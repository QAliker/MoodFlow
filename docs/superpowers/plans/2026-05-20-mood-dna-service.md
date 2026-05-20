# MoodDna Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Compute a unique visual "Mood DNA" portrait from a user's last 50 moods and expose it via `GET /api/mood/dna`.

**Architecture:** Pure service class `MoodDnaService` contains all computation logic; a thin `MoodDnaController` delegates to it and returns JSON. No new model or migration needed — data is derived entirely from existing `moods` rows. No caching (recalculated on each call per spec).

**Tech Stack:** Laravel 11, PHP 8.3, Eloquent, Sanctum auth, PHPUnit feature + unit tests.

---

## File Map

| Action | Path |
|--------|------|
| Create | `app/Services/MoodDnaService.php` |
| Create | `app/Http/Controllers/MoodDnaController.php` |
| Modify | `routes/api.php` |
| Create | `tests/Unit/Services/MoodDnaServiceTest.php` |
| Create | `tests/Feature/Api/MoodDnaTest.php` |

---

## Data Sources in `Mood` model

| Gene | Source column/field |
|------|-------------------|
| `dominantEmotion` | `moods.emotion` |
| `energyProfile` | avg of `moods.energy` |
| `valenceProfile` | avg of `moods.valence` |
| `consistency` | std-dev of `moods.energy` values |
| `peakHour` | mode of `created_at->hour` |
| `genreFingerprint` | `moods.context.genres[]` — flatMap, countBy, top 3 |
| `activityProfile` | `moods.context.activity_mode` — filter nulls, countBy, top 2 |
| `shape` | unique emotion count → sides 3–8 |
| `colors` | avg energy/valence/intensity → 4 HSL-derived hex strings |
| `pulseSpeed` | avg energy mapped to float |
| `symmetry` | std-dev of energy mapped to float 0.0–1.0 |

---

## Gene Computation Rules

### energyProfile
```
avg < 35  → "calm"
35–65     → "moderate"
> 65      → "energetic"
```

### valenceProfile
```
avg < -0.2        → "melancholic"
-0.2 to 0.3       → "neutral"
> 0.3             → "positive"
```

### consistency (raw float, rounded to 2 decimal places)
```php
$mean     = array_sum($energies) / count($energies);
$variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $energies)) / count($energies);
$stdDev   = round(sqrt($variance), 2);
```

### shape (sides)
```
uniqueEmotionCount  →  sides
1                   →  3
2                   →  4
3                   →  5
4                   →  6
5                   →  7
6+                  →  8
Formula: min(8, max(3, uniqueEmotionCount + 2))
```

### colors (4 hex strings)
```
baseHue  = avg(energy) / 100 * 360           → 0–360°
sat      = (avg(valence) + 1) / 2 * 100       → 0–100%
light    = avg(intensity) / 100 * 40 + 30     → 30–70%

4 colors with hues: baseHue, baseHue+30, baseHue+90, baseHue+180 (all mod 360)
All share same sat & light. Convert each HSL → hex.
```

### pulseSpeed (float)
```
avg energy < 35   → 0.5
35–65             → 1.0
> 65              → 2.0
```

### symmetry (float 0.0–1.0, based on std-dev of energy)
```
stdDev < 10   → 1.0
10–20         → 0.75
20–35         → 0.5
> 35          → 0.25
```

---

## Task 1: Write Failing Unit Tests for MoodDnaService

