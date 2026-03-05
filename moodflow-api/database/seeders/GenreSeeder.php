<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            ['name' => 'Rap',        'deezer_id' => 116,  'icon' => '🎤'],
            ['name' => 'R&B',        'deezer_id' => 165,  'icon' => '🎶'],
            ['name' => 'Pop',        'deezer_id' => 132,  'icon' => '🌟'],
            ['name' => 'Rock',       'deezer_id' => 152,  'icon' => '🎸'],
            ['name' => 'Electro',    'deezer_id' => 106,  'icon' => '⚡'],
            ['name' => 'Jazz',       'deezer_id' => 129,  'icon' => '🎷'],
            ['name' => 'Classique',  'deezer_id' => 98,   'icon' => '🎻'],
            ['name' => 'Afrobeats', 'deezer_id' => null,  'icon' => '🥁'],
            ['name' => 'Reggaeton', 'deezer_id' => 122,   'icon' => '🌴'],
            ['name' => 'Metal',      'deezer_id' => 464,  'icon' => '🤘'],
            ['name' => 'Country',    'deezer_id' => 84,   'icon' => '🤠'],
            ['name' => 'Soul/Funk',  'deezer_id' => 169,  'icon' => '🕺'],
            ['name' => 'Indie',      'deezer_id' => 85,   'icon' => '🌿'],
            ['name' => 'Lo-fi',      'deezer_id' => null, 'icon' => '☕'],
            ['name' => 'K-pop',      'deezer_id' => null, 'icon' => '🌸'],
        ];

        foreach ($genres as $data) {
            Genre::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name'      => $data['name'],
                    'deezer_id' => $data['deezer_id'],
                    'icon'      => $data['icon'],
                ]
            );
        }
    }
}
