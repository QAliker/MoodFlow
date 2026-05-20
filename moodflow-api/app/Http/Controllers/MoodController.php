<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeMoodRequest;
use App\Http\Requests\MoodHistoryRequest;
use App\Http\Requests\MoodStatsRequest;
use App\Services\MoodDashboardService;
use App\Services\MoodService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class MoodController extends Controller
{
    public function __construct(
        private readonly MoodService          $moodService,
        private readonly WeatherService       $weatherService,
        private readonly MoodDashboardService $dashboardService,
    ) {}

    public function analyze(AnalyzeMoodRequest $request): JsonResponse
    {
        $data = $request->validated();

        $weather = null;
        if (isset($data['lat'], $data['lon'])) {
            $weatherData = $this->weatherService->getWeather(
                (float) $data['lat'],
                (float) $data['lon'],
            );
            $weather = $weatherData['condition'] ?? null;
        }

        $moodInput = [
            'source'        => $data['source'],
            'emotion'       => $data['emotion'] ?? null,
            'energy'        => $data['energy'],
            'valence'       => $data['valence'],
            'intensity'     => $data['intensity'],
            'weather'       => $weather,
            'activity_mode' => $data['activity_mode'] ?? null,
        ];

        $result = $this->moodService->recommend($request->user(), $moodInput);

        $suggestedMode = null;
        if ($weather === 'rain' && ($data['activity_mode'] ?? null) === null) {
            $suggestedMode = 'rainy_day';
        }

        return response()->json([
            'data' => [
                'mood'           => $result['mood'],
                'tracks'         => $result['tracks'],
                'suggested_mode' => $suggestedMode,
            ],
            'message' => 'Mood analyzed successfully.',
        ], 201);
    }

    public function history(MoodHistoryRequest $request): JsonResponse
    {
        $period = $request->validated()['period'] ?? 'all';

        $moods = $request->user()
            ->moods()
            ->forPeriod($period)
            ->latest()
            ->paginate(20)
            ->through(fn($mood) => [
                'id'            => $mood->id,
                'date'          => $mood->created_at->toDateString(),
                'time'          => $mood->created_at->format('H:i'),
                'source'        => $mood->source,
                'emotion'       => $mood->emotion,
                'energy'        => $mood->energy,
                'valence'       => $mood->valence,
                'intensity'     => $mood->intensity,
                'activity_mode' => $mood->context['activity_mode'] ?? null,
                'weather'       => $mood->context['weather'] ?? null,
                'tracks'        => collect($mood->tracks ?? [])
                    ->map(fn(array $t) => ['title' => $t['title'], 'artist' => $t['artist']])
                    ->all(),
            ]);

        return response()->json(['data' => $moods]);
    }

    public function stats(MoodStatsRequest $request): JsonResponse
    {
        $period = $request->validated()['period'] ?? 'month';
        $stats  = $this->dashboardService->stats($request->user(), $period);

        return response()->json(['data' => $stats]);
    }
}
