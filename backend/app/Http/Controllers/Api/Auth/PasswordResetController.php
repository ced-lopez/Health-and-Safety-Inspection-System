<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends BaseApiController
{
    private const RESET_LINK_MESSAGE = 'If that email is registered, a password reset link has been sent.';

    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        try {
            Password::sendResetLink([
                'email' => $validated['email'],
            ]);
        } catch (Throwable) {
            // Do not leave a usable token behind when delivery did not complete.
            if ($user) {
                Password::broker()->deleteToken($user);
            }

            Log::warning('Password reset email could not be dispatched.');
        }

        // The response is deliberately identical for existing and unknown addresses.
        return $this->success(null, self::RESET_LINK_MESSAGE);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', new StrongPassword, 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                AuditLogger::log(
                    $user,
                    'Authentication',
                    'Password Reset',
                    'User reset their password using an email link',
                    $user,
                    $request,
                    event: 'auth.password_reset',
                );
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password reset successfully. Please sign in with your new password.');
        }

        return $this->error('This password reset link is invalid or has expired.', 422);
    }
}
