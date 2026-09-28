<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            // Defense in depth: an inactive account cannot retain a usable token,
            // even when it was disabled outside the dedicated API action.
            $user->tokens()->delete();

            return new JsonResponse([
                'message' => 'No autenticado.',
            ], 401);
        }

        return $next($request);
    }
}
