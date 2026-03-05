<?php

namespace App\Http\Controllers;

use App\Http\Requests\WeatherRequest;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    public function __construct(private readonly WeatherService $weatherService) {}

    public function __invoke(WeatherRequest $request): JsonResponse
    {
        $weather = $this->weatherService->getWeather(
            lat: (float) $request->validated('lat'),
            lon: (float) $request->validated('lon'),
        );

        return response()->json(['data' => $weather]);
    }
}
