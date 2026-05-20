<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MoodDashboardService
{
    public function stats(User $user, string $period): array
    {
        $moods = $user->moods()
            ->forPeriod($period)
            ->latest()
            ->get();

        if ($moods->isEmpty()) {
            return [
                'averages'                => ['energy' => 0, 'valence' => 0.0, 'intensity' => 0],
                'most_frequent_emotion'   => null,
                'most_used_activity_mode' => null,
                'most_active_day'         => null,
                'preferred_hour'          => null,
                'total_sessions'          => 0,
                'weekly_evolution'        => [],
                'top_genres'              => [],
                'source_breakdown'        => ['emoji' => 0, 'slider' => 0, 'activity' => 0],
            ];
        }

        return [
            'averages'                => $this->averages($moods),
            'most_frequent_emotion'   => $this->mostFrequentField($moods, 'emotion'),
            'most_used_activity_mode' => $this->mostUsedActivityMode($moods),
            'most_active_day'         => $this->mostActiveDay($moods),
            'preferred_hour'          => $this->preferredHour($moods),
            'total_sessions'          => $moods->count(),
            'weekly_evolution'        => $this->weeklyEvolution($moods),
            'top_genres'              => $this->topGenres($moods),
            'source_breakdown'        => $this->sourceBreakdown($moods),
        ];
    }

    private function averages(Collection $moods): array
    {
        return [
            'energy'    => round((float) $moods->avg('energy'), 1),
            'valence'   => round((float) $moods->avg('valence'), 2),
            'intensity' => round((float) $moods->avg('intensity'), 1),
        ];
    }

    private function mostFrequentField(Collection $moods, string $field): ?string
    {
        return $moods->groupBy($field)
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();
    }

    private function mostUsedActivityMode(Collection $moods): ?string
    {
        $withMode = $moods->filter(
            fn($mood) => ($mood->context['activity_mode'] ?? null) !== null,
        );

        if ($withMode->isEmpty()) {
            return null;
        }

        return $withMode
            ->groupBy(fn($mood) => $mood->context['activity_mode'])
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();
    }

    private function mostActiveDay(Collection $moods): ?string
    {
        return $moods
            ->groupBy(fn($mood) => $mood->created_at->englishDayOfWeek)
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();
    }

    private function preferredHour(Collection $moods): ?int
    {
        $hour = $moods
            ->groupBy(fn($mood) => $mood->created_at->hour)
            ->sortByDesc(fn(Collection $group) => $group->count())
            ->keys()
            ->first();

        return $hour !== null ? (int) $hour : null;
    }

    private function weeklyEvolution(Collection $moods): array
    {
        return $moods
            ->groupBy(fn($mood) => $mood->created_at->startOfWeek()->toDateString())
            ->map(fn(Collection $week, string $weekStart) => [
                'week'      => $weekStart,
                'energy'    => round((float) $week->avg('energy'), 1),
                'valence'   => round((float) $week->avg('valence'), 2),
                'intensity' => round((float) $week->avg('intensity'), 1),
                'count'     => $week->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function topGenres(Collection $moods): array
    {
        return $moods
            ->flatMap(fn($mood) => $mood->context['genres'] ?? [])
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(5)
            ->values()
            ->all();
    }

    private function sourceBreakdown(Collection $moods): array
    {
        $emoji    = 0;
        $slider   = 0;
        $activity = 0;

        foreach ($moods as $mood) {
            if (($mood->context['activity_mode'] ?? null) !== null) {
                $activity++;
            } elseif ($mood->source === 'emoji') {
                $emoji++;
            } else {
                $slider++;
            }
        }

        return ['emoji' => $emoji, 'slider' => $slider, 'activity' => $activity];
    }
}
