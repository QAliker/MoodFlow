<?php

namespace Tests\Unit\Services;

use App\Models\Mood;
use App\Models\User;
use App\Services\MoodDnaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertEquals(10, $result['minimum']);
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
        $this->assertArrayNotHasKey('status', $result);
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

        $this->assertEqualsWithDelta(0.5, $result['pulseSpeed'], 0.001);
    }

    public function test_pulse_speed_normal_for_moderate_energy(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 50]);

        $result = $this->service->generateDna($user);

        $this->assertEqualsWithDelta(1.0, $result['pulseSpeed'], 0.001);
    }

    public function test_pulse_speed_fast_for_high_energy(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['energy' => 80]);

        $result = $this->service->generateDna($user);

        $this->assertEqualsWithDelta(2.0, $result['pulseSpeed'], 0.001);
    }

    public function test_symmetry_high_for_stable_energy(): void
    {
        $user = User::factory()->create();
        // All same energy → std dev = 0 → symmetry = 1.0
        $this->createMoods($user, 10, ['energy' => 50]);

        $result = $this->service->generateDna($user);

        $this->assertEqualsWithDelta(1.0, $result['symmetry'], 0.001);
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

        $this->assertEqualsWithDelta(0.25, $result['symmetry'], 0.001);
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
        // Need 10 moods total — add 3 more neutral (empty genres to avoid inflating pop count)
        $this->createMoods($user, 3, ['context' => ['genres' => [], 'activity_mode' => null, 'weather' => null]]);

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
        // Force old moods to a past timestamp so latest() LIMIT 50 excludes them
        \Illuminate\Support\Facades\DB::table('moods')
            ->where('user_id', $user->id)
            ->where('emotion', 'old')
            ->update(['created_at' => now()->subDays(1)]);
        $this->createMoods($user, 50, ['emotion' => 'happy']);

        $result = $this->service->generateDna($user);

        $this->assertEquals('happy', $result['dominantEmotion']);
    }

    public function test_peak_hour_returns_most_frequent_hour(): void
    {
        $user = User::factory()->create();
        $this->createMoods($user, 10, ['created_at' => now()->setHour(14)]);
        $this->createMoods($user, 3,  ['created_at' => now()->setHour(8)]);

        $result = $this->service->generateDna($user);

        $this->assertEquals(14, $result['peakHour']);
    }

    // Helper: create N moods with optional overrides
    private function createMoods(User $user, int $count, array $override = []): void
    {
        $createdAt = $override['created_at'] ?? null;
        unset($override['created_at']);

        for ($i = 0; $i < $count; $i++) {
            $mood = Mood::create(array_merge([
                'user_id'   => $user->id,
                'source'    => 'emoji',
                'emotion'   => 'happy',
                'energy'    => 50,
                'valence'   => 0.1,
                'intensity' => 50,
                'context'   => ['genres' => ['pop'], 'activity_mode' => null, 'weather' => null],
                'tracks'    => [],
            ], $override));

            if ($createdAt !== null) {
                \Illuminate\Support\Facades\DB::table('moods')
                    ->where('id', $mood->id)
                    ->update(['created_at' => $createdAt]);
            }
        }
    }
}
