<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function __construct(
        private readonly PendingRequest $http,
        private readonly string $apiKey,
    ) {}

    /**
     * Fetch current weather for the given coordinates.
     *
     * Returns null when the API key is missing, on timeout, or on HTTP error.
     *
     * @return array{condition: string, temperature: float, description: string}|null
     */
    public function getWeather(float $lat, float $lon): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        $key = sprintf('weather:%s:%s', round($lat, 2), round($lon, 2));

        return Cache::remember($key, 1800, function () use ($lat, $lon): ?array {
            try {
                $response = $this->http->get('/weather', [
                    'lat'   => $lat,
                    'lon'   => $lon,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                ]);

                if (! $response->successful()) {
                    Log::warning('OpenWeatherMap HTTP error', [
                        'status' => $response->status(),
                        'lat'    => $lat,
                        'lon'    => $lon,
                    ]);

                    return null;
                }

                return $this->normalize($response->json());
            } catch (ConnectionException $e) {
                Log::warning('OpenWeatherMap connection error', [
                    'error' => $e->getMessage(),
                    'lat'   => $lat,
                    'lon'   => $lon,
                ]);

                return null;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{condition: string, temperature: float, description: string}
     */
    private function normalize(array $raw): array
    {
        $main        = $raw['weather'][0]['main'] ?? 'Clouds';
        $description = $raw['weather'][0]['description'] ?? '';
        $temperature = (float) ($raw['main']['temp'] ?? 0.0);

        return [
            'condition'   => $this->normalizeCondition($main),
            'temperature' => $temperature,
            'description' => $description,
        ];
    }

    private function normalizeCondition(string $main): string
    {
        return match (strtolower($main)) {
            'clear'               => 'clear',
            'clouds'              => 'clouds',
            'rain', 'drizzle'     => 'rain',
            'snow'                => 'snow',
            default               => 'clouds',
        };
    }
}