**Files:**
- Create: `tests/Unit/Services/MoodDnaServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Mood;
use App\Models\User;
use App\Services\MoodDnaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MoodDnaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MoodDnaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MoodDnaService();
    }

    public function test_insufficient_data_when_fewer_than_10_moods(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 5);

        $result = $this->service->generateDna($user);

        $this->assertEquals('insufficient_data', $result['status']);
        $this->assertEquals(10, $result['minimum']);
        $this->assertEquals(5, $result['current']);
    }

    public function test_insufficient_data_when_zero_moods(): void
    {
        $user   = User::factory()->create();
        $result = $this->service->generateDna($user);

        $this->assertEquals('insufficient_data', $result['status']);
        $this->assertEquals(0, $result['current']);
    }

    public function test_returns_dna_array_keys_when_enough_moods(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10);

        $result = $this->service->generateDna($user);

        $this->assertArrayHasKey('dominantEmotion',  $result);
        $this->assertArrayHasKey('energyProfile',    $result);
        $this->assertArrayHasKey('valenceProfile',   $result);
        $this->assertArrayHasKey('consistency',      $result);
        $this->assertArrayHasKey('peakHour',         $result);
        $this->assertArrayHasKey('genreFingerprint', $result);
        $this->assertArrayHasKey('activityProfile',  $result);
        $this->assertArrayHasKey('shape',            $result);
        $this->assertArrayHasKey('colors',           $result);
        $this->assertArrayHasKey('pulseSpeed',       $result);
        $this->assertArrayHasKey('symmetry',         $result);
    }

    public function test_energy_profile_calm(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 20]);

        $result = $this->service->generateDna($user);

        $this->assertEquals('calm', $result['energyProfile']);
    }

    public function test_energy_profile_moderate(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 50]);

        $result = $this->service->generateDna($user);

        $this->assertEquals('moderate', $result['energyProfile']);
    }

    public function test_energy_profile_energetic(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 80]);

        $result = $this->service->generateDna($user);

        $this->assertEquals('energetic', $result['energyProfile']);
    }

    public function test_valence_profile_melancholic(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['valence' => -0.5]);

        $result = $this->service->generateDna($user);

        $this->assertEquals('melancholic', $result['valenceProfile']);
    }

    public function test_valence_profile_neutral(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['valence' => 0.1]);

        $result = $this->service->generateDna($user);

        $this->assertEquals('neutral', $result['valenceProfile']);
    }

    public function test_valence_profile_positive(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['valence' => 0.5]);

        $result = $this->service->generateDna($user);

        $this->assertEquals('positive', $result['valenceProfile']);
    }

    public function test_dominant_emotion_is_most_frequent(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 7, ['emotion' => 'happy']);
        $this->createMoods($user, 3, ['emotion' => 'calm']);

        $result = $this->service->generateDna($user);

        $this->assertEquals('happy', $result['dominantEmotion']);
    }

    public function test_shape_three_sides_for_one_emotion(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['emotion' => 'happy']);

        $result = $this->service->generateDna($user);

        $this->assertEquals(3, $result['shape']);
    }

    public function test_shape_six_sides_for_four_emotions(): void
    {
        $user = User::factory()->create();
        foreach (['happy', 'sad', 'calm', 'intense'] as $emotion) {
            $this->createMoods($user, 3, ['emotion' => $emotion]);
        }
        // 12 moods, 4 unique emotions → 6 sides
        $result = $this->service->generateDna($user);

        $this->assertEquals(6, $result['shape']);
    }

    public function test_shape_max_eight_sides(): void
    {
        $user = User::factory()->create();
        foreach (['happy', 'sad', 'calm', 'intense', 'neutral', 'reflective', 'upbeat', 'ambient'] as $emotion) {
            $this->createMoods($user, 2, ['emotion' => $emotion]);
        }
        // 16 moods, 8 unique emotions → min(8, max(3, 8+2)) = 8

        $result = $this->service->generateDna($user);

        $this->assertEquals(8, $result['shape']);
    }

    public function test_colors_returns_four_hex_strings(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 60, 'valence' => 0.2, 'intensity' => 50]);

        $result = $this->service->generateDna($user);

        $this->assertCount(4, $result['colors']);
        foreach ($result['colors'] as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        }
    }

    public function test_pulse_speed_slow_for_low_energy(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 20]);

        $result = $this->service->generateDna($user);

        $this->assertEquals(0.5, $result['pulseSpeed']);
    }

    public function test_pulse_speed_normal_for_moderate_energy(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 50]);

        $result = $this->service->generateDna($user);

        $this->assertEquals(1.0, $result['pulseSpeed']);
    }

    public function test_pulse_speed_fast_for_high_energy(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 80]);

        $result = $this->service->generateDna($user);

        $this->assertEquals(2.0, $result['pulseSpeed']);
    }

    public function test_symmetry_high_for_stable_energy(): void
    {
        $user = User::factory()->create();
        // All same energy → std dev = 0 → symmetry = 1.0
        $this->createMoods($user, 10, ['energy' => 50]);

        $result = $this->service->generateDna($user);

        $this->assertEquals(1.0, $result['symmetry']);
    }

    public function test_symmetry_low_for_volatile_energy(): void
    {
        $user = User::factory()->create();
        // Alternating 10 and 90 → std dev ~40 → symmetry = 0.25
        for ($i = 0; $i < 5; $i++) {
            $this->createMoods($user, 1, ['energy' => 10]);
            $this->createMoods($user, 1, ['energy' => 90]);
        }

        $result = $this->service->generateDna($user);

        $this->assertEquals(0.25, $result['symmetry']);
    }

    public function test_genre_fingerprint_top_3(): void
    {
        $user = User::factory()->create();
        foreach (['pop', 'pop', 'pop', 'rock', 'rock', 'jazz', 'metal'] as $i => $genre) {
            Mood::create([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => [$genre], 'activity_mode' => null, 'weather' => null],
                'tracks'    => [],
            ]);
        }
        // Need 10 moods total — add 3 more neutral
        $this->createMoods($user, 3);

        $result = $this->service->generateDna($user);

        $this->assertCount(3, $result['genreFingerprint']);
        $this->assertEquals('pop',  $result['genreFingerprint'][0]);
        $this->assertEquals('rock', $result['genreFingerprint'][1]);
        $this->assertEquals('jazz', $result['genreFingerprint'][2]);
    }

    public function test_activity_profile_top_2(): void
    {
        $user = User::factory()->create();
        // 3 workout, 2 focus, 1 sleep, 4 null
        foreach (['workout', 'workout', 'workout', 'focus', 'focus', 'sleep'] as $mode) {
            Mood::create([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => [], 'activity_mode' => $mode, 'weather' => null],
                'tracks'    => [],
            ]);
        }
        $this->createMoods($user, 4);  // nulls

        $result = $this->service->generateDna($user);

        $this->assertCount(2, $result['activityProfile']);
        $this->assertEquals('workout', $result['activityProfile'][0]);
        $this->assertEquals('focus',   $result['activityProfile'][1]);
    }

    public function test_only_last_50_moods_used(): void
    {
        $user = User::factory()->create();
        // 60 moods: oldest 10 have emotion 'old', newest 50 have 'happy'
        $this->createMoods($user, 10, ['emotion' => 'old']);
        $this->createMoods($user, 50, ['emotion' => 'happy']);

        $result = $this->service->generateDna($user);

        $this->assertEquals('happy', $result['dominantEmotion']);
    }

    public function test_peak_hour_returns_most_frequent_hour(): void
    {
        $user = User::factory()->create();
        // Create 10 moods at hour 14 and 3 at hour 8
        for ($i = 0; $i < 10; $i++) {
            $mood = Mood::create([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => [], 'activity_mode' => null, 'weather' => null],
                'tracks'    => [],
            ]);
            \Illuminate\Support\Facades\DB::table('moods')
                ->where('id', $mood->id)
                ->update(['created_at' => now()->setHour(14)]);
        }
        for ($i = 0; $i < 3; $i++) {
            $mood = Mood::create([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => [], 'activity_mode' => null, 'weather' => null],
                'tracks'    => [],
            ]);
            \Illuminate\Support\Facades\DB::table('moods')
                ->where('id', $mood->id)
                ->update(['created_at' => now()->setHour(8)]);
        }

        $result = $this->service->generateDna($user);

        $this->assertEquals(14, $result['peakHour']);
    }

    // Helper: create N moods with optional overrides
    private function createMoods(User $user, int $count, array $override = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            Mood::create(array_merge([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => ['pop'], 'activity_mode' => null, 'weather' => null],
                'tracks'    => [],
            ], $override));
        }
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose run --rm api php artisan test tests/Unit/Services/MoodDnaServiceTest.php --no-coverage
```

