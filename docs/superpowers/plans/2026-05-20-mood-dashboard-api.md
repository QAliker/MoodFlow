# Mood Dashboard API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `GET /api/mood/history` (period filter) and `GET /api/mood/stats` (aggregated metrics) endpoints for the Mood Dashboard.

**Architecture:** Thin controller delegates to a new `MoodDashboardService` for stats computation. Query Scopes on `Mood` model handle period filtering. Genres used per mood are stored in the `context` JSON column (no migration needed). History response strips track URLs, returning only title + artist.

**Tech Stack:** Laravel 11, PHP 8.3, Eloquent Collections, Carbon, PHPUnit (Feature tests), RefreshDatabase.

---

## File Map

| Action | File |
|--------|------|
| Modify | `app/Models/Mood.php` — add `scopeForPeriod` |
| Modify | `app/Services/MoodService.php` — pass & store genre names in context |
| Create | `app/Http/Requests/MoodHistoryRequest.php` |
| Create | `app/Http/Requests/MoodStatsRequest.php` |
| Create | `app/Services/MoodDashboardService.php` |
| Modify | `app/Http/Controllers/MoodController.php` — update `history()`, add `stats()` |
| Modify | `routes/api.php` — add `GET /api/mood/stats` route |
| Create | `tests/Feature/Api/MoodDashboardTest.php` |

---

### Task 1: Query Scope on Mood model

**Files:**
- Modify: `app/Models/Mood.php`
- Test: `tests/Feature/Api/MoodDashboardTest.php`

- [ ] **Step 1: Write failing test for period scope**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Genre;
use App\Models\Mood;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoodDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_period_week_filters_old_moods(): void
    {
        $user = User::factory()->create();

        $this->createMood($user, ['created_at' => now()->subDays(3)]);
        $this->createMood($user, ['created_at' => now()->subDays(10)]);

        $response = $this->actingAs($user)->getJson('/api/mood/history?period=week');

        $response->assertOk()->assertJsonCount(1, 'data.data');
    }

    // helpers
    private function createMood(User $user, array $override = []): Mood
    {
        return Mood::create(array_merge([
            'user_id'   => $user->id,
            'source'    => 'emoji',
            'emotion'   => 'happy',
            'energy'    => 70,
            'valence'   => 0.5,
            'intensity' => 60,
            'context'   => ['weather' => null, 'activity_mode' => null, 'genres' => ['pop']],
            'tracks'    => [['title' => 'Song', 'artist' => 'Artist A', 'deezer_id' => 1]],
        ], $override));
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter test_history_period_week_filters_old_moods
```

Expected: FAIL — `scopeForPeriod` method not found.

- [ ] **Step 3: Add Query Scope to Mood model**

Replace `app/Models/Mood.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Mood extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'emotion',
        'energy',
        'valence',
        'intensity',
        'context',
        'tracks',
    ];

    protected $casts = [
        'context' => 'array',
        'tracks'  => 'array',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return match ($period) {
            'week'  => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            default => $query,
        };
    }
}
```

- [ ] **Step 4: Run test — must pass**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter test_history_period_week_filters_old_moods
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Mood.php tests/Feature/Api/MoodDashboardTest.php
git commit -m "feat(mood): add scopeForPeriod query scope on Mood model"
```

---

### Task 2: Store genre names in mood context

**Files:**
- Modify: `app/Services/MoodService.php`

The `context` JSON column already stores weather, modifier, adjusted, activity_mode. Add `genres` (array of genre name strings). No migration needed.

- [ ] **Step 1: Write failing test for genre storage**

Add to `MoodDashboardTest`:

```php
public function test_analyze_stores_genres_in_context(): void
{
    $this->mock(\App\Services\DeezerService::class, function ($mock): void {
        $mock->shouldReceive('searchTracks')->andReturn([]);
    });

    $user  = User::factory()->create();
    $genre = Genre::create(['name' => 'pop', 'slug' => 'pop', 'deezer_id' => 132]);
    $user->genres()->attach($genre->id);

    $this->actingAs($user)->postJson('/api/mood/analyze', [
        'source'    => 'emoji',
        'energy'    => 50,
        'valence'   => 0.0,
        'intensity' => 50,
    ])->assertCreated();

    $mood = \App\Models\Mood::first();
    $this->assertContains('pop', $mood->context['genres']);
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter test_analyze_stores_genres_in_context
```

