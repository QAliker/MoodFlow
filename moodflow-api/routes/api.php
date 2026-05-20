<?php

use App\Http\Controllers\ActivityModeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/genres', [GenreController::class, 'index']);
Route::get('/activity-modes', [ActivityModeController::class, 'index']);
Route::get('/weather', WeatherController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me/genres', [UserController::class, 'updateGenres']);

    Route::middleware('has.genres')->group(function (): void {
        Route::post('/mood/analyze', [MoodController::class, 'analyze']);
        Route::get('/mood/history', [MoodController::class, 'history']);
        Route::get('/mood/stats', [MoodController::class, 'stats']);
    });
});
