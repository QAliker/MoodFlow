<?php

namespace App\Services;

use App\Models\ActivityModeUsage;
use App\Models\Mood;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MoodService
{
    public function __construct(
        private readonly DeezerService $deezer,
        private readonly ContextService $context,
        private readonly ActivityModeService $activityMode,
    ) {}

    /**
     * @param  array{source: string, energy: int, valence: float, intensity: int, weather?: string|null, activity_mode?: string|null}  $moodInput
     * @return array{mood: Mood, tracks: array<int, array<string, mixed>>}
     */
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
        $tracks = $this->fetchTracks(
            $user->genres,
            $primary,
            $secondary,
            $moodInput['mode_genres_boost'] ?? [],
        );

        $mood = $this->saveMood($user, $moodInput, $adjusted, $modifier, $label, $tracks, $modeSlug);

        if ($modeSlug !== null) {
            ActivityModeUsage::create([
                'user_id'   => $user->id,
                'mode_slug' => $modeSlug,
                'used_at'   => Carbon::now(),
            ]);
        }

        return ['mood' => $mood, 'tracks' => $tracks];
    }

    private function applyModifier(array $moodInput, array $modifier): array
    {
        return [
            'energy'    => (int) max(0, min(100, $moodInput['energy'] + $modifier['energy_mod'])),
            'valence'   => (float) max(-1.0, min(1.0, $moodInput['valence'] + $modifier['valence_mod'] / 100)),
            'intensity' => (int) max(0, min(100, $moodInput['intensity'] + $modifier['intensity_mod'])),
        ];
    }

    private function resolveKeywords(float $energy, float $valence, array $moodInput): array
    {
        if (isset($moodInput['mode_keywords']) && count($moodInput['mode_keywords']) >= 2) {
            $primary   = $moodInput['mode_keywords'][0];
            $secondary = $moodInput['mode_keywords'][1];

            if ($moodInput['filter_no_lyrics'] ?? false) {
                $primary .= ' instrumental';
            }

            [$label] = $this->selectKeywords($energy, $valence);

            return [$label, $primary, $secondary];
        }

        return $this->selectKeywords($energy, $valence);
    }

    /**
     * @return array{string, string, string}
     */
    private function selectKeywords(float $energy, float $valence): array
    {
        return match (true) {
            $energy > 70 && $valence > 0.3  => ['energetic',   'party',         'energy'],
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
     * @param  Collection<int, \App\Models\Genre>  $genres
     * @param  array<int, string>  $boostGenres
     * @return array<int, array<string, mixed>>
     */
    private function fetchTracks(Collection $genres, string $primary, string $secondary, array $boostGenres = []): array
    {
        if ($genres->isEmpty() && empty($boostGenres)) {
            return [];
        }

        $tracks = $this->searchByKeyword($genres, $primary, $boostGenres);

        if (count($tracks) < 15) {
            $second = $this->searchByKeyword($genres, $secondary, $boostGenres);
            $tracks = $this->mergeAndDedup($tracks, $second);
        }

        shuffle($tracks);

        return array_slice($tracks, 0, 20);
    }

    /**
     * @param  Collection<int, \App\Models\Genre>  $genres
     * @param  array<int, string>  $boostGenres
     * @return array<int, array<string, mixed>>
     */
    private function searchByKeyword(Collection $genres, string $keyword, array $boostGenres = []): array
    {
        $results = [];

        foreach ($genres as $genre) {
            $found   = $this->deezer->searchTracks("{$keyword} {$genre->name}", 10);
            $results = $this->mergeAndDedup($results, $found);
        }

        foreach ($boostGenres as $boostGenre) {
            $found   = $this->deezer->searchTracks("{$keyword} {$boostGenre}", 10);
            $results = $this->mergeAndDedup($results, $found);
        }

        return $results;
    }

    /**
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
        ?string $modeSlug,
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
            ],
            'tracks'    => $tracks,
        ]);
    }
}
