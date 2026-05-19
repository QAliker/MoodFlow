# Activity Modes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Activity Modes to the recommendation pipeline — preconfigured mood anchors (focus, workout, sleep, etc.) that serve as a base, with the user's live mood values nudging the result by ±20%.

**Architecture:** A new `ActivityModeService` loads modes from `config/activity-modes.php`, merges them with user mood input using a ±20% offset formula, and injects the result into the existing `MoodService::recommend()` pipeline. A new `activity_mode_usages` table tracks which mode was used per user per request.

**Tech Stack:** Laravel 11, PHP 8.3, Eloquent, PHPUnit, PostgreSQL

---

## File Map

| Action | File |
|--------|------|
| Create | `moodflow-api/config/activity-modes.php` |
| Create | `moodflow-api/database/migrations/2026_05_19_000001_create_activity_mode_usages_table.php` |
| Create | `moodflow-api/app/Models/ActivityModeUsage.php` |
| Create | `moodflow-api/app/Services/ActivityModeService.php` |
| Modify | `moodflow-api/app/Http/Requests/AnalyzeMoodRequest.php` |
| Modify | `moodflow-api/app/Services/MoodService.php` |
| Modify | `moodflow-api/app/Http/Controllers/MoodController.php` |
| Create | `moodflow-api/app/Http/Controllers/ActivityModeController.php` |
| Modify | `moodflow-api/routes/api.php` |
| Create | `moodflow-api/tests/Unit/Services/ActivityModeServiceTest.php` |
| Create | `moodflow-api/tests/Feature/Api/ActivityModeTest.php` |

---

## Merge Formula

Mode = BASE. User mood = nudge (±20%).

```
energy_offset   = (userEnergy - 50) * 0.4        // maps 0–100 → -20..+20
intensity_offset = (userIntensity - 50) * 0.4
valence_offset  = userValence * 0.2               // already -1..1 → -0.2..+0.2

merged_energy   = clamp(modeEnergy   + energy_offset,   0,   100)
merged_intensity = clamp(modeIntensity + intensity_offset, 0, 100)
merged_valence  = clamp(modeValence  + valence_offset,  -1.0, 1.0)
```

Special case: mode has `uses_user_mood: true` → user values pass through as-is (commute mode).

---

## Task 1: Config File

**Files:**
- Create: `moodflow-api/config/activity-modes.php`

- [ ] **Step 1: Write the config**

