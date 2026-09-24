<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenNotIdle
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = null;
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $token = $user->currentAccessToken();
        } elseif ($bearer = $request->bearerToken()) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            $user = $token?->tokenable;
        }

        if ($user && $token) {
            if (! $user->relationLoaded('role')) {
                $user->loadMissing('role');
            }

            $isStaffType = $user->hasRole('administrator', 'barangay_staff', 'inspector');

            if ($isStaffType) {
                $idleMinutes = (int) config('sanctum.staff_idle_minutes', 60);

                if ($idleMinutes > 0) {
                    $lastUsed = $token->last_used_at ?? $token->created_at;

                    if ($lastUsed && $lastUsed->diffInMinutes(now()) > $idleMinutes) {
                        $token->delete();

                        return response()->json([
                            'success' => false,
                            'message' => 'Session expired due to inactivity. Please log in again.',
                        ], 401);
                    }
                }
            }
        }

        $response = $next($request);

        if ($user && $token && $token->exists) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $response;
    }
}
