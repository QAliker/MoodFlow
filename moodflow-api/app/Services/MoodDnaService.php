<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MoodDnaService
{
    /**
     * @return array{status: string, minimum: int, current: int}|array{dominantEmotion: ?string, energyProfile: string, valenceProfile: string, consistency: float, peakHour: ?int, genreFingerprint: array<string>, activityProfile: array<string>, shape: int, colors: array<string>, pulseSpeed: float, symmetry: float}
     */
    public function generateDna(User $user): array
    {
        $moods = $user->moods()
            ->latest()
            ->limit(50)
            ->get();

        if ($moods->count() < 10) {
            return [
                'status'  => 'insufficient_data',
                'minimum' => 10,
                'current' => $moods->count(),
            ];
        }

        $energies    = $moods->pluck('energy')->map(fn($v) => (float) $v)->all();
        $valences    = $moods->pluck('valence')->map(fn($v) => (float) $v)->all();
        $intensities = $moods->pluck('intensity')->map(fn($v) => (float) $v)->all();

        $avgEnergy    = array_sum($energies) / count($energies);
        $avgValence   = array_sum($valences) / count($valences);
        $avgIntensity = array_sum($intensities) / count($intensities);
        $stdDev       = $this->stdDev($energies);

        return [
            'dominantEmotion'  => $this->dominantEmotion($moods),
            'energyProfile'    => $this->energyProfile($avgEnergy),
            'valenceProfile'   => $this->valenceProfile($avgValence),
            'consistency'      => $stdDev,
            'peakHour'         => $this->peakHour($moods),
            'genreFingerprint' => $this->genreFingerprint($moods),
            'activityProfile'  => $this->activityProfile($moods),
            'shape'            => $this->shape($moods),
            'colors'           => $this->colors($avgEnergy, $avgValence, $avgIntensity),
            'pulseSpeed'       => $this->pulseSpeed($avgEnergy),
            'symmetry'         => $this->symmetry($stdDev),
        ];
    }

    private function energyProfile(float $avg): string
    {
        return match (true) {
            $avg < 35  => 'calm',
            $avg <= 65 => 'moderate',
            default    => 'energetic',
        };
    }

    private function valenceProfile(float $avg): string
    {
        return match (true) {
            $avg < -0.2 => 'melancholic',
            $avg <= 0.3 => 'neutral',
            default     => 'positive',
        };
    }

    private function dominantEmotion(Collection $moods): ?string
    {
        return $moods->groupBy('emotion')
            ->sortByDesc(fn(Collection $g) => $g->count())
            ->keys()
            ->first();
    }

    private function peakHour(Collection $moods): ?int
    {
        $hour = $moods->groupBy(fn($m) => $m->created_at->hour)
            ->sortByDesc(fn(Collection $g) => $g->count())
            ->keys()
            ->first();

        return $hour !== null ? (int) $hour : null;
    }

    private function genreFingerprint(Collection $moods): array
    {
        return $moods
            ->flatMap(fn($m) => $m->context['genres'] ?? [])
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(3)
            ->values()
            ->all();
    }

    private function activityProfile(Collection $moods): array
    {
        return $moods
            ->map(fn($m) => $m->context['activity_mode'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(2)
            ->values()
            ->all();
    }

    private function shape(Collection $moods): int
    {
        $unique = $moods->pluck('emotion')->unique()->count();

        return min(8, max(3, $unique + 2));
    }

    private function colors(float $energy, float $valence, float $intensity): array
    {
        $energy   = max(0.0, min(100.0, $energy));
        $valence  = max(-1.0, min(1.0, $valence));
        $intensity = max(0.0, min(100.0, $intensity));

        $baseHue = $energy / 100 * 360;
        $sat     = ($valence + 1) / 2 * 100;
        $light   = $intensity / 100 * 40 + 30;

        return [
            $this->hslToHex($baseHue,                  $sat, $light),
            $this->hslToHex(fmod($baseHue + 30,  360), $sat, $light),
            $this->hslToHex(fmod($baseHue + 90,  360), $sat, $light),
            $this->hslToHex(fmod($baseHue + 180, 360), $sat, $light),
        ];
    }

    private function pulseSpeed(float $energy): float
    {
        return match (true) {
            $energy < 35  => 0.5,
            $energy <= 65 => 1.0,
            default       => 2.0,
        };
    }

    private function symmetry(float $stdDev): float
    {
        return match (true) {
            $stdDev < 10  => 1.0,
            $stdDev < 20  => 0.75,
            $stdDev < 35  => 0.5,
            default       => 0.25,
        };
    }

    private function stdDev(array $values): float
    {
        if (count($values) === 0) {
            return 0.0;
        }

        $mean     = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values);

        return round(sqrt($variance), 2);
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return sprintf('#%02x%02x%02x', $v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = (int) round($this->hueToRgb($p, $q, $h + 1 / 3) * 255);
        $g = (int) round($this->hueToRgb($p, $q, $h) * 255);
        $b = (int) round($this->hueToRgb($p, $q, $h - 1 / 3) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
