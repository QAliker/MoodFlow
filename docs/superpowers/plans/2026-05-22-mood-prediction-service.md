# MoodPredictionService Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build MoodPredictionService that predicts a user's mood at a given time using historical patterns, then expose it via GET /api/mood/predict.

**Architecture:** Service loads all user moods, filters by day-of-week (±1) and hour (±2) and optional weather condition, computes averages when confidence ≥ 0.3 (≥ 3 matches), falls back to ContextService passive modifiers otherwise. Invokable controller resolves weather from optional lat/lon via WeatherService.

**Tech Stack:** PHP 8.3, Laravel 11, Eloquent, Carbon, ContextService (existing), WeatherService (existing), PHPUnit + RefreshDatabase

---

## File Map

| Action | Path (relative to `moodflow-api/`) | Responsibility |
|--------|-------------------------------------|----------------|
| Create | `app/Services/MoodPredictionService.php` | Prediction logic: filter, confidence, averages, message, fallback |
| Create | `app/Http/Controllers/MoodPredictionController.php` | Thin invokable — resolve weather, delegate to service |
| Create | `tests/Unit/Services/MoodPredictionServiceTest.php` | Unit tests for service |
| Create | `tests/Feature/Api/MoodPredictionTest.php` | Feature tests for GET /mood/predict |
| Modify | `routes/api.php` | Add route to auth:sanctum group |

### Key domain facts for implementors

- `Mood` columns: `user_id`, `source`, `emotion`, `energy` (int 0–100), `valence` (float −1.0–1.0), `intensity` (int 0–100), `context` (json), `tracks` (json), `created_at`
- `context` JSON shape: `{ "weather": {"condition": "rain"|null, ...}, "activity_mode": "focus"|null, "genres": [] }`
- `ContextService::getModifier(Carbon, ?string): array` returns `{ energy_mod: int±15, valence_mod: int±15, intensity_mod: int±15 }` (all on 0–100 scale)
- No `MoodFactory` exists — use `Mood::create()` + `DB::table('moods')->where('id', $id)->update(['created_at' => $date])` to set timestamps (same pattern as `MoodDnaServiceTest`)
- Routes: `auth:sanctum` group at line 20 of `routes/api.php`; `/mood/predict` goes alongside `/mood/dna` (no `has.genres` needed)

---

## Task 1: Write failing unit tests for filtering, confidence, averages, and suggested activity

