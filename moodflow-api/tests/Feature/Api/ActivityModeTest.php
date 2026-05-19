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
        $genre = Genre::create(['name' => 'pop', 'slug' => 'pop', 'deezer_id' => 132]);
        $user->genres()->attach($genre->id);
        return $user;
    }
}