```php
<?php

return [
    'focus' => [
        'slug'             => 'focus',
        'label'            => 'Concentration',
        'emoji'            => '🎯',
        'energy'           => 40,
        'valence'          => 0.2,
        'intensity'        => 30,
        'genres_boost'     => ['lo-fi', 'classique', 'jazz'],
        'keywords'         => ['study', 'focus', 'instrumental', 'concentration'],
        'filter_no_lyrics' => true,
    ],

    'sleep' => [
        'slug'             => 'sleep',
        'label'            => 'Sommeil',
        'emoji'            => '😴',
        'energy'           => 10,
        'valence'          => 0.1,
        'intensity'        => 10,
        'genres_boost'     => ['classique', 'jazz'],
        'keywords'         => ['sleep', 'ambient', 'calm', 'night'],
        'filter_no_lyrics' => true,
    ],

    'workout' => [
        'slug'         => 'workout',
        'label'        => 'Sport',
        'emoji'        => '💪',
        'energy'       => 90,
        'valence'      => 0.5,
        'intensity'    => 85,
        'genres_boost' => ['electro', 'rap', 'rock'],
        'keywords'     => ['workout', 'energy', 'pump', 'power'],
    ],

    'morning' => [
        'slug'         => 'morning',
        'label'        => 'Matin',
        'emoji'        => '🌅',
        'energy'       => 55,
        'valence'      => 0.6,
        'intensity'    => 45,
        'genres_boost' => ['pop', 'indie', 'soul/funk'],
        'keywords'     => ['morning', 'feel good', 'wake up', 'positive'],
    ],

    'destress' => [
        'slug'         => 'destress',
        'label'        => 'Décompression',
        'emoji'        => '🧘',
        'energy'       => 25,
        'valence'      => 0.3,
        'intensity'    => 20,
        'genres_boost' => ['lo-fi', 'jazz', 'classique'],
        'keywords'     => ['relax', 'chill', 'calm', 'peaceful'],
    ],

    'commute' => [
        'slug'           => 'commute',
        'label'          => 'Trajet',
        'emoji'          => '🚇',
        'energy'         => 50,
        'valence'        => 0.3,
        'intensity'      => 50,
        'genres_boost'   => [],
        'keywords'       => ['commute', 'drive', 'travel'],
        'uses_user_mood' => true,
    ],

    'motivation' => [
        'slug'         => 'motivation',
        'label'        => 'Motivation',
        'emoji'        => '🚀',
        'energy'       => 80,
        'valence'      => 0.7,
        'intensity'    => 75,
        'genres_boost' => ['rap', 'rock', 'electro'],
        'keywords'     => ['motivation', 'inspire', 'power', 'boss'],
    ],

    'soiree' => [
        'slug'         => 'soiree',
        'label'        => 'Soirée',
        'emoji'        => '🎉',
        'energy'       => 85,
        'valence'      => 0.8,
        'intensity'    => 80,
        'genres_boost' => ['electro', 'reggaeton', 'rap', 'afrobeats'],
        'keywords'     => ['party', 'dance', 'club', 'friday'],
    ],

    'meditation' => [
        'slug'             => 'meditation',
        'label'            => 'Méditation',
        'emoji'            => '☯️',
        'energy'           => 5,
        'valence'          => 0.2,
        'intensity'        => 5,
        'genres_boost'     => [],
        'keywords'         => ['meditation', 'zen', 'breathing', 'mindfulness'],
        'filter_no_lyrics' => true,
    ],

    'cuisiner' => [
        'slug'         => 'cuisiner',
        'label'        => 'Cuisine',
        'emoji'        => '👨‍🍳',
        'energy'       => 50,
        'valence'      => 0.5,
        'intensity'    => 40,
        'genres_boost' => ['jazz', 'soul/funk', 'pop'],
        'keywords'     => ['cooking', 'chill', 'groove', 'kitchen'],
    ],

    'rainy_day' => [
        'slug'         => 'rainy_day',
        'label'        => 'Jour de pluie',
        'emoji'        => '🌧️',
        'energy'       => 30,
        'valence'      => -0.1,
        'intensity'    => 40,
        'genres_boost' => ['indie', 'jazz', 'classique'],
        'keywords'     => ['rain', 'melancholic', 'cozy', 'introspective'],
        'auto_trigger' => 'weather:rain',
    ],
];
```

- [ ] **Step 2: Verify PHP syntax**

```bash
cd moodflow-api && php -l config/activity-modes.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/config/activity-modes.php
git commit -m "feat: add activity modes config (11 modes)"
```

---

## Task 2: Migration — activity_mode_usages

**Files:**
- Create: `moodflow-api/database/migrations/2026_05_19_000001_create_activity_mode_usages_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_mode_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode_slug', 50);
            $table->timestamp('used_at')->useCurrent();

            $table->index(['user_id', 'used_at']);
            $table->index('mode_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_mode_usages');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
docker compose run --rm api php artisan migrate
```
Expected: output includes `activity_mode_usages`

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/database/migrations/2026_05_19_000001_create_activity_mode_usages_table.php
git commit -m "feat: add activity_mode_usages migration"
```

---

## Task 3: Model — ActivityModeUsage

**Files:**
- Create: `moodflow-api/app/Models/ActivityModeUsage.php`

- [ ] **Step 1: Write test first**

Create `moodflow-api/tests/Unit/Models/ActivityModeUsageTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ActivityModeUsage;
use PHPUnit\Framework\TestCase;

class ActivityModeUsageTest extends TestCase
{
    public function test_fillable_fields(): void
    {
        $model = new ActivityModeUsage;

        $this->assertEquals(['user_id', 'mode_slug', 'used_at'], $model->getFillable());
    }

