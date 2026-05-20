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
        $user = $this->createUserWithGenre();

        $this->createMood($user, ['created_at' => now()->subDays(3)]);
        $this->createMood($user, ['created_at' => now()->subDays(10)]);

        $response = $this->actingAs($user)->getJson('/api/mood/history?period=week');

        $response->assertOk()->assertJsonCount(1, 'data.data');
    }

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

    public function test_stats_returns_correct_structure(): void
    {
        $user = $this->createUserWithGenre();
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
        $user = $this->createUserWithGenre();

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
        $user = $this->createUserWithGenre();

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
        $user = $this->createUserWithGenre();

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
        $user = $this->createUserWithGenre();

        $response = $this->actingAs($user)->getJson('/api/mood/stats?period=month');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['total_sessions']);
        $this->assertNull($data['most_frequent_emotion']);
        $this->assertEmpty($data['weekly_evolution']);
    }

    public function test_history_requires_auth(): void
    {
        $this->getJson('/api/mood/history')->assertUnauthorized();
    }

    public function test_history_default_returns_all_moods(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMood($user, ['created_at' => now()->subDays(40)]);
        $this->createMood($user, ['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->getJson('/api/mood/history');

        $response->assertOk()->assertJsonCount(2, 'data.data');
    }

    public function test_history_period_month_filters_older_moods(): void
    {
        $user = $this->createUserWithGenre();
        $this->createMood($user, ['created_at' => now()->subDays(5)]);
        $this->createMood($user, ['created_at' => now()->subDays(40)]);

        $response = $this->actingAs($user)->getJson('/api/mood/history?period=month');

        $response->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_history_returns_correct_fields(): void
    {
        $user = $this->createUserWithGenre();
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
        $user1 = $this->createUserWithGenre();
        $user2 = $this->createUserWithGenre();
        $this->createMood($user1);
        $this->createMood($user2);

        $response = $this->actingAs($user1)->getJson('/api/mood/history');

        $response->assertOk()->assertJsonCount(1, 'data.data');
    }

    // helpers
    private function createUserWithGenre(): User
    {
        $user  = User::factory()->create();
        $genre = \App\Models\Genre::firstOrCreate(
            ['slug' => 'pop'],
            ['name' => 'pop', 'deezer_id' => 132]
        );
        $user->genres()->attach($genre->id);

        return $user;
    }

    private function createMood(User $user, array $override = []): Mood
    {
        $createdAt = $override['created_at'] ?? null;
        unset($override['created_at']);

        $mood = Mood::create(array_merge([
            'user_id'   => $user->id,
            'source'    => 'emoji',
            'emotion'   => 'happy',
            'energy'    => 70,
            'valence'   => 0.5,
            'intensity' => 60,
            'context'   => ['weather' => null, 'activity_mode' => null, 'genres' => ['pop']],
            'tracks'    => [['title' => 'Song', 'artist' => 'Artist A', 'deezer_id' => 1]],
        ], $override));

        if ($createdAt !== null) {
            \Illuminate\Support\Facades\DB::table('moods')
                ->where('id', $mood->id)
                ->update(['created_at' => $createdAt]);
            $mood->created_at = $createdAt;
        }

        return $mood;
    }
}
