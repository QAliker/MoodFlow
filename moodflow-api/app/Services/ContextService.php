<?php

namespace App\Services;

use Carbon\Carbon;

class ContextService
{
    private const CLAMP_MIN = -15;
    private const CLAMP_MAX = 15;

    /**
     * Compute passive context modifiers based on time and weather.
     *
     * @return array{energy_mod: int, valence_mod: int, intensity_mod: int}
     */
    public function getModifier(Carbon $now, ?string $weather = null): array
    {
        $energyMod   = 0;
        $valenceMod  = 0;
        $intensityMod = 0;

        $this->applyTimeOfDay($now, $energyMod, $valenceMod);
        $this->applyDayOfWeek($now, $energyMod, $valenceMod);
        $this->applyWeather($weather, $energyMod, $valenceMod);

        return [
            'energy_mod'    => $this->clamp($energyMod),
            'valence_mod'   => $this->clamp($valenceMod),
            'intensity_mod' => $this->clamp($intensityMod),
        ];
    }

    private function applyTimeOfDay(Carbon $now, int &$energy, int &$valence): void
    {
        $hour = $now->hour;

        if ($hour >= 6 && $hour < 12) {
            // Morning
            $energy  += 10;
            $valence += 5;
        } elseif ($hour >= 22 || $hour < 6) {
            // Night
            $energy  -= 15;
            $valence += 5;
        }
        // Afternoon/evening (12h–22h): no modifier
    }

    private function applyDayOfWeek(Carbon $now, int &$energy, int &$valence): void
    {
        $hour      = $now->hour;
        $dayOfWeek = $now->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 5 = Friday, 6 = Saturday

        $isFridayOrSaturdayEvening = in_array($dayOfWeek, [5, 6], strict: true) && $hour >= 18;
        $isMondayMorning           = $dayOfWeek === 1 && $hour >= 6 && $hour < 12;

        if ($isFridayOrSaturdayEvening) {
            $energy  += 15;
            $valence += 10;
        } elseif ($dayOfWeek === 0) {
            // Sunday
            $energy -= 10;
        } elseif ($isMondayMorning) {
            $energy += 5;
        }
    }

    private function applyWeather(?string $weather, int &$energy, int &$valence): void
    {
        if ($weather === null) {
            return;
        }

        switch (strtolower($weather)) {
            case 'clear':
                $valence += 10;
                break;
            case 'rain':
                $valence -= 10;
                break;
            case 'snow':
                $valence -= 5;
                $energy  -= 5;
                break;
        }
    }

    private function clamp(int $value): int
    {
        return max(self::CLAMP_MIN, min(self::CLAMP_MAX, $value));
    }
}