    public function test_timestamps_disabled(): void
    {
        $model = new ActivityModeUsage;

        $this->assertFalse($model->timestamps);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose run --rm api php artisan test tests/Unit/Models/ActivityModeUsageTest.php
```
Expected: FAIL — class not found

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityModeUsage extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'mode_slug', 'used_at'];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker compose run --rm api php artisan test tests/Unit/Models/ActivityModeUsageTest.php
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add moodflow-api/app/Models/ActivityModeUsage.php moodflow-api/tests/Unit/Models/ActivityModeUsageTest.php
git commit -m "feat: add ActivityModeUsage model"
```

---

## Task 4: ActivityModeService

**Files:**
- Create: `moodflow-api/app/Services/ActivityModeService.php`
- Create: `moodflow-api/tests/Unit/Services/ActivityModeServiceTest.php`

The service exposes three public methods:
- `all(): array` — all 11 modes from config
- `get(string $slug): array` — single mode, throws `InvalidArgumentException` if not found
- `merge(array $moodInput, string $modeSlug): array` — returns moodInput with mode applied

The `merge()` output adds these keys to the original moodInput array:
- `energy`, `valence`, `intensity` — merged values (float/int)
- `mode_keywords` — array of keywords from mode config
- `mode_genres_boost` — array of genre strings from mode config
- `filter_no_lyrics` — bool (default false)
- `activity_mode` — the slug

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\ActivityModeService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ActivityModeServiceTest extends TestCase
{
    private ActivityModeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityModeService;
    }

    public function test_all_returns_11_modes(): void
    {
        $modes = $this->service->all();

        $this->assertCount(11, $modes);
        $this->assertArrayHasKey('focus', $modes);
        $this->assertArrayHasKey('rainy_day', $modes);
    }

    public function test_get_returns_mode_by_slug(): void
    {
        $mode = $this->service->get('focus');

        $this->assertEquals('focus', $mode['slug']);
        $this->assertEquals(40, $mode['energy']);
        $this->assertEquals(0.2, $mode['valence']);
        $this->assertTrue($mode['filter_no_lyrics']);
    }

    public function test_get_throws_for_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown activity mode: unknown_slug');

        $this->service->get('unknown_slug');
    }

    public function test_merge_uses_mode_as_base_neutral_user_mood(): void
    {
        // User mood is perfectly neutral (50 energy, 0 valence, 50 intensity)
        // Offsets = 0, so merged values = mode base exactly
        $moodInput = [
            'source'    => 'emoji',
            'energy'    => 50,
            'valence'   => 0.0,
            'intensity' => 50,
        ];

        $merged = $this->service->merge($moodInput, 'focus');

        $this->assertEquals(40, $merged['energy']);   // focus base = 40
        $this->assertEqualsWithDelta(0.2, $merged['valence'], 0.001);
        $this->assertEquals(30, $merged['intensity']); // focus base = 30
        $this->assertEquals('focus', $merged['activity_mode']);
        $this->assertEquals(['study', 'focus', 'instrumental', 'concentration'], $merged['mode_keywords']);
        $this->assertEquals(['lo-fi', 'classique', 'jazz'], $merged['mode_genres_boost']);
        $this->assertTrue($merged['filter_no_lyrics']);
    }

    public function test_merge_applies_user_energy_offset(): void
    {
        // User energy = 100 → offset = (100 - 50) * 0.4 = +20
        // focus base = 40 → merged = 60
        $moodInput = ['source' => 'emoji', 'energy' => 100, 'valence' => 0.0, 'intensity' => 50];

        $merged = $this->service->merge($moodInput, 'focus');

        $this->assertEquals(60, $merged['energy']);
    }

    public function test_merge_clamps_energy_to_100(): void
    {
        // workout base = 90, user energy = 100 → offset = +20 → 90+20 = 110 → clamped to 100
        $moodInput = ['source' => 'emoji', 'energy' => 100, 'valence' => 0.0, 'intensity' => 50];

        $merged = $this->service->merge($moodInput, 'workout');

        $this->assertEquals(100, $merged['energy']);
    }