Expected: FAIL — `genres` key missing from context.

- [ ] **Step 3: Modify MoodService to pass and store genre names**

In `app/Services/MoodService.php`, update `recommend()` to extract genre names and pass them to `saveMood()`:

```php
public function recommend(User $user, array $moodInput): array
{
    $modeSlug = $moodInput['activity_mode'] ?? null;
    if ($modeSlug !== null) {
        $moodInput = $this->activityMode->merge($moodInput, $modeSlug);
    }

    $modifier = $this->context->getModifier(
        Carbon::now(),
        $moodInput['weather'] ?? null,
    );

    $adjusted = $this->applyModifier($moodInput, $modifier);

    [$label, $primary, $secondary] = $this->resolveKeywords($adjusted['energy'], $adjusted['valence'], $moodInput);

    $user->loadMissing('genres');
    $genreNames = $user->genres->pluck('name')->all();

    $tracks = $this->fetchTracks(
        $user->genres,
        $primary,
        $secondary,
        $moodInput['mode_genres_boost'] ?? [],
    );

    $mood = $this->saveMood($user, $moodInput, $adjusted, $modifier, $label, $tracks, $modeSlug, $genreNames);

    if ($modeSlug !== null) {
        ActivityModeUsage::create([
            'user_id'   => $user->id,
            'mode_slug' => $modeSlug,
            'used_at'   => Carbon::now(),
        ]);
    }

    return ['mood' => $mood, 'tracks' => $tracks];
}
```

Update `saveMood()` signature and body to include genres:

```php
private function saveMood(
    User $user,
    array $moodInput,
    array $adjusted,
    array $modifier,
    string $label,
    array $tracks,
    ?string $modeSlug,
    array $genreNames = [],
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
            'genres'        => $genreNames,
        ],
        'tracks'    => $tracks,
    ]);
}
```

- [ ] **Step 4: Run test — must pass**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter test_analyze_stores_genres_in_context
```

Expected: PASS.

- [ ] **Step 5: Confirm existing activity mode tests still pass**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/ActivityModeTest.php
```

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/MoodService.php tests/Feature/Api/MoodDashboardTest.php
git commit -m "feat(mood): store user genre names in mood context for dashboard stats"
```

---

### Task 3: Form Requests for history and stats

**Files:**
- Create: `app/Http/Requests/MoodHistoryRequest.php`
- Create: `app/Http/Requests/MoodStatsRequest.php`

- [ ] **Step 1: Write failing validation tests**

Add to `MoodDashboardTest`:

```php
public function test_history_rejects_invalid_period(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/mood/history?period=invalid')
        ->assertUnprocessable();
}

public function test_stats_rejects_invalid_period(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/mood/stats?period=invalid')
        ->assertUnprocessable();
}

public function test_stats_rejects_period_all(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/mood/stats?period=all')
        ->assertUnprocessable();
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter "test_history_rejects_invalid_period|test_stats_rejects_invalid_period|test_stats_rejects_period_all"
```

Expected: FAIL — routes return 404 (endpoints not yet wired).

- [ ] **Step 3: Create MoodHistoryRequest**

Create `app/Http/Requests/MoodHistoryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoodHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:week,month,all'],
        ];
    }
}
```

- [ ] **Step 4: Create MoodStatsRequest**

Create `app/Http/Requests/MoodStatsRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoodStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:week,month'],
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/MoodHistoryRequest.php app/Http/Requests/MoodStatsRequest.php
git commit -m "feat(mood): add Form Requests for history and stats dashboard endpoints"
```

---

### Task 4: MoodDashboardService

**Files:**
- Create: `app/Services/MoodDashboardService.php`

- [ ] **Step 1: Write failing tests for stats computation**

Add to `MoodDashboardTest`:

```php
public function test_stats_returns_correct_structure(): void
{
    $user = User::factory()->create();
    $this->createMood($user);
    $this->createMood($user);

    $response = $this->actingAs($user)->getJson('/api/mood/stats?period=month');

    $response->assertOk()->assertJsonStructure([
        'data' => [
            'averages'                => ['energy', 'valence', 'intensity'],
            'most_frequent_emotion',
            'most_used_activity_mode',
            'most_active_day',
            'preferred_hour',
            'total_sessions',
            'weekly_evolution'        => [['week', 'energy', 'valence', 'intensity', 'count']],
            'top_genres',
            'source_breakdown'        => ['emoji', 'slider', 'activity'],
        ],
    ]);
}

