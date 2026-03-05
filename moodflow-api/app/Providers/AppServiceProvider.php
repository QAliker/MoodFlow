<?php

namespace App\Providers;

use App\Services\DeezerService;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