    public function test_merge_clamps_energy_to_0(): void
    {
        // sleep base = 10, user energy = 0 → offset = -20 → 10-20 = -10 → clamped to 0
        $moodInput = ['source' => 'emoji', 'energy' => 0, 'valence' => 0.0, 'intensity' => 50];

        $merged = $this->service->merge($moodInput, 'sleep');

        $this->assertEquals(0, $merged['energy']);
    }

    public function test_merge_applies_valence_offset(): void
    {
        // focus base valence = 0.2, user valence = 1.0 → offset = 0.2 → merged = 0.4
        $moodInput = ['source' => 'emoji', 'energy' => 50, 'valence' => 1.0, 'intensity' => 50];

        $merged = $this->service->merge($moodInput, 'focus');

        $this->assertEqualsWithDelta(0.4, $merged['valence'], 0.001);
    }

    public function test_merge_uses_user_mood_when_flag_set(): void
    {
        // commute has uses_user_mood: true → user values pass through unchanged
        $moodInput = ['source' => 'emoji', 'energy' => 80, 'valence' => 0.7, 'intensity' => 75];

        $merged = $this->service->merge($moodInput, 'commute');

        $this->assertEquals(80, $merged['energy']);
        $this->assertEqualsWithDelta(0.7, $merged['valence'], 0.001);
        $this->assertEquals(75, $merged['intensity']);
        // Still adds mode metadata
        $this->assertEquals('commute', $merged['activity_mode']);
        $this->assertEquals(['commute', 'drive', 'travel'], $merged['mode_keywords']);
    }

    public function test_merge_filter_no_lyrics_defaults_false(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 50, 'valence' => 0.0, 'intensity' => 50];

        $merged = $this->service->merge($moodInput, 'workout');

