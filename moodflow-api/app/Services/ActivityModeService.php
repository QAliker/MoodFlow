<?php

namespace App\Services;

use InvalidArgumentException;

class ActivityModeService
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return config('activity-modes');
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        $mode = config("activity-modes.{$slug}");

        if ($mode === null) {
            throw new InvalidArgumentException("Unknown activity mode: {$slug}");
        }

        return $mode;
    }

    /**
     * Merge mode params with user mood. Mode = BASE, user mood nudges ±20%.
     *
     * @param  array{source: string, energy: int, valence: float, intensity: int}  $moodInput
     * @return array<string, mixed>
     */
    public function merge(array $moodInput, string $modeSlug): array
    {
        $mode = $this->get($modeSlug);

        if ($mode['uses_user_mood'] ?? false) {
            $energy    = $moodInput['energy'];
            $valence   = $moodInput['valence'];
            $intensity = $moodInput['intensity'];
        } else {
            $energyOffset    = ($moodInput['energy'] - 50) * 0.4;
            $intensityOffset = ($moodInput['intensity'] - 50) * 0.4;
            $valenceOffset   = $moodInput['valence'] * 0.2;

            $energy    = (int) round(max(0, min(100, $mode['energy'] + $energyOffset)));
            $intensity = (int) round(max(0, min(100, $mode['intensity'] + $intensityOffset)));
            $valence   = round(max(-1.0, min(1.0, $mode['valence'] + $valenceOffset)), 4);
        }

        return array_merge($moodInput, [
            'energy'            => $energy,
            'valence'           => $valence,
            'intensity'         => $intensity,
            'activity_mode'     => $modeSlug,
            'mode_keywords'     => $mode['keywords'],
            'mode_genres_boost' => $mode['genres_boost'],
            'filter_no_lyrics'  => $mode['filter_no_lyrics'] ?? false,
        ]);
    }
}