public function test_stats_averages_are_correct(): void
{
    $user = User::factory()->create();

    $this->createMood($user, ['energy' => 60, 'valence' => 0.4, 'intensity' => 50]);
    $this->createMood($user, ['energy' => 80, 'valence' => 0.6, 'intensity' => 70]);

    $response = $this->actingAs($user)->getJson('/api/mood/stats?period=month');

    $data = $response->json('data');
    $this->assertEquals(70.0, $data['averages']['energy']);
    $this->assertEquals(0.5,  $data['averages']['valence']);
    $this->assertEquals(60.0, $data['averages']['intensity']);
}

public function test_stats_source_breakdown_counts_activity_mode(): void
{
    $user = User::factory()->create();

    $this->createMood($user, ['source' => 'emoji', 'context' => ['weather' => null, 'activity_mode' => null, 'genres' => []]]);
    $this->createMood($user, ['source' => 'slider', 'context' => ['weather' => null, 'activity_mode' => null, 'genres' => []]]);
    $this->createMood($user, ['source' => 'emoji', 'context' => ['weather' => null, 'activity_mode' => 'workout', 'genres' => []]]);

    $response = $this->actingAs($user)->getJson('/api/mood/stats?period=month');

    $breakdown = $response->json('data.source_breakdown');
    $this->assertEquals(1, $breakdown['emoji']);
    $this->assertEquals(1, $breakdown['slider']);
    $this->assertEquals(1, $breakdown['activity']);
}

public function test_stats_top_genres_returns_top_5(): void
{
    $user = User::factory()->create();

    foreach (['pop', 'pop', 'pop', 'rock', 'rock', 'jazz', 'metal', 'classical', 'blues'] as $genre) {
        $this->createMood($user, ['context' => ['weather' => null, 'activity_mode' => null, 'genres' => [$genre]]]);
    }

    $response = $this->actingAs($user)->getJson('/api/mood/stats?period=month');

    $genres = $response->json('data.top_genres');
    $this->assertCount(5, $genres);
    $this->assertEquals('pop', $genres[0]);
    $this->assertEquals('rock', $genres[1]);
}

