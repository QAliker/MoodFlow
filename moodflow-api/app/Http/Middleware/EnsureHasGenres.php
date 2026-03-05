<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasGenres
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->genres()->doesntExist()) {
            return response()->json([
                'message' => 'Please select your genres first.',
                'redirect' => '/onboarding/genres',
            ], 403);
        }

        return $next($request);
    }
}
