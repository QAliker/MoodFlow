<?php

namespace App\Services;

use App\Models\Mood;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MoodService
{
    public function __construct(
        private readonly DeezerService $deezer,
        private readonly ContextService $context,
    ) {}

    /**
     * Generate a mood-based track recommendation and persist the Mood.
     *
     * @param  array{source: string, energy: int, valence: float, intensity: int, weather?: string|null}  $moodInput
     * @return array{mood: Mood, tracks: array<int, array<string, mixed>>}
     */
    public function recommend(User $user, array $moodInput): array
    {
        // 1. Apply passive context modifier
        $modifier = $this->context->getModifier(
            Carbon::now(),
            $moodInput['weather'] ?? null,
        );

        $adjusted = $this->applyModifier($moodInput, $modifier);

        // 2. Select keywords and emotion label from adjusted mood
        [$label, $primary, $secondary] = $this->selectKeywords(
            $adjusted['energy'],
            $adjusted['valence'],
        );

        // 3. Fetch, deduplicate and shuffle tracks
        $user->loadMissing('genres');
        $tracks = $this->fetchTracks($user->genres, $primary, $secondary);

        // 4. Persist
        $mood = $this->saveMood($user, $moodInput, $adjusted, $modifier, $label, $tracks);

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
     * Return [emotionLabel, primaryKeyword, secondaryKeyword] based on adjusted mood values.
     *
     * @return array{string, string, string}
     */
    private function selectKeywords(float $energy, float $valence): array
    {
        return match (true) {
            $energy > 70 && $valence > 0.3  => ['energetic',  'party',         'energy'],
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
     * Fetch tracks from Deezer for each genre using primary keyword,
     * then fall back to secondary keyword if fewer than 15 tracks are found.
     *
     * @param  Collection<int, \App\Models\Genre>  $genres
     * @return array<int, array<string, mixed>>
     */
    private function fetchTracks(Collection $genres, string $primary, string $secondary): array
    {
        if ($genres->isEmpty()) {
            return [];
        }

        $tracks = $this->searchByKeyword($genres, $primary);

        if (count($tracks) < 15) {
            $second = $this->searchByKeyword($genres, $secondary);
            $tracks = $this->mergeAndDedup($tracks, $second);
        }

        shuffle($tracks);

        return array_slice($tracks, 0, 20);
    }

    /**
     * Search Deezer for "{keyword} {genre}" for each genre and return deduplicated tracks.
     *
     * @param  Collection<int, \App\Models\Genre>  $genres
     * @return array<int, array<string, mixed>>
     */
    private function searchByKeyword(Collection $genres, string $keyword): array
    {
        $results = [];

        foreach ($genres as $genre) {
            $found = $this->deezer->searchTracks("{$keyword} {$genre->name}", 10);
            $results = $this->mergeAndDedup($results, $found);
        }

        return $results;
    }

    /**
     * Merge two track arrays and deduplicate by deezer_id.
     *
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
    ): Mood {
        return Mood::create([
            'user_id'   => $user->id,
            'source'    => $moodInput['source'],
            'emotion'   => $label,
            'energy'    => $moodInput['energy'],
            'valence'   => $moodInput['valence'],
            'intensity' => $moodInput['intensity'],
            'context'   => [
                'weather'  => $moodInput['weather'] ?? null,
                'modifier' => $modifier,
                'adjusted' => $adjusted,
            ],
            'tracks'    => $tracks,
        ]);
    }
}
