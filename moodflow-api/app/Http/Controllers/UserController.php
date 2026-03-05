<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGenresRequest;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function me(): JsonResponse
    {
        return response()->json([
            'data' => auth()->user()->load('genres'),
        ]);
    }

    public function updateGenres(UpdateGenresRequest $request): JsonResponse
    {
        $user = auth()->user();
        $user->genres()->sync($request->genre_ids);

        return response()->json([
            'data'    => $user->load('genres'),
            'message' => 'Genres updated successfully.',
        ]);
    }
}