**Files:**
- Create: `moodflow-api/tests/Unit/Services/MoodPredictionServiceTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Mood;
use App\Models\User;
use App\Services\ContextService;
use App\Services\MoodPredictionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoodPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MoodPredictionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MoodPredictionService(new ContextService());
        $this->user    = User::factory()->create();
    }

    private function makeMood(array $attrs): Mood
    {
        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $mood = Mood::create(array_merge([
            'user_id'   => $this->user->id,
            'source'    => 'slider',
            'emotion'   => 'calm',
            'energy'    => 50,
            'valence'   => 0.0,
            'intensity' => 50,
            'context'   => ['genres' => [], 'activity_mode' => null, 'weather' => null],
            'tracks'    => [],
        ], $attrs));

        if ($createdAt !== null) {
            DB::table('moods')->where('id', $mood->id)->update(['created_at' => $createdAt]);
        }

        return $mood;
    }

    // === FILTERING — day of week ===

    public function test_includes_moods_from_same_day_of_week(): void
    {
        $monday = Carbon::parse('2026-05-18 10:00:00'); // dayOfWeek=1
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['energy' => 60, 'valence' => 0.3, 'intensity' => 50,
                'created_at' => $monday->copy()->subWeeks($i)]);
        }
        // Friday (dayOfWeek=5) — diff=4 → excluded
        $this->makeMood(['energy' => 10, 'valence' => -0.9, 'intensity' => 10,
            'created_at' => Carbon::parse('2026-05-22 10:00:00')]);

        $now    = Carbon::parse('2026-05-25 10:00:00'); // Monday
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(3, $result['based_on']);
    }

    public function test_includes_adjacent_days_within_one(): void
    {
        // Tuesday (day=2), adjacent to Monday (day=1) — diff=1 → included
        $tuesday = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['energy' => 70, 'valence' => 0.5, 'intensity' => 60,
                'created_at' => $tuesday->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-25 10:00:00'); // Monday
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(3, $result['based_on']);
    }

    public function test_excludes_days_beyond_one(): void
    {
        // Wednesday (day=3) from Monday (day=1) — diff=2 → excluded
        $wednesday = Carbon::parse('2026-05-20 10:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['energy' => 70, 'valence' => 0.5, 'intensity' => 60,
                'created_at' => $wednesday->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-25 10:00:00'); // Monday
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(0, $result['based_on']);
    }

    // === FILTERING — hour ===

    public function test_includes_moods_within_two_hour_window(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00'); // Tuesday 10h — within ±2h of 11h
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['energy' => 60, 'valence' => 0.3, 'intensity' => 50,
                'created_at' => $base->copy()->subWeeks($i)]);
        }
        // 07h: diff=4 → excluded
        $this->makeMood(['energy' => 10, 'valence' => -0.9, 'intensity' => 10,
            'created_at' => Carbon::parse('2026-05-19 07:00:00')]);

        $now    = Carbon::parse('2026-05-26 11:00:00'); // Tuesday 11h
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(3, $result['based_on']);
    }

    // === FILTERING — weather ===

    public function test_filters_by_weather_when_provided(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['energy' => 30, 'valence' => -0.4, 'intensity' => 30,
                'context' => ['weather' => ['condition' => 'rain'], 'activity_mode' => null, 'genres' => []],
                'created_at' => $base->copy()->subWeeks($i)]);
        }
        // clear mood — excluded when weather='rain'
        $this->makeMood(['energy' => 90, 'valence' => 0.9, 'intensity' => 90,
            'context' => ['weather' => ['condition' => 'clear'], 'activity_mode' => null, 'genres' => []],
            'created_at' => $base->copy()->subWeeks(3)]);

        $now    = Carbon::parse('2026-05-26 10:00:00'); // Tuesday
        $result = $this->service->predict($this->user->fresh(), $now, 'rain');

        $this->assertEquals(3, $result['based_on']);
    }

    public function test_skips_weather_filter_when_weather_is_null(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        // Mix of conditions — all included when weather=null
        foreach (['rain', 'clear', 'clouds'] as $i => $condition) {
            $this->makeMood(['energy' => 60, 'valence' => 0.1, 'intensity' => 50,
                'context' => ['weather' => ['condition' => $condition], 'activity_mode' => null, 'genres' => []],
                'created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now, null);

        $this->assertEquals(3, $result['based_on']);
    }

    // === CONFIDENCE ===

    public function test_confidence_is_zero_when_fewer_than_three_matches(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 2; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(0.0, $result['confidence']);
        $this->assertEquals(0,   $result['based_on']);
    }

    public function test_confidence_is_zero_point_three_at_exactly_three_matches(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(0.3, $result['confidence']);
        $this->assertEquals(3,   $result['based_on']);
    }

    public function test_confidence_caps_at_one_with_ten_or_more_matches(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 12; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(1.0, $result['confidence']);
    }

    // === AVERAGES ===

    public function test_predicted_mood_is_average_of_similar_moods(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        $this->makeMood(['energy' => 40, 'valence' => -0.2, 'intensity' => 30, 'created_at' => $base]);
        $this->makeMood(['energy' => 60, 'valence' =>  0.2, 'intensity' => 50, 'created_at' => $base->copy()->subWeeks(1)]);
        $this->makeMood(['energy' => 80, 'valence' =>  0.6, 'intensity' => 70, 'created_at' => $base->copy()->subWeeks(2)]);

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals(60,  $result['predicted_mood']['energy']);
        $this->assertEqualsWithDelta(0.2, $result['predicted_mood']['valence'], 0.001);
        $this->assertEquals(50,  $result['predicted_mood']['intensity']);
    }

    // === SUGGESTED ACTIVITY ===

    public function test_suggested_activity_is_most_frequent_mode(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        $this->makeMood(['context' => ['activity_mode' => 'focus',   'genres' => [], 'weather' => null], 'created_at' => $base]);
        $this->makeMood(['context' => ['activity_mode' => 'focus',   'genres' => [], 'weather' => null], 'created_at' => $base->copy()->subWeeks(1)]);
        $this->makeMood(['context' => ['activity_mode' => 'workout', 'genres' => [], 'weather' => null], 'created_at' => $base->copy()->subWeeks(2)]);

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertEquals('focus', $result['suggested_activity']);
    }

    public function test_suggested_activity_is_null_when_no_modes_recorded(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['context' => ['activity_mode' => null, 'genres' => [], 'weather' => null],
                'created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 10:00:00');
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertNull($result['suggested_activity']);
    }
}
```

