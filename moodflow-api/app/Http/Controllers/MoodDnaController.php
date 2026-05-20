<?php

namespace App\Http\Controllers;

use App\Services\MoodDnaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodDnaController extends Controller
{
    public function __construct(
        private readonly MoodDnaService $dnaService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(['data' => $this->dnaService->generateDna($user)]);
    }
}
