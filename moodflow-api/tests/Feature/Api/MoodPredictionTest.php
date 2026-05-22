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
