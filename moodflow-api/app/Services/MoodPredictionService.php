<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MoodPredictionService
{
    public function __construct(private readonly ContextService $contextService) {}

    public function predict(User $user, Carbon $now, ?string $weather = null): array
    {
        $similar    = $this->findSimilarMoods($user, $now, $weather);
        $count      = $similar->count();
        $confidence = $this->computeConfidence($count);

        if ($confidence < 0.3) {
            return $this->fallback($now, $weather);
        }

        $predicted = $this->averageMood($similar);

        return [
            'predicted_mood'     => $predicted,
            'confidence'         => round($confidence, 2),
            'based_on'           => $count,
            'suggested_activity' => $this->suggestedActivity($similar),
            'message'            => $this->buildMessage($now, $weather, $predicted),
        ];
    }

    private function findSimilarMoods(User $user, Carbon $now, ?string $weather): Collection
    {
        $targetDay  = $now->dayOfWeek;
        $targetHour = $now->hour;

        return $user->moods()->get()->filter(function ($mood) use ($targetDay, $targetHour, $weather) {
            $createdAt = $mood->created_at;

            $dayDiff = abs($createdAt->dayOfWeek - $targetDay);
            if (min($dayDiff, 7 - $dayDiff) > 1) {
                return false;
            }

            $hourDiff = abs($createdAt->hour - $targetHour);
            if (min($hourDiff, 24 - $hourDiff) > 2) {
                return false;
            }

            if ($weather !== null && ($mood->context['weather']['condition'] ?? null) !== $weather) {
                return false;
            }

            return true;
        });
    }

    private function computeConfidence(int $count): float
    {
        return $count < 3 ? 0.0 : min(1.0, $count / 10);
    }

    private function averageMood(Collection $moods): array
    {
        return [
            'energy'    => (int) round($moods->avg('energy')),
            'valence'   => round($moods->avg('valence'), 4),
            'intensity' => (int) round($moods->avg('intensity')),
        ];
    }

    private function suggestedActivity(Collection $moods): ?string
    {
        return $moods
            ->map(fn($m) => $m->context['activity_mode'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    private function buildMessage(Carbon $now, ?string $weather, array $predicted): string
    {
        $days   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $hour   = $now->hour;
        $period = match (true) {
            $hour >= 6  && $hour < 12 => 'matin',
            $hour >= 12 && $hour < 18 => 'après-midi',
            $hour >= 18 && $hour < 22 => 'soir',
            default                   => 'nuit',
        };

        $weatherWords = ['clear' => 'ensoleillé', 'clouds' => 'nuageux', 'rain' => 'pluvieux', 'snow' => 'neigeux'];
        $weatherWord  = $weather ? ($weatherWords[$weather] ?? null) : null;

        $context = ucfirst($days[$now->dayOfWeek]) . ' ' . $period;
        if ($weatherWord) {
            $context .= ' ' . $weatherWord;
        }

        return "{$context}, tu es généralement {$this->moodDescription($predicted['energy'], $predicted['valence'])}";
    }

    private function moodDescription(int $energy, float $valence): string
    {
        $e = match (true) {
            $energy < 33  => 'low',
            $energy <= 66 => 'medium',
            default       => 'high',
        };
        $v = match (true) {
            $valence < -0.33 => 'negative',
            $valence <= 0.33 => 'neutral',
            default          => 'positive',
        };

        return match ("{$e}_{$v}") {
            'low_negative'    => 'calme et mélancolique',
            'low_neutral'     => 'calme et concentré',
            'low_positive'    => 'calme et serein',
            'medium_negative' => 'modéré et introspectif',
            'medium_neutral'  => 'équilibré',
            'medium_positive' => 'détendu et positif',
            'high_negative'   => 'agité',
            'high_neutral'    => 'énergique',
            default           => 'dynamique et enthousiaste',
        };
    }

    private function fallback(Carbon $now, ?string $weather): array
    {
        $mod       = $this->contextService->getModifier($now, $weather);
        $energy    = (int) min(100, max(0, 50 + $mod['energy_mod']));
        $valence   = round(min(1.0, max(-1.0, $mod['valence_mod'] / 100)), 4);
        $intensity = (int) min(100, max(0, 50 + $mod['intensity_mod']));
        $predicted = compact('energy', 'valence', 'intensity');

        return [
            'predicted_mood'     => $predicted,
            'confidence'         => 0.0,
            'based_on'           => 0,
            'suggested_activity' => null,
            'message'            => $this->buildMessage($now, $weather, $predicted),
        ];
    }
}
