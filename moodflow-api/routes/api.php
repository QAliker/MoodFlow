<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/genres', [GenreController::class, 'index']);
Route::get('/weather', WeatherController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me/genres', [UserController::class, 'updateGenres']);
});