- [ ] **Step 2: Run — verify failure**

```bash
cd moodflow-api && php artisan test tests/Unit/Services/MoodPredictionServiceTest.php 2>&1
```

Expected: `Error: Class "App\Services\MoodPredictionService" not found`

---

## Task 2: Implement MoodPredictionService

**Files:**
- Create: `moodflow-api/app/Services/MoodPredictionService.php`

- [ ] **Step 1: Create the service**

```php
<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MoodPredictionService
{
    public function __construct(private readonly ContextService $contextService) {}

    public function predict(User $user, Carbon $now, ?string $weather = null): array
    {
        $similar    = $this->findSimilarMoods($user, $now, $weather);
        $count      = $similar->count();
        $confidence = $this->computeConfidence($count);

        if ($confidence < 0.3) {
            return $this->fallback($now, $weather);
        }

        $predicted = $this->averageMood($similar);

        return [
            'predicted_mood'     => $predicted,
            'confidence'         => round($confidence, 2),
            'based_on'           => $count,
            'suggested_activity' => $this->suggestedActivity($similar),
            'message'            => $this->buildMessage($now, $weather, $predicted),
        ];
    }

    private function findSimilarMoods(User $user, Carbon $now, ?string $weather): Collection
    {
        $targetDay  = $now->dayOfWeek;
        $targetHour = $now->hour;

        return $user->moods()->get()->filter(function ($mood) use ($targetDay, $targetHour, $weather) {
            $createdAt = $mood->created_at;

            $dayDiff = abs($createdAt->dayOfWeek - $targetDay);
            if (min($dayDiff, 7 - $dayDiff) > 1) {
                return false;
            }

            $hourDiff = abs($createdAt->hour - $targetHour);
            if (min($hourDiff, 24 - $hourDiff) > 2) {
                return false;
            }

            if ($weather !== null && ($mood->context['weather']['condition'] ?? null) !== $weather) {
                return false;
            }

            return true;
        });
    }

    private function computeConfidence(int $count): float
    {
        return $count < 3 ? 0.0 : min(1.0, $count / 10);
    }

    private function averageMood(Collection $moods): array
    {
        return [
            'energy'    => (int) round($moods->avg('energy')),
            'valence'   => round($moods->avg('valence'), 4),
            'intensity' => (int) round($moods->avg('intensity')),
        ];
    }

    private function suggestedActivity(Collection $moods): ?string
    {
        return $moods
            ->map(fn($m) => $m->context['activity_mode'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    private function buildMessage(Carbon $now, ?string $weather, array $predicted): string
    {
        $days   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $hour   = $now->hour;
        $period = match (true) {
            $hour >= 6  && $hour < 12 => 'matin',
            $hour >= 12 && $hour < 18 => 'après-midi',
            $hour >= 18 && $hour < 22 => 'soir',
            default                   => 'nuit',
        };

        $weatherWords = ['clear' => 'ensoleillé', 'clouds' => 'nuageux', 'rain' => 'pluvieux', 'snow' => 'neigeux'];
        $weatherWord  = $weather ? ($weatherWords[$weather] ?? null) : null;

        $context = ucfirst($days[$now->dayOfWeek]) . ' ' . $period;
        if ($weatherWord) {
            $context .= ' ' . $weatherWord;
        }

        return "{$context}, tu es généralement {$this->moodDescription($predicted['energy'], $predicted['valence'])}";
    }

    private function moodDescription(int $energy, float $valence): string
    {
        $e = match (true) {
            $energy < 33  => 'low',
            $energy <= 66 => 'medium',
            default       => 'high',
        };
        $v = match (true) {
            $valence < -0.33 => 'negative',
            $valence <= 0.33 => 'neutral',
            default          => 'positive',
        };

        return match ("{$e}_{$v}") {
            'low_negative'    => 'calme et mélancolique',
            'low_neutral'     => 'calme et concentré',
            'low_positive'    => 'calme et serein',
            'medium_negative' => 'modéré et introspectif',
            'medium_neutral'  => 'équilibré',
            'medium_positive' => 'détendu et positif',
            'high_negative'   => 'agité',
            'high_neutral'    => 'énergique',
            default           => 'dynamique et enthousiaste',
        };
    }

    private function fallback(Carbon $now, ?string $weather): array
    {
        $mod       = $this->contextService->getModifier($now, $weather);
        $energy    = (int) min(100, max(0, 50 + $mod['energy_mod']));
        $valence   = round(min(1.0, max(-1.0, $mod['valence_mod'] / 100)), 4);
        $intensity = (int) min(100, max(0, 50 + $mod['intensity_mod']));
        $predicted = compact('energy', 'valence', 'intensity');

        return [
            'predicted_mood'     => $predicted,
            'confidence'         => 0.0,
            'based_on'           => 0,
            'suggested_activity' => null,
            'message'            => $this->buildMessage($now, $weather, $predicted),
        ];
    }
}
```

