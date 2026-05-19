<?php

namespace App\Http\Controllers;

use App\Services\ActivityModeService;
use Illuminate\Http\JsonResponse;

class ActivityModeController extends Controller
{
    public function __construct(private readonly ActivityModeService $activityMode) {}

    public function index(): JsonResponse
    {
        $modes = array_map(
            fn (array $m) => [
                'slug'        => $m['slug'],
                'label'       => $m['label'],
                'emoji'       => $m['emoji'],
                'description' => $m['description'],
            ],
            array_values($this->activityMode->all()),
        );

        return response()->json(['data' => $modes]);
    }
}