        $this->assertFalse($merged['filter_no_lyrics']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose run --rm api php artisan test tests/Unit/Services/ActivityModeServiceTest.php
```
Expected: FAIL — class not found

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use InvalidArgumentException;

class ActivityModeService
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return config('activity-modes');
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        $mode = config("activity-modes.{$slug}");

        if ($mode === null) {
            throw new InvalidArgumentException("Unknown activity mode: {$slug}");
        }

        return $mode;
    }

    /**
     * Merge mode params with user mood. Mode = BASE, user mood nudges ±20%.
     *
     * @param  array{source: string, energy: int, valence: float, intensity: int}  $moodInput
     * @return array<string, mixed>
     */
    public function merge(array $moodInput, string $modeSlug): array
    {
        $mode = $this->get($modeSlug);

        if ($mode['uses_user_mood'] ?? false) {
            $energy    = $moodInput['energy'];
            $valence   = $moodInput['valence'];
            $intensity = $moodInput['intensity'];
        } else {
            $energyOffset    = ($moodInput['energy'] - 50) * 0.4;
            $intensityOffset = ($moodInput['intensity'] - 50) * 0.4;
            $valenceOffset   = $moodInput['valence'] * 0.2;

            $energy    = (int) round(max(0, min(100, $mode['energy'] + $energyOffset)));
            $intensity = (int) round(max(0, min(100, $mode['intensity'] + $intensityOffset)));
            $valence   = round(max(-1.0, min(1.0, $mode['valence'] + $valenceOffset)), 4);
        }

        return array_merge($moodInput, [
            'energy'            => $energy,
            'valence'           => $valence,
            'intensity'         => $intensity,
            'activity_mode'     => $modeSlug,
            'mode_keywords'     => $mode['keywords'],
            'mode_genres_boost' => $mode['genres_boost'],
            'filter_no_lyrics'  => $mode['filter_no_lyrics'] ?? false,
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose run --rm api php artisan test tests/Unit/Services/ActivityModeServiceTest.php
```
Expected: all PASS

- [ ] **Step 5: Commit**

```bash
git add moodflow-api/app/Services/ActivityModeService.php moodflow-api/tests/Unit/Services/ActivityModeServiceTest.php
git commit -m "feat: add ActivityModeService with merge formula"
```

---

## Task 5: AnalyzeMoodRequest — add activity_mode

**Files:**
- Modify: `moodflow-api/app/Http/Requests/AnalyzeMoodRequest.php`

- [ ] **Step 1: Update validation rules**

The valid slugs are the 11 mode keys. Add the `activity_mode` field as optional, nullable, validated against the config keys.

Replace the `rules()` method body:

```php
/** @return array<string, mixed> */
public function rules(): array
{
    $validModes = implode(',', array_keys(config('activity-modes')));

    return [
        'source'        => ['required', 'string', 'in:emoji,slider,passive'],
        'emotion'       => ['sometimes', 'nullable', 'string', 'max:50'],
        'energy'        => ['required', 'integer', 'between:0,100'],
        'valence'       => ['required', 'numeric', 'between:-1,1'],
        'intensity'     => ['required', 'integer', 'between:0,100'],
        'lat'           => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
        'lon'           => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        'activity_mode' => ['sometimes', 'nullable', 'string', "in:{$validModes}"],
    ];
}
```

- [ ] **Step 2: Verify syntax**

```bash
cd moodflow-api && php -l app/Http/Requests/AnalyzeMoodRequest.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/app/Http/Requests/AnalyzeMoodRequest.php
git commit -m "feat: add activity_mode to AnalyzeMoodRequest validation"
```

---

## Task 6: MoodService — apply activity mode

**Files:**
- Modify: `moodflow-api/app/Services/MoodService.php`

Changes:
1. Inject `ActivityModeService` in constructor
2. In `recommend()`: if `moodInput['activity_mode']` is set, call `ActivityModeService::merge()` before context modifier
3. Use `mode_keywords` as primary/secondary search terms when mode is active
4. Include `mode_genres_boost` in the track fetch step
5. Append `"instrumental"` to the primary keyword when `filter_no_lyrics` is true
6. Record usage in `activity_mode_usages` after saving mood
7. Store `activity_mode` in `Mood.context`

- [ ] **Step 1: Update the service**

Full updated `MoodService.php`:

```php
<?php

namespace App\Services;

use App\Models\ActivityModeUsage;
use App\Models\Mood;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MoodService
{
    public function __construct(
        private readonly DeezerService $deezer,
        private readonly ContextService $context,
        private readonly ActivityModeService $activityMode,
    ) {}

    /**
     * @param  array{source: string, energy: int, valence: float, intensity: int, weather?: string|null, activity_mode?: string|null}  $moodInput
     * @return array{mood: Mood, tracks: array<int, array<string, mixed>>}
     */
    public function recommend(User $user, array $moodInput): array
    {
        // 1. Apply activity mode (merges mode base with user mood ±20%)
        $modeSlug = $moodInput['activity_mode'] ?? null;
        if ($modeSlug !== null) {
            $moodInput = $this->activityMode->merge($moodInput, $modeSlug);
        }

        // 2. Apply passive context modifier
        $modifier = $this->context->getModifier(
            Carbon::now(),
            $moodInput['weather'] ?? null,
        );

        $adjusted = $this->applyModifier($moodInput, $modifier);

        // 3. Resolve search keywords
        [$label, $primary, $secondary] = $this->resolveKeywords($adjusted['energy'], $adjusted['valence'], $moodInput);

        // 4. Fetch, deduplicate and shuffle tracks
        $user->loadMissing('genres');
        $tracks = $this->fetchTracks(
            $user->genres,
            $primary,
            $secondary,
            $moodInput['mode_genres_boost'] ?? [],
        );

        // 5. Persist mood
        $mood = $this->saveMood($user, $moodInput, $adjusted, $modifier, $label, $tracks, $modeSlug);

        // 6. Log activity mode usage
        if ($modeSlug !== null) {
            ActivityModeUsage::create([
                'user_id'   => $user->id,
                'mode_slug' => $modeSlug,
                'used_at'   => Carbon::now(),
            ]);
        }

        return ['mood' => $mood, 'tracks' => $tracks];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array{energy: int, valence: float, intensity: int}  $moodInput
     * @param  array{energy_mod: int, valence_mod: int, intensity_mod: int}  $modifier
     * @return array{energy: int, valence: float, intensity: int}
     */
    private function applyModifier(array $moodInput, array $modifier): array
    {
        return [
            'energy'    => (int) max(0, min(100, $moodInput['energy'] + $modifier['energy_mod'])),
            'valence'   => (float) max(-1.0, min(1.0, $moodInput['valence'] + $modifier['valence_mod'] / 100)),
            'intensity' => (int) max(0, min(100, $moodInput['intensity'] + $modifier['intensity_mod'])),
        ];
    }

    /**
     * When an activity mode is active, use its first two keywords as primary/secondary.
     * Otherwise fall back to emotion-based keyword selection.
     *
     * @return array{string, string, string}
     */
    private function resolveKeywords(float $energy, float $valence, array $moodInput): array
    {
        if (isset($moodInput['mode_keywords']) && count($moodInput['mode_keywords']) >= 2) {
            $primary   = $moodInput['mode_keywords'][0];
            $secondary = $moodInput['mode_keywords'][1];

            if ($moodInput['filter_no_lyrics'] ?? false) {
                $primary .= ' instrumental';
            }

            [$label] = $this->selectKeywords($energy, $valence);

            return [$label, $primary, $secondary];
        }

        return $this->selectKeywords($energy, $valence);
    }

    /**
     * Return [emotionLabel, primaryKeyword, secondaryKeyword] based on adjusted mood values.
     *
     * @return array{string, string, string}
     */
    private function selectKeywords(float $energy, float $valence): array
    {
        return match (true) {
            $energy > 70 && $valence > 0.3  => ['energetic',   'party',         'energy'],
            $energy < 30 && $valence < -0.3 => ['melancholic', 'sad',           'melancholic'],
            $energy < 30 && $valence > 0.3  => ['calm',        'chill',         'relax'],
            $energy > 70 && $valence < -0.3 => ['intense',     'rage',          'dark'],
            $energy > 70                    => ['upbeat',       'dance',         'upbeat'],
            $energy < 30                    => ['ambient',      'lofi',          'ambient'],
            $valence > 0.3                  => ['happy',        'happy',         'feel good'],
            $valence < -0.3                 => ['reflective',   'blues',         'introspective'],
            default                         => ['neutral',      'pop',           'chill'],
        };
    }

    /**
     * Fetch tracks for user genres + optional boost genres.
     *
     * @param  Collection<int, \App\Models\Genre>  $genres
     * @param  array<int, string>  $boostGenres
     * @return array<int, array<string, mixed>>
     */
    private function fetchTracks(Collection $genres, string $primary, string $secondary, array $boostGenres = []): array
    {
        if ($genres->isEmpty() && empty($boostGenres)) {
            return [];
        }

        $tracks = $this->searchByKeyword($genres, $primary, $boostGenres);

        if (count($tracks) < 15) {
            $second = $this->searchByKeyword($genres, $secondary, $boostGenres);
            $tracks = $this->mergeAndDedup($tracks, $second);
        }

        shuffle($tracks);

        return array_slice($tracks, 0, 20);
    }

    /**
     * @param  Collection<int, \App\Models\Genre>  $genres
     * @param  array<int, string>  $boostGenres
     * @return array<int, array<string, mixed>>
     */
    private function searchByKeyword(Collection $genres, string $keyword, array $boostGenres = []): array
    {
        $results = [];

        foreach ($genres as $genre) {
            $found   = $this->deezer->searchTracks("{$keyword} {$genre->name}", 10);
            $results = $this->mergeAndDedup($results, $found);
        }

        foreach ($boostGenres as $boostGenre) {
            $found   = $this->deezer->searchTracks("{$keyword} {$boostGenre}", 10);
            $results = $this->mergeAndDedup($results, $found);
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $base
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    private function mergeAndDedup(array $base, array $incoming): array
    {
        $indexed = [];

        foreach ($base as $track) {
            $indexed[$track['deezer_id']] = $track;
        }

        foreach ($incoming as $track) {
            $indexed[$track['deezer_id']] = $track;
        }

        return array_values($indexed);
    }

    private function saveMood(
        User $user,
        array $moodInput,
        array $adjusted,
        array $modifier,
        string $label,
        array $tracks,
        ?string $modeSlug,
    ): Mood {
        return Mood::create([
            'user_id'   => $user->id,
            'source'    => $moodInput['source'],
            'emotion'   => $label,
            'energy'    => $moodInput['energy'],
            'valence'   => $moodInput['valence'],
            'intensity' => $moodInput['intensity'],
            'context'   => [
                'weather'       => $moodInput['weather'] ?? null,
                'modifier'      => $modifier,
                'adjusted'      => $adjusted,
                'activity_mode' => $modeSlug,
            ],
            'tracks'    => $tracks,
        ]);
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
cd moodflow-api && php -l app/Services/MoodService.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/app/Services/MoodService.php
git commit -m "feat: apply activity mode in MoodService recommendation pipeline"
```

---

## Task 7: MoodController — pass activity_mode

**Files:**
- Modify: `moodflow-api/app/Http/Controllers/MoodController.php`

Only change: add `activity_mode` to the `$moodInput` array built in `analyze()`.

- [ ] **Step 1: Update the controller**

```php
$moodInput = [
    'source'        => $data['source'],
    'emotion'       => $data['emotion'] ?? null,
    'energy'        => $data['energy'],
    'valence'       => $data['valence'],
    'intensity'     => $data['intensity'],
    'weather'       => $weather,
    'activity_mode' => $data['activity_mode'] ?? null,
];
```

Replace the `$moodInput` block in `analyze()` (the full method stays the same, only this array changes).

- [ ] **Step 2: Verify syntax**

```bash
cd moodflow-api && php -l app/Http/Controllers/MoodController.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add moodflow-api/app/Http/Controllers/MoodController.php
git commit -m "feat: forward activity_mode from request to MoodService"
```

---

## Task 8: ActivityModeController + Route

**Files:**
- Create: `moodflow-api/app/Http/Controllers/ActivityModeController.php`
- Modify: `moodflow-api/routes/api.php`

Single endpoint `GET /api/activity-modes` returns all 11 modes. No auth required (public catalogue).

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Services\ActivityModeService;
use Illuminate\Http\JsonResponse;

class ActivityModeController extends Controller
{
    public function __construct(private readonly ActivityModeService $activityMode) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_values($this->activityMode->all()),
        ]);
    }
}
```

- [ ] **Step 2: Register the route**

In `routes/api.php`, add after the `/genres` route:

```php
Route::get('/activity-modes', [ActivityModeController::class, 'index']);
```

And add the import at the top:

```php
use App\Http\Controllers\ActivityModeController;
```

- [ ] **Step 3: Verify route is registered**

```bash
docker compose run --rm api php artisan route:list --path=activity-modes
```
Expected: one GET route for `/api/activity-modes`

- [ ] **Step 4: Commit**

```bash
git add moodflow-api/app/Http/Controllers/ActivityModeController.php moodflow-api/routes/api.php
git commit -m "feat: add GET /api/activity-modes endpoint"
```

---

## Task 9: Feature Tests

**Files:**
- Create: `moodflow-api/tests/Feature/Api/ActivityModeTest.php`

Tests cover: listing modes, analyze with mode, analyze without mode (regression).

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ActivityModeUsage;
use App\Models\Genre;
use App\Models\User;
use App\Services\DeezerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_activity_modes_returns_11_modes(): void
    {
        $response = $this->getJson('/api/activity-modes');

        $response->assertOk()
            ->assertJsonCount(11, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'label', 'emoji', 'energy', 'valence', 'intensity', 'keywords'],
                ],
            ]);
    }

    public function test_list_modes_is_public(): void
    {
        $response = $this->getJson('/api/activity-modes');

        $response->assertOk();
    }

    public function test_analyze_with_activity_mode_logs_usage(): void
    {
        $this->mockDeezer();

        $user = $this->userWithGenre();

        $response = $this->actingAs($user)->postJson('/api/mood/analyze', [
            'source'        => 'emoji',
            'energy'        => 50,
            'valence'       => 0.0,
            'intensity'     => 50,
            'activity_mode' => 'focus',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('activity_mode_usages', [
            'user_id'   => $user->id,
            'mode_slug' => 'focus',
        ]);
    }

    public function test_analyze_with_activity_mode_stores_mode_in_context(): void
    {
        $this->mockDeezer();

        $user = $this->userWithGenre();

        $response = $this->actingAs($user)->postJson('/api/mood/analyze', [
            'source'        => 'emoji',
            'energy'        => 50,
            'valence'       => 0.0,
            'intensity'     => 50,
            'activity_mode' => 'workout',
        ]);

        $response->assertCreated();
        $mood = $response->json('data.mood');
        $this->assertEquals('workout', $mood['context']['activity_mode']);
    }

    public function test_analyze_without_activity_mode_does_not_log_usage(): void
    {
        $this->mockDeezer();

        $user = $this->userWithGenre();

        $this->actingAs($user)->postJson('/api/mood/analyze', [
            'source'    => 'emoji',
            'energy'    => 50,
            'valence'   => 0.0,
            'intensity' => 50,
        ])->assertCreated();

        $this->assertDatabaseCount('activity_mode_usages', 0);
    }

    public function test_analyze_with_invalid_mode_returns_422(): void
    {
        $user = $this->userWithGenre();

        $this->actingAs($user)->postJson('/api/mood/analyze', [
            'source'        => 'emoji',
            'energy'        => 50,
            'valence'       => 0.0,
            'intensity'     => 50,
            'activity_mode' => 'does_not_exist',
        ])->assertUnprocessable();
    }

    // -------------------------------------------------------------------------

    private function mockDeezer(): void
    {
        $this->mock(DeezerService::class, function ($mock): void {
            $mock->shouldReceive('searchTracks')->andReturn([
                ['deezer_id' => 1, 'title' => 'Track A', 'artist' => 'Artist A', 'preview' => 'http://x', 'cover' => 'http://x'],
            ]);
        });
    }

    private function userWithGenre(): User
    {
        $user  = User::factory()->create();
        $genre = Genre::create(['name' => 'pop', 'deezer_id' => 132]);
        $user->genres()->attach($genre->id);

        return $user;
    }
}
```