- [ ] **Step 2: Run unit tests — verify they pass**

```bash
cd moodflow-api && php artisan test tests/Unit/Services/MoodPredictionServiceTest.php 2>&1
```

Expected: All green

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/app/Services/MoodPredictionService.php \
        moodflow-api/tests/Unit/Services/MoodPredictionServiceTest.php
git commit -m "feat: add MoodPredictionService with filtering, confidence, and message generation"
```

---

## Task 3: Additional unit tests — message and fallback

**Files:**
- Modify: `moodflow-api/tests/Unit/Services/MoodPredictionServiceTest.php`

- [ ] **Step 1: Append these test methods (before the closing `}` of the class)**

```php
    // === MESSAGE ===

    public function test_message_contains_day_period_and_weather(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00'); // Tuesday
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['energy' => 30, 'valence' => -0.1, 'intensity' => 40,
                'context' => ['weather' => ['condition' => 'rain'], 'activity_mode' => null, 'genres' => []],
                'created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 10:30:00'); // Tuesday 10h = matin
        $result = $this->service->predict($this->user->fresh(), $now, 'rain');

        $this->assertStringContainsString('mardi',              $result['message']);
        $this->assertStringContainsString('matin',              $result['message']);
        $this->assertStringContainsString('pluvieux',           $result['message']);
        $this->assertStringContainsString('tu es généralement', $result['message']);
    }

    public function test_message_omits_weather_when_null(): void
    {
        $base = Carbon::parse('2026-05-19 14:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $now    = Carbon::parse('2026-05-26 14:00:00'); // après-midi
        $result = $this->service->predict($this->user->fresh(), $now);

        $this->assertStringContainsString('après-midi',         $result['message']);
        $this->assertStringNotContainsString('ensoleillé',      $result['message']);
        $this->assertStringNotContainsString('pluvieux',        $result['message']);
    }

    public function test_message_period_soir(): void
    {
        $base = Carbon::parse('2026-05-19 20:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $result = $this->service->predict($this->user->fresh(), Carbon::parse('2026-05-26 20:00:00'));

        $this->assertStringContainsString('soir', $result['message']);
    }

    public function test_message_period_nuit(): void
    {
        $base = Carbon::parse('2026-05-19 23:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $result = $this->service->predict($this->user->fresh(), Carbon::parse('2026-05-26 23:00:00'));

        $this->assertStringContainsString('nuit', $result['message']);
    }

    // === FALLBACK ===

    public function test_fallback_returned_when_no_moods(): void
    {
        $result = $this->service->predict($this->user->fresh(), Carbon::parse('2026-05-26 10:00:00'));

        $this->assertEquals(0.0, $result['confidence']);
        $this->assertEquals(0,   $result['based_on']);
        $this->assertNull($result['suggested_activity']);
        $this->assertArrayHasKey('energy',    $result['predicted_mood']);
        $this->assertArrayHasKey('valence',   $result['predicted_mood']);
        $this->assertArrayHasKey('intensity', $result['predicted_mood']);
        $this->assertNotEmpty($result['message']);
    }

    public function test_fallback_applies_friday_evening_energy_modifier(): void
    {
        // Friday 19h: friday-evening modifier → energy_mod=+15, base=50 → energy=65
        $result = $this->service->predict($this->user->fresh(), Carbon::parse('2026-05-22 19:00:00'));

        $this->assertEquals(65, $result['predicted_mood']['energy']);
    }

    public function test_response_structure_is_always_complete(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 3; $i++) {
            $this->makeMood(['created_at' => $base->copy()->subWeeks($i)]);
        }

        $result = $this->service->predict($this->user->fresh(), Carbon::parse('2026-05-26 10:00:00'));

        $this->assertArrayHasKey('predicted_mood',     $result);
        $this->assertArrayHasKey('confidence',         $result);
        $this->assertArrayHasKey('based_on',           $result);
        $this->assertArrayHasKey('suggested_activity', $result);
        $this->assertArrayHasKey('message',            $result);
        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0,   $result['confidence']);
    }
```

- [ ] **Step 2: Run full unit test class**

```bash
cd moodflow-api && php artisan test tests/Unit/Services/MoodPredictionServiceTest.php 2>&1
```

Expected: All green

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/tests/Unit/Services/MoodPredictionServiceTest.php
git commit -m "test: expand MoodPredictionService unit tests for message and fallback"
```

---

## Task 4: Feature tests, controller, and route

**Files:**
- Create: `moodflow-api/tests/Feature/Api/MoodPredictionTest.php`
- Create: `moodflow-api/app/Http/Controllers/MoodPredictionController.php`
- Modify: `moodflow-api/routes/api.php`

- [ ] **Step 1: Write feature tests**

Create `moodflow-api/tests/Feature/Api/MoodPredictionTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Mood;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoodPredictionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function makeMood(User $user, array $attrs): void
    {
        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $mood = Mood::create(array_merge([
            'user_id'   => $user->id,
            'source'    => 'slider',
            'emotion'   => 'calm',
            'energy'    => 60,
            'valence'   => 0.3,
            'intensity' => 50,
            'context'   => ['genres' => [], 'activity_mode' => null, 'weather' => null],
            'tracks'    => [],
        ], $attrs));

        if ($createdAt !== null) {
            DB::table('moods')->where('id', $mood->id)->update(['created_at' => $createdAt]);
        }
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/mood/predict')->assertStatus(401);
    }

    public function test_returns_fallback_structure_when_no_history(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/mood/predict')
            ->assertStatus(200)
            ->assertJsonStructure([
                'predicted_mood' => ['energy', 'valence', 'intensity'],
                'confidence',
                'based_on',
                'suggested_activity',
                'message',
            ])
            ->assertJson(['confidence' => 0.0, 'based_on' => 0]);
    }

    public function test_returns_prediction_with_sufficient_history(): void
    {
        $base = Carbon::parse('2026-05-19 10:00:00'); // Tuesday
        for ($i = 0; $i < 5; $i++) {
            $this->makeMood($this->user, ['energy' => 70, 'valence' => 0.5, 'intensity' => 65,
                'created_at' => $base->copy()->subWeeks($i)]);
        }

        $this->travelTo(Carbon::parse('2026-05-26 10:00:00')); // next Tuesday 10h

        $this->actingAs($this->user)
            ->getJson('/api/mood/predict')
            ->assertStatus(200)
            ->assertJsonPath('confidence',            0.5)
            ->assertJsonPath('based_on',              5)
            ->assertJsonPath('predicted_mood.energy', 70);
    }

    public function test_accepts_lat_lon_without_error(): void
    {
        // WeatherService returns null when API key absent — endpoint still 200
        $this->actingAs($this->user)
            ->getJson('/api/mood/predict?lat=48.8566&lon=2.3522')
            ->assertStatus(200)
            ->assertJsonStructure(['predicted_mood', 'confidence', 'based_on', 'suggested_activity', 'message']);
    }

    public function test_validates_lat_out_of_range(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/mood/predict?lat=999&lon=2.3522')
            ->assertStatus(422);
    }

    public function test_validates_lon_out_of_range(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/mood/predict?lat=48.8566&lon=999')
            ->assertStatus(422);
    }

    public function test_only_uses_own_moods(): void
    {
        $other = User::factory()->create();
        $base  = Carbon::parse('2026-05-19 10:00:00');
        for ($i = 0; $i < 10; $i++) {
            $this->makeMood($other, ['energy' => 90, 'valence' => 0.9, 'intensity' => 90,
                'created_at' => $base->copy()->subWeeks($i)]);
        }

        $this->travelTo(Carbon::parse('2026-05-26 10:00:00'));

        $this->actingAs($this->user)
            ->getJson('/api/mood/predict')
            ->assertStatus(200)
            ->assertJson(['confidence' => 0.0, 'based_on' => 0]);
    }
}
```

- [ ] **Step 2: Run feature tests — verify 404**

```bash
cd moodflow-api && php artisan test tests/Feature/Api/MoodPredictionTest.php 2>&1
```

Expected: FAIL with `404 Not Found` (route missing)

- [ ] **Step 3: Create controller**

Create `moodflow-api/app/Http/Controllers/MoodPredictionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\MoodPredictionService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodPredictionController extends Controller
{
    public function __construct(
        private readonly MoodPredictionService $predictionService,
        private readonly WeatherService $weatherService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'sometimes|numeric|between:-90,90',
            'lon' => 'sometimes|numeric|between:-180,180',
        ]);

        $weather = null;
        if ($request->filled('lat') && $request->filled('lon')) {
            $data    = $this->weatherService->getWeather((float) $request->lat, (float) $request->lon);
            $weather = $data['condition'] ?? null;
        }

        return response()->json(
            $this->predictionService->predict($request->user(), Carbon::now(), $weather)
        );
    }
}
```

- [ ] **Step 4: Add route to api.php**

Add import at the top of `moodflow-api/routes/api.php` with the existing imports:

```php
use App\Http\Controllers\MoodPredictionController;
```

Inside the `auth:sanctum` group, alongside `Route::get('/mood/dna', MoodDnaController::class);`:

```php
Route::get('/mood/predict', MoodPredictionController::class);
```

The auth:sanctum group section should look like:

```php
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users/me',        [UserController::class, 'me']);
    Route::put('/users/me/genres', [UserController::class, 'updateGenres']);

    Route::get('/mood/dna',     MoodDnaController::class);
    Route::get('/mood/predict', MoodPredictionController::class);

    Route::middleware('has.genres')->group(function (): void {
        Route::post('/mood/analyze', [MoodController::class, 'analyze']);
        Route::get('/mood/history',  [MoodController::class, 'history']);
        Route::get('/mood/stats',    [MoodController::class, 'stats']);
    });
});
```

- [ ] **Step 5: Run feature tests — verify they pass**

```bash
cd moodflow-api && php artisan test tests/Feature/Api/MoodPredictionTest.php 2>&1
```

Expected: All green

- [ ] **Step 6: Run full suite — verify no regressions**

```bash
cd moodflow-api && php artisan test 2>&1
```

Expected: All existing + new tests pass

- [ ] **Step 7: Commit**

```bash
git add moodflow-api/app/Http/Controllers/MoodPredictionController.php \
        moodflow-api/tests/Feature/Api/MoodPredictionTest.php \
        moodflow-api/routes/api.php
git commit -m "feat: add GET /api/mood/predict endpoint"
```

---

## Self-Review

### Spec coverage

| Requirement | Task |
|---|---|
| `predict(User, Carbon, ?weather)` signature | Task 2 |
| Filter same day ±1 | Task 1 + 2 |
| Filter same hour ±2 | Task 1 + 2 |
| Filter same weather condition | Task 1 + 2 |
| Avg energy/valence/intensity | Task 1 + 2 |
| confidence 0–1, 0 if <3 matches | Task 1 + 2 |
| based_on count | Task 1 + 2 |
| suggested_activity (most frequent mode) | Task 1 + 2 |
| message FR contextuel | Task 3 |
| GET /api/mood/predict | Task 4 |
| Uses ContextService for time/day | Task 2 (fallback) |
| lat/lon → WeatherService | Task 4 |
| Fallback when confidence < 0.3 | Task 2 + 3 |

All requirements covered.

### Type consistency

- `predict()` returns identical shape in both success and fallback paths ✓
- `findSimilarMoods()` → `Collection`; consumed by `averageMood(Collection)` and `suggestedActivity(Collection)` ✓
- `computeConfidence(int)` receives `$similar->count()` (int) ✓
- `fallback()` produces same `$predicted` array shape fed to `buildMessage()` ✓