Expected: Multiple failures — `App\Services\MoodDnaService not found`

---

## Task 2: Implement MoodDnaService

**Files:**
- Create: `app/Services/MoodDnaService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MoodDnaService
{
    public function generateDna(User $user): array
    {
        $moods = $user->moods()
            ->latest()
            ->limit(50)
            ->get();

        if ($moods->count() < 10) {
            return [
                'status'  => 'insufficient_data',
                'minimum' => 10,
                'current' => $moods->count(),
            ];
        }

        $energies    = $moods->pluck('energy')->map(fn($v) => (float) $v)->all();
        $valences    = $moods->pluck('valence')->map(fn($v) => (float) $v)->all();
        $intensities = $moods->pluck('intensity')->map(fn($v) => (float) $v)->all();

        $avgEnergy    = array_sum($energies) / count($energies);
        $avgValence   = array_sum($valences) / count($valences);
        $avgIntensity = array_sum($intensities) / count($intensities);
        $stdDev       = $this->stdDev($energies);

        return [
            'dominantEmotion'  => $this->dominantEmotion($moods),
            'energyProfile'    => $this->energyProfile($avgEnergy),
            'valenceProfile'   => $this->valenceProfile($avgValence),
            'consistency'      => $stdDev,
            'peakHour'         => $this->peakHour($moods),
            'genreFingerprint' => $this->genreFingerprint($moods),
            'activityProfile'  => $this->activityProfile($moods),
            'shape'            => $this->shape($moods),
            'colors'           => $this->colors($avgEnergy, $avgValence, $avgIntensity),
            'pulseSpeed'       => $this->pulseSpeed($avgEnergy),
            'symmetry'         => $this->symmetry($stdDev),
        ];
    }

    private function energyProfile(float $avg): string
    {
        return match (true) {
            $avg < 35  => 'calm',
            $avg <= 65 => 'moderate',
            default    => 'energetic',
        };
    }

    private function valenceProfile(float $avg): string
    {
        return match (true) {
            $avg < -0.2 => 'melancholic',
            $avg <= 0.3 => 'neutral',
            default     => 'positive',
        };
    }

    private function dominantEmotion(Collection $moods): ?string
    {
        return $moods->groupBy('emotion')
            ->sortByDesc(fn(Collection $g) => $g->count())
            ->keys()
            ->first();
    }

    private function peakHour(Collection $moods): ?int
    {
        $hour = $moods->groupBy(fn($m) => $m->created_at->hour)
            ->sortByDesc(fn(Collection $g) => $g->count())
            ->keys()
            ->first();

        return $hour !== null ? (int) $hour : null;
    }

    private function genreFingerprint(Collection $moods): array
    {
        return $moods
            ->flatMap(fn($m) => $m->context['genres'] ?? [])
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(3)
            ->values()
            ->all();
    }

    private function activityProfile(Collection $moods): array
    {
        return $moods
            ->map(fn($m) => $m->context['activity_mode'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(2)
            ->values()
            ->all();
    }

    private function shape(Collection $moods): int
    {
        $unique = $moods->pluck('emotion')->unique()->count();

        return min(8, max(3, $unique + 2));
    }

    private function colors(float $energy, float $valence, float $intensity): array
    {
        $baseHue = $energy / 100 * 360;
        $sat     = ($valence + 1) / 2 * 100;
        $light   = $intensity / 100 * 40 + 30;

        return [
            $this->hslToHex($baseHue,           $sat, $light),
            $this->hslToHex(fmod($baseHue + 30,  360), $sat, $light),
            $this->hslToHex(fmod($baseHue + 90,  360), $sat, $light),
            $this->hslToHex(fmod($baseHue + 180, 360), $sat, $light),
        ];
    }

    private function pulseSpeed(float $energy): float
    {
        return match (true) {
            $energy < 35  => 0.5,
            $energy <= 65 => 1.0,
            default       => 2.0,
        };
    }

    private function symmetry(float $stdDev): float
    {
        return match (true) {
            $stdDev < 10  => 1.0,
            $stdDev < 20  => 0.75,
            $stdDev < 35  => 0.5,
            default       => 0.25,
        };
    }

    private function stdDev(array $values): float
    {
        $mean     = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values);

        return round(sqrt($variance), 2);
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return sprintf('#%02x%02x%02x', $v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = (int) round($this->hueToRgb($p, $q, $h + 1 / 3) * 255);
        $g = (int) round($this->hueToRgb($p, $q, $h) * 255);
        $b = (int) round($this->hueToRgb($p, $q, $h - 1 / 3) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
```

