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

        $this->assertStringContainsString('Mardi',              $result['message']);
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
}
