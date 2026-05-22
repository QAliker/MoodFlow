<?php

namespace App\Http\Controllers;

use App\Services\MoodPredictionService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodPredictionController extends Controller
{
    public function __construct(
        private readonly MoodPredictionService $predictionService,
        private readonly WeatherService $weatherService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'sometimes|numeric|between:-90,90',
            'lon' => 'sometimes|numeric|between:-180,180',
        ]);

        $weather = null;
        if ($request->filled('lat') && $request->filled('lon')) {
            $data    = $this->weatherService->getWeather((float) $request->lat, (float) $request->lon);
            $weather = $data['condition'] ?? null;
        }

        return response()->json(
            $this->predictionService->predict($request->user(), Carbon::now(), $weather)
        );
    }
}