- [ ] **Step 2: Run unit tests — expect all pass**

```bash
docker compose run --rm api php artisan test tests/Unit/Services/MoodDnaServiceTest.php --no-coverage
```

Expected: All green. If a test fails, fix the logic before continuing.

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/app/Services/MoodDnaService.php \
        moodflow-api/tests/Unit/Services/MoodDnaServiceTest.php
git commit -m "feat: add MoodDnaService with gene computation"
```

---

## Task 3: Write Failing Feature Test for GET /api/mood/dna

**Files:**
- Create: `tests/Feature/Api/MoodDnaTest.php`

- [ ] **Step 1: Write the failing feature tests**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Genre;
use App\Models\Mood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoodDnaTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/mood/dna')->assertUnauthorized();
    }

    public function test_returns_insufficient_data_when_fewer_than_10_moods(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMoods($user, 5);

        $response = $this->actingAs($user)->getJson('/api/mood/dna');

        $response->assertOk()->assertJson([
            'data' => [
                'status'  => 'insufficient_data',
                'minimum' => 10,
                'current' => 5,
            ],
        ]);
    }

    public function test_returns_dna_structure_with_enough_moods(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMoods($user, 10);

        $response = $this->actingAs($user)->getJson('/api/mood/dna');

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'dominantEmotion',
                'energyProfile',
                'valenceProfile',
                'consistency',
                'peakHour',
                'genreFingerprint',
                'activityProfile',
                'shape',
                'colors',
                'pulseSpeed',
                'symmetry',
            ],
        ]);
    }

    public function test_dna_shape_is_between_3_and_8(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMoods($user, 10);

        $shape = $this->actingAs($user)->getJson('/api/mood/dna')->json('data.shape');

        $this->assertGreaterThanOrEqual(3, $shape);
        $this->assertLessThanOrEqual(8, $shape);
    }

    public function test_dna_colors_contains_4_hex_colors(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMoods($user, 10);

        $colors = $this->actingAs($user)->getJson('/api/mood/dna')->json('data.colors');

        $this->assertCount(4, $colors);
        foreach ($colors as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        }
    }

    public function test_dna_reflects_correct_energy_profile(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMoods($user, 10, ['energy' => 80]);

        $profile = $this->actingAs($user)->getJson('/api/mood/dna')->json('data.energyProfile');

        $this->assertEquals('energetic', $profile);
    }

    // helpers
    private function createUserWithGenre(): User
    {
        $user  = User::factory()->create();
        $genre = Genre::firstOrCreate(['slug' => 'pop'], ['name' => 'pop', 'deezer_id' => 132]);
        $user->genres()->attach($genre->id);

        return $user;
    }

    private function createMoods(User $user, int $count, array $override = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            Mood::create(array_merge([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => ['pop'], 'activity_mode' => null, 'weather' => null],
                'tracks'    => [],
            ], $override));
        }
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDnaTest.php --no-coverage
```

