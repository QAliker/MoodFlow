<?php

namespace App\Providers;

use App\Services\DeezerService;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DeezerService::class, function (): DeezerService {
            return new DeezerService(
                Http::baseUrl(config('services.deezer.base_url'))
                    ->timeout(5)
                    ->acceptJson(),
            );
        });

        $this->app->singleton(WeatherService::class, function (): WeatherService {
            return new WeatherService(
                Http::baseUrl(config('services.openweathermap.base_url'))
                    ->timeout(5)
                    ->acceptJson(),
                (string) config('services.openweathermap.key'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
