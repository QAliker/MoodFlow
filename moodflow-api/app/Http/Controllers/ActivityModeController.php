<?php

namespace App\Http\Controllers;

use App\Services\ActivityModeService;
use Illuminate\Http\JsonResponse;

class ActivityModeController extends Controller
{
    public function __construct(private readonly ActivityModeService $activityMode) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_values($this->activityMode->all()),
        ]);
    }
}