Expected: Failures — route not found (404).

---

## Task 4: Add Controller and Route

**Files:**
- Create: `app/Http/Controllers/MoodDnaController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Services\MoodDnaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodDnaController extends Controller
{
    public function __construct(
        private readonly MoodDnaService $dnaService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dna = $this->dnaService->generateDna($request->user());

        return response()->json(['data' => $dna]);
    }
}
```

- [ ] **Step 2: Register the route in `routes/api.php`**

Add inside the `auth:sanctum` middleware group (after the existing `users/me` routes, before the `has.genres` group):

```php
use App\Http\Controllers\MoodDnaController;

// Inside Route::middleware('auth:sanctum')->group(function (): void { ... })
Route::get('/mood/dna', MoodDnaController::class);
```

The full updated `routes/api.php` middleware block should look like:

```php
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me/genres', [UserController::class, 'updateGenres']);

    Route::get('/mood/dna', MoodDnaController::class);

    Route::middleware('has.genres')->group(function (): void {
        Route::post('/mood/analyze', [MoodController::class, 'analyze']);
        Route::get('/mood/history', [MoodController::class, 'history']);
        Route::get('/mood/stats', [MoodController::class, 'stats']);
    });
});
```

Note: `MoodDnaController` is placed outside `has.genres` so users without genres can still get `insufficient_data` rather than a 403.

