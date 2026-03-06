<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeMoodRequest;
use App\Services\MoodService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodController extends Controller
{
    public function __construct(
        private readonly MoodService $moodService,
        private readonly WeatherService $weatherService,
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
            'source'    => $data['source'],
            'emotion'   => $data['emotion'] ?? null,
            'energy'    => $data['energy'],
            'valence'   => $data['valence'],
            'intensity' => $data['intensity'],
            'weather'   => $weather,
        ];

        $result = $this->moodService->recommend($request->user(), $moodInput);

        return response()->json([
            'data' => [
                'mood'   => $result['mood'],
                'tracks' => $result['tracks'],
            ],
            'message' => 'Mood analyzed successfully.',
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $moods = $request->user()
            ->moods()
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $moods,
        ]);
    }
}
