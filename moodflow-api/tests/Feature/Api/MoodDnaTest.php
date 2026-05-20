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
