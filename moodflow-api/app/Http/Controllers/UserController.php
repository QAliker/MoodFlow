<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function me(): JsonResponse
    {
        return response()->json([
            'data' => auth()->user()->load('genres'),
        ]);
    }
}