public function test_stats_empty_when_no_moods(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/mood/stats?period=month');

    $response->assertOk();
    $data = $response->json('data');
    $this->assertEquals(0, $data['total_sessions']);
    $this->assertNull($data['most_frequent_emotion']);
    $this->assertEmpty($data['weekly_evolution']);
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter "test_stats"
```

Expected: FAIL — `stats` route does not exist.

- [ ] **Step 3: Create MoodDashboardService**

Create `app/Services/MoodDashboardService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MoodDashboardService
{
    public function stats(User $user, string $period): array
    {
        $moods = $user->moods()
            ->forPeriod($period)
            ->latest()
            ->get();

        if ($moods->isEmpty()) {
            return [
                'averages'                => ['energy' => 0, 'valence' => 0.0, 'intensity' => 0],
                'most_frequent_emotion'   => null,
                'most_used_activity_mode' => null,
                'most_active_day'         => null,
                'preferred_hour'          => null,
                'total_sessions'          => 0,
                'weekly_evolution'        => [],
                'top_genres'              => [],
                'source_breakdown'        => ['emoji' => 0, 'slider' => 0, 'activity' => 0],
            ];
        }

        return [
            'averages'                => $this->averages($moods),
            'most_frequent_emotion'   => $this->mostFrequentField($moods, 'emotion'),
            'most_used_activity_mode' => $this->mostUsedActivityMode($moods),
            'most_active_day'         => $this->mostActiveDay($moods),
            'preferred_hour'          => $this->preferredHour($moods),
            'total_sessions'          => $moods->count(),
            'weekly_evolution'        => $this->weeklyEvolution($moods),
            'top_genres'              => $this->topGenres($moods),
            'source_breakdown'        => $this->sourceBreakdown($moods),
        ];
    }

    private function averages(Collection $moods): array
    {
        return [
            'energy'    => round((float) $moods->avg('energy'), 1),
            'valence'   => round((float) $moods->avg('valence'), 2),
            'intensity' => round((float) $moods->avg('intensity'), 1),
        ];
    }

    private function mostFrequentField(Collection $moods, string $field): ?string
    {
        return $moods->groupBy($field)
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();
    }

    private function mostUsedActivityMode(Collection $moods): ?string
    {
        $withMode = $moods->filter(
            fn($mood) => ($mood->context['activity_mode'] ?? null) !== null,
        );

        if ($withMode->isEmpty()) {
            return null;
        }

        return $withMode
            ->groupBy(fn($mood) => $mood->context['activity_mode'])
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();
    }

    private function mostActiveDay(Collection $moods): ?string
    {
        return $moods
            ->groupBy(fn($mood) => $mood->created_at->englishDayOfWeek)
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();
    }

    private function preferredHour(Collection $moods): ?int
    {
        $hour = $moods
            ->groupBy(fn($mood) => $mood->created_at->hour)
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();

        return $hour !== null ? (int) $hour : null;
    }

    private function weeklyEvolution(Collection $moods): array
    {
        return $moods
            ->groupBy(fn($mood) => $mood->created_at->startOfWeek()->toDateString())
            ->map(fn(Collection $week, string $weekStart) => [
                'week'      => $weekStart,
                'energy'    => round((float) $week->avg('energy'), 1),
                'valence'   => round((float) $week->avg('valence'), 2),
                'intensity' => round((float) $week->avg('intensity'), 1),
                'count'     => $week->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function topGenres(Collection $moods): array
    {
        return $moods
            ->flatMap(fn($mood) => $mood->context['genres'] ?? [])
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(5)
            ->values()
            ->all();
    }

    private function sourceBreakdown(Collection $moods): array
    {
        $emoji    = 0;
        $slider   = 0;
        $activity = 0;

        foreach ($moods as $mood) {
            if (($mood->context['activity_mode'] ?? null) !== null) {
                $activity++;
            } elseif ($mood->source === 'emoji') {
                $emoji++;
            } else {
                $slider++;
            }
        }

        return ['emoji' => $emoji, 'slider' => $slider, 'activity' => $activity];
    }
}
```

- [ ] **Step 4: Commit service (tests still fail — routes not wired)**

```bash
git add app/Services/MoodDashboardService.php
git commit -m "feat(mood): add MoodDashboardService for aggregated stats computation"
```

---

### Task 5: Update MoodController

**Files:**
- Modify: `app/Http/Controllers/MoodController.php`

- [ ] **Step 1: Replace MoodController**

Replace the full file content of `app/Http/Controllers/MoodController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeMoodRequest;
use App\Http\Requests\MoodHistoryRequest;
use App\Http\Requests\MoodStatsRequest;
use App\Services\MoodDashboardService;
use App\Services\MoodService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class MoodController extends Controller
{
    public function __construct(
        private readonly MoodService          $moodService,
        private readonly WeatherService       $weatherService,
        private readonly MoodDashboardService $dashboardService,
    ) {}

    public function analyze(AnalyzeMoodRequest $request): JsonResponse
    {
        $data = $request->validated();

        $weather = null;
        if (isset($data['lat'], $data['lon'])) {
            $weatherData = $this->weatherService->getWeather(
                (float) $data['lat'],
                (float) $data['lon'],
            );
            $weather = $weatherData['condition'] ?? null;
        }

        $moodInput = [
            'source'        => $data['source'],
            'emotion'       => $data['emotion'] ?? null,
            'energy'        => $data['energy'],
            'valence'       => $data['valence'],
            'intensity'     => $data['intensity'],
            'weather'       => $weather,
            'activity_mode' => $data['activity_mode'] ?? null,
        ];

        $result = $this->moodService->recommend($request->user(), $moodInput);

        $suggestedMode = null;
        if ($weather === 'rain' && ($data['activity_mode'] ?? null) === null) {
            $suggestedMode = 'rainy_day';
        }

        return response()->json([
            'data' => [
                'mood'           => $result['mood'],
                'tracks'         => $result['tracks'],
                'suggested_mode' => $suggestedMode,
            ],
            'message' => 'Mood analyzed successfully.',
        ], 201);
    }

    public function history(MoodHistoryRequest $request): JsonResponse
    {
        $period = $request->validated()['period'] ?? 'all';

        $moods = $request->user()
            ->moods()
            ->forPeriod($period)
            ->latest()
            ->paginate(20)
            ->through(fn($mood) => [
                'id'            => $mood->id,
                'date'          => $mood->created_at->toDateString(),
                'time'          => $mood->created_at->format('H:i'),
                'source'        => $mood->source,
                'emotion'       => $mood->emotion,
                'energy'        => $mood->energy,
                'valence'       => $mood->valence,
                'intensity'     => $mood->intensity,
                'activity_mode' => $mood->context['activity_mode'] ?? null,
                'weather'       => $mood->context['weather'] ?? null,
                'tracks'        => collect($mood->tracks ?? [])
                    ->map(fn(array $t) => ['title' => $t['title'], 'artist' => $t['artist']])
                    ->all(),
            ]);

        return response()->json(['data' => $moods]);
    }

    public function stats(MoodStatsRequest $request): JsonResponse
    {
        $period = $request->validated()['period'] ?? 'month';
        $stats  = $this->dashboardService->stats($request->user(), $period);

        return response()->json(['data' => $stats]);
    }
}
```

- [ ] **Step 2: Commit controller changes**

```bash
git add app/Http/Controllers/MoodController.php
git commit -m "feat(mood): update MoodController with period-filtered history and stats endpoints"
```

---

### Task 6: Add stats route

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Add stats route**

In `routes/api.php`, inside the `middleware('has.genres')` group, add the stats route after the existing history route:

```php
Route::middleware('has.genres')->group(function (): void {
    Route::post('/mood/analyze', [MoodController::class, 'analyze']);
    Route::get('/mood/history', [MoodController::class, 'history']);
    Route::get('/mood/stats', [MoodController::class, 'stats']);
});
```

Also add the new use statements at the top if not already present:

```php
use App\Http\Controllers\MoodController;
```

(Already present — just adding the route line.)

- [ ] **Step 2: Verify routes are listed**

```bash
docker compose run --rm api php artisan route:list | grep mood
```

Expected output includes:
```
GET|HEAD  api/mood/history  mood.history  MoodController@history
GET|HEAD  api/mood/stats    mood.stats    MoodController@stats
POST      api/mood/analyze  mood.analyze  MoodController@analyze
```

- [ ] **Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat(mood): register GET /api/mood/stats route"
```

---

### Task 7: Full test suite — history endpoint

**Files:**
- Modify: `tests/Feature/Api/MoodDashboardTest.php`

- [ ] **Step 1: Add remaining history tests**

Add to the test class:

```php
public function test_history_requires_auth(): void
{
    $this->getJson('/api/mood/history')->assertUnauthorized();
}

public function test_history_default_returns_all_moods(): void
{
    $user = User::factory()->create();
    $this->createMood($user, ['created_at' => now()->subDays(40)]);
    $this->createMood($user, ['created_at' => now()->subDays(3)]);

    $response = $this->actingAs($user)->getJson('/api/mood/history');

    $response->assertOk()->assertJsonCount(2, 'data.data');
}

public function test_history_period_month_filters_older_moods(): void
{
    $user = User::factory()->create();
    $this->createMood($user, ['created_at' => now()->subDays(5)]);
    $this->createMood($user, ['created_at' => now()->subDays(40)]);

    $response = $this->actingAs($user)->getJson('/api/mood/history?period=month');

    $response->assertOk()->assertJsonCount(1, 'data.data');
}

public function test_history_returns_correct_fields(): void
{
    $user = User::factory()->create();
    $this->createMood($user, [
        'source'    => 'emoji',
        'emotion'   => 'happy',
        'energy'    => 70,
        'valence'   => 0.5,
        'intensity' => 60,
        'context'   => ['weather' => 'sunny', 'activity_mode' => 'workout', 'genres' => ['pop']],
        'tracks'    => [['deezer_id' => 1, 'title' => 'My Song', 'artist' => 'Great Artist', 'cover_url' => 'http://x', 'preview_url' => 'http://x', 'deezer_url' => 'http://x', 'album' => 'Album']],
    ]);

    $response = $this->actingAs($user)->getJson('/api/mood/history');
    $item = $response->json('data.data.0');

    $this->assertEquals('emoji',        $item['source']);
    $this->assertEquals('happy',        $item['emotion']);
    $this->assertEquals(70,             $item['energy']);
    $this->assertEquals(0.5,            $item['valence']);
    $this->assertEquals(60,             $item['intensity']);
    $this->assertEquals('workout',      $item['activity_mode']);
    $this->assertEquals('sunny',        $item['weather']);
    $this->assertEquals('My Song',      $item['tracks'][0]['title']);
    $this->assertEquals('Great Artist', $item['tracks'][0]['artist']);
    $this->assertArrayNotHasKey('cover_url',    $item['tracks'][0]);
    $this->assertArrayNotHasKey('preview_url',  $item['tracks'][0]);
    $this->assertArrayNotHasKey('deezer_url',   $item['tracks'][0]);
}

public function test_history_only_returns_current_users_moods(): void
{
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $this->createMood($user1);
    $this->createMood($user2);

    $response = $this->actingAs($user1)->getJson('/api/mood/history');

    $response->assertOk()->assertJsonCount(1, 'data.data');
}
```

- [ ] **Step 2: Run all history tests**

```bash
docker compose run --rm api php artisan test tests/Feature/Api/MoodDashboardTest.php --filter "test_history"
```

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/MoodDashboardTest.php
git commit -m "test(mood): add full history endpoint test coverage"
```

---

### Task 8: Run full test suite and cleanup

- [ ] **Step 1: Run all tests**

```bash
docker compose run --rm api php artisan test
```

Expected: all PASS. If any fail, fix before continuing.

- [ ] **Step 2: Verify route list is clean**

```bash
docker compose run --rm api php artisan route:list | grep mood
```

Expected — 3 mood routes: analyze (POST), history (GET), stats (GET).

- [ ] **Step 3: Final commit**

```bash
git add -p
git commit -m "test(mood): complete dashboard test suite — history + stats"
```

---

## Self-Review

### Spec coverage check

| Spec requirement | Task |
|-----------------|------|
| GET /api/mood/history?period=week\|month\|all | Task 1, 5, 6 |
| Paginated results (page param) | Task 5 — `paginate(20)` handles `?page=N` automatically |
| Date, time, source, emotion, energy, valence, intensity | Task 5, 7 |
| Activity mode from context | Task 5, 7 |
| Tracks (title + artist only, no URLs) | Task 5, 7 |
| Weather from context | Task 5, 7 |
| GET /api/mood/stats?period=week\|month | Task 3, 5, 6 |
| Avg energy/valence/intensity | Task 4 |
| Most frequent emotion | Task 4 |
| Most used activity mode | Task 4 |
| Most active day of week | Task 4 |
| Preferred hour | Task 4 |
| Total sessions | Task 4 |
| Week-over-week evolution array | Task 4 |
| Top 5 genres | Task 2, 4 |
| Source breakdown (emoji/slider/activity) | Task 4 |
| Eloquent Query Scopes for period filters | Task 1 |

All requirements covered.

### Placeholder scan

No TBD, no "implement later", no steps without code. Clean.

### Type consistency

- `scopeForPeriod(Builder $query, string $period)` defined in Task 1, called as `->forPeriod($period)` in Tasks 4 and 5. ✓
- `saveMood()` signature extended in Task 2 with `array $genreNames = []` — default prevents breaking existing call sites. ✓
- `MoodDashboardService::stats()` returns array — consumed directly by controller. ✓
- `MoodHistoryRequest` / `MoodStatsRequest` used in controller method signatures in Task 5. ✓