- [ ] **Step 3: Verify route registered**

```bash
docker compose run --rm api php artisan route:list --path=mood/dna
```

Expected output includes: `GET | api/mood/dna | App\Http\Controllers\MoodDnaController`

- [ ] **Step 4: Run feature tests**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDnaTest.php --no-coverage
```

Expected: All green.

- [ ] **Step 5: Run full test suite to check no regressions**

```bash
docker compose run --rm api php artisan test --no-coverage
```

Expected: All green.

- [ ] **Step 6: Commit**

```bash
git add moodflow-api/app/Http/Controllers/MoodDnaController.php \
        moodflow-api/routes/api.php \
        moodflow-api/tests/Feature/Api/MoodDnaTest.php
git commit -m "feat: add GET /api/mood/dna endpoint"
```

---

## Self-Review

**Spec coverage check:**

| Requirement | Covered by |
|-------------|-----------|
| `generateDna(User)` method | Task 2, service |
| Fetch last 50 moods | Task 2, `limit(50)->latest()` |
| `dominantEmotion` | Task 2 + unit test |
| `energyProfile` calm/moderate/energetic | Task 2 + unit tests |
| `valenceProfile` melancholic/neutral/positive | Task 2 + unit tests |
| `consistency` (std dev) | Task 2 + unit test |
| `peakHour` | Task 2 + unit test |
| `genreFingerprint` top 3 | Task 2 + unit test |
| `activityProfile` top 2 | Task 2 + unit test |
| `shape` 3–8 sides | Task 2 + unit tests |
| `colors` 4 HSL-derived hex | Task 2 + unit + feature tests |
| `pulseSpeed` based on energy | Task 2 + unit tests |
| `symmetry` based on consistency | Task 2 + unit test |
| `GET /api/mood/dna` route | Task 4 |
| No cache | service has no cache calls ✓ |
| `< 10 moods → insufficient_data` | Task 2 + unit + feature tests |
| `status`, `minimum`, `current` fields | Task 2 + feature test |

**Placeholder scan:** No TBDs or TODOs found.

**Type consistency:** `generateDna` returns `array` throughout. `hslToHex` returns `string`. `stdDev` returns `float`. All consistent across tasks.
