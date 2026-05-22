<?php

namespace Database\Seeders;

use App\Models\ActivityModeUsage;
use App\Models\Genre;
use App\Models\Mood;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@moodflow.app'],
            [
                'name'               => 'Alex Demo',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
            ]
        );

        $genreSlugs = ['rap', 'pop', 'r-b', 'electro', 'lo-fi'];
        $genreIds = Genre::whereIn('slug', $genreSlugs)->pluck('id');
        $user->genres()->syncWithoutDetaching($genreIds);

        $this->seedMoods($user);
        $this->seedActivityModeUsages($user);
        $this->seedRoom($user);

        $this->command->info("Demo user: demo@moodflow.app / password");
    }

    private function seedMoods(User $user): void
    {
        Mood::where('user_id', $user->id)->delete();

        $emotions = [
            // [emotion, energy, valence, intensity, source]
            ['happy',      80, 0.85, 70, 'emoji'],
            ['energetic',  90, 0.80, 85, 'slider'],
            ['chill',      35, 0.65, 30, 'emoji'],
            ['sad',        25, 0.20, 40, 'emoji'],
            ['focused',    65, 0.60, 60, 'slider'],
            ['anxious',    70, 0.25, 75, 'emoji'],
            ['euphoric',   95, 0.90, 90, 'slider'],
            ['melancholic',20, 0.30, 35, 'emoji'],
            ['motivated',  85, 0.75, 80, 'slider'],
            ['tired',      15, 0.40, 20, 'emoji'],
        ];

        $moods = [];
        $now = Carbon::now();

        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $date = $now->copy()->subDays($daysAgo);

            // 1-3 moods per day
            $dailyCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $dailyCount; $i++) {
                $hour = $this->realisticHour($i, $dailyCount);
                [$emotion, $baseEnergy, $baseValence, $baseIntensity, $source] = fake()->randomElement($emotions);

                // morning = more energy, evening = more chill
                $energyMod = $hour < 12 ? rand(0, 15) : rand(-20, 0);
                $energy    = max(5, min(100, $baseEnergy + $energyMod));
                $valence   = round(max(0.05, min(1.0, $baseValence + (rand(-10, 10) / 100))), 4);
                $intensity = max(5, min(100, $baseIntensity + rand(-10, 10)));

                $moods[] = [
                    'user_id'    => $user->id,
                    'source'     => $source,
                    'emotion'    => $emotion,
                    'energy'     => $energy,
                    'valence'    => $valence,
                    'intensity'  => $intensity,
                    'context'    => json_encode($this->randomContext($hour)),
                    'tracks'     => json_encode($this->fakeTracks()),
                    'created_at' => $date->copy()->setHour($hour)->setMinute(rand(0, 59)),
                    'updated_at' => $date->copy()->setHour($hour)->setMinute(rand(0, 59)),
                ];
            }
        }

        foreach (array_chunk($moods, 50) as $chunk) {
            Mood::insert($chunk);
        }
    }

    private function realisticHour(int $index, int $total): int
    {
        if ($total === 1) {
            return fake()->numberBetween(8, 22);
        }
        return match ($index) {
            0       => fake()->numberBetween(7, 12),
            1       => fake()->numberBetween(13, 18),
            default => fake()->numberBetween(19, 23),
        };
    }

    private function randomContext(int $hour): array
    {
        $timeOfDay = match (true) {
            $hour < 12  => 'morning',
            $hour < 17  => 'afternoon',
            $hour < 21  => 'evening',
            default     => 'night',
        };

        $activities = ['working', 'commuting', 'exercising', 'relaxing', 'studying', 'socializing'];

        return [
            'time_of_day' => $timeOfDay,
            'activity'    => fake()->randomElement($activities),
            'weather'     => fake()->randomElement(['sunny', 'cloudy', 'rainy', 'clear']),
        ];
    }

    private function fakeTracks(): array
    {
        $count = rand(3, 6);
        $tracks = [];

        for ($i = 0; $i < $count; $i++) {
            $tracks[] = [
                'id'       => rand(100000, 999999),
                'title'    => 'Track ' . Str::random(6),
                'artist'   => 'Artist ' . Str::random(4),
                'duration' => rand(160, 280),
                'preview'  => null,
            ];
        }

        return $tracks;
    }

    private function seedActivityModeUsages(User $user): void
    {
        ActivityModeUsage::where('user_id', $user->id)->delete();

        $modes = ['emoji', 'slider', 'passive', 'emoji', 'emoji', 'slider'];
        $now   = Carbon::now();

        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $count = rand(1, 3);
            for ($i = 0; $i < $count; $i++) {
                ActivityModeUsage::create([
                    'user_id'   => $user->id,
                    'mode_slug' => fake()->randomElement($modes),
                    'used_at'   => $now->copy()->subDays($daysAgo)->subHours(rand(0, 12)),
                ]);
            }
        }
    }

    private function seedRoom(User $user): void
    {
        Room::updateOrCreate(
            ['slug' => 'demo-vibes'],
            [
                'name'       => 'Demo Vibes',
                'creator_id' => $user->id,
                'max_users'  => 8,
                'is_active'  => true,
            ]
        );
    }
}
