<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request)
    {
        $staffRole = Role::query()->where('slug', 'staff')->first();

        $user = User::query()->create([
            'role_id' => $staffRole?->id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->load('role');
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->logAuthEvent($user, 'registered', $request);

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Registration successful', 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Contact your administrator.'],
            ]);
        }

        $user->load('role');
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->logAuthEvent($user, 'login', $request);

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $request->user()->currentAccessToken()?->delete();

        $this->logAuthEvent($user, 'logout', $request);

        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request)
    {
        $request->user()->load('role');

        return $this->success(
            new UserResource($request->user()),
            'Authenticated user retrieved'
        );
    }

    private function logAuthEvent(?User $user, string $event, Request $request): void
    {
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'event' => "auth.{$event}",
            'auditable_type' => User::class,
            'auditable_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