- [ ] **Step 2: Run tests (expect some failures — Deezer mock may need adjusting)**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/ActivityModeTest.php
```
Expected: all PASS. If `GenreSeeder` already ran and `Genre` factory is needed, adjust `userWithGenre()`.

- [ ] **Step 3: Run full test suite to check for regressions**

```bash
docker compose run --rm api php artisan test
```
Expected: all PASS

- [ ] **Step 4: Commit**

```bash
git add moodflow-api/tests/Feature/Api/ActivityModeTest.php
git commit -m "test: add feature tests for Activity Modes endpoint and analyze integration"
```

---

## Self-Review

### Spec Coverage

| Requirement | Task |
|-------------|------|
| `config/activity-modes.php` with 11 modes | Task 1 |
| Each mode: slug, label FR, emoji, params | Task 1 |
| `filter_no_lyrics` modes | Task 1 |
| `uses_user_mood` commute mode | Task 1 (config) + Task 4 (merge logic) |
| `auto_trigger` rainy_day | Task 1 (stored in config, not yet consumed — future) |
| `?activity_mode=focus` optional param | Task 5 |
| Merge: mode = BASE, user mood ±20% | Task 4 |
| `activity_mode_usages` migration | Task 2 |
| Model + relationship | Task 3 |
| Mode applied before context modifier | Task 6 (step order in `recommend()`) |
| genres_boost used in track fetch | Task 6 (`fetchTracks` + `searchByKeyword`) |
| `filter_no_lyrics` appends `instrumental` keyword | Task 6 (`resolveKeywords`) |

### No Placeholders ✓

All steps contain exact code. No TBD, no "similar to above".

### Type Consistency ✓

- `ActivityModeService::merge()` → returns `array` with keys `mode_keywords`, `mode_genres_boost`, `filter_no_lyrics`, `activity_mode`
- `MoodService::recommend()` reads those exact keys
- `MoodService::fetchTracks()` signature `(Collection, string, string, array)` matches all call sites
- `resolveKeywords()` reads `$moodInput['mode_keywords']`, `$moodInput['filter_no_lyrics']` — consistent with merge output
