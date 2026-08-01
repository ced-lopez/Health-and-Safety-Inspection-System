<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Http\Requests\Auth\VerifyRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SendVerificationCode;
use App\Rules\StrongPassword;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request)
    {
        $residentRole = Role::query()->where('slug', 'resident')->first();
        $channel = $request->validated('verification_channel') ?? 'email';

        $user = DB::transaction(function () use ($request, $residentRole, $channel) {
            $user = User::query()->create([
                'role_id' => $residentRole?->id,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => $request->validated('password'),
                'is_active' => true,
                'email_verified_at' => null,
            ]);

            $this->issueVerificationCode($user, $channel);

            $this->logAuthEvent($user, 'registered', $request);

            return $user;
        });

        $user->load('role');

        return $this->success([
            'user' => new UserResource($user),
            'verification_required' => true,
            'verification_channel' => $channel,
            'email' => $user->email,
        ], 'Registration successful. Please verify your account to continue.', 201);
    }

    public function verify(VerifyRequest $request)
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.'],
            ]);
        }

        if (! $user->verification_code_hash || $user->verification_code_expires_at?->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['The verification code has expired. Please request a new one.'],
            ]);
        }

        if (! Hash::check($request->validated('code'), $user->verification_code_hash)) {
            $user->increment('verification_attempts');

            if ($user->verification_attempts >= 5) {
                $this->clearVerificationCode($user);

                throw ValidationException::withMessages([
                    'code' => ['Too many incorrect attempts. Please request a new code.'],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => ['The verification code is incorrect.'],
            ]);
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_code_hash' => null,
            'verification_code_expires_at' => null,
            'verification_channel' => null,
            'verification_attempts' => 0,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->logAuthEvent($user, 'verified', $request);

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user->load('role')),
        ], 'Verification successful');
    }

    public function resendVerification(ResendVerificationRequest $request)
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.'],
            ]);
        }

        if ($user->email_verified_at) {
            return $this->error('This account is already verified.', 422);
        }

        $channel = $request->validated('verification_channel') ?? $user->verification_channel ?? 'email';

        $this->issueVerificationCode($user, $channel);

        $this->logAuthEvent($user, 'verification_resent', $request);

        return $this->success([
            'email' => $user->email,
            'verification_channel' => $channel,
        ], 'A new verification code has been sent.');
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

        $user->loadMissing('role');

        // Enforce portal separation: residents sign in through /login, while
        // administrators, barangay staff, and inspectors sign in through /admin/login.
        $portal = $request->validated('portal');
        $roleSlug = $user->role?->slug;

        if ($portal === 'resident' && $roleSlug !== 'resident') {
            throw ValidationException::withMessages([
                'email' => ['This account is an internal account. Barangay personnel must sign in through the internal portal.'],
            ]);
        }

        if ($portal === 'staff' && $roleSlug === 'resident') {
            throw ValidationException::withMessages([
                'email' => ['This account is a resident. Residents must sign in through the resident portal.'],
            ]);
        }

        // Resident accounts must confirm a fresh verification code on every login,
        // whether or not their email was previously verified.
        if ($user->role?->slug === 'resident') {
            $channel = $user->verification_channel ?? 'email';

            $this->issueVerificationCode($user, $channel);
            $this->logAuthEvent($user, 'verification_required', $request);

            $user->load('role');

            return $this->success([
                'user' => new UserResource($user),
                'verification_required' => true,
                'verification_channel' => $channel,
                'email' => $user->email,
            ], 'Please enter the verification code sent to your email to continue.');
        }

        if (! $user->email_verified_at) {
            $channel = $user->verification_channel ?? 'email';

            $this->issueVerificationCode($user, $channel);
            $this->logAuthEvent($user, 'verification_required', $request);

            $user->load('role');

            return $this->success([
                'user' => new UserResource($user),
                'verification_required' => true,
                'verification_channel' => $channel,
                'email' => $user->email,
            ], 'Please verify your email address to continue. A new verification code has been sent.');
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

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
        ]);

        $request->user()->update($validated);

        return $this->success(
            new UserResource($request->user()->load('role')),
            'Profile updated successfully'
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', new StrongPassword, 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        AuditLogger::log(
            $request->user(),
            'Authentication',
            'Password Changed',
            'User changed their account password',
            $request->user(),
            $request,
            event: 'auth.password_changed',
        );

        return $this->success(null, 'Password changed successfully');
    }

    private function issueVerificationCode(User $user, string $channel): void
    {
        $code = (string) random_int(100000, 999999);

        $user->update([
            'verification_code_hash' => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(10),
            'verification_channel' => $channel,
            'verification_attempts' => 0,
        ]);

        // The code is delivered by email (and stored in-app so it is visible to the
        // resident). If an SMS gateway is configured later, hook it up in the
        // notification channel so channel === 'sms' sends the code via SMS instead.
        $user->notify(new SendVerificationCode($code, $channel));
    }

    private function clearVerificationCode(User $user): void
    {
        $user->update([
            'verification_code_hash' => null,
            'verification_code_expires_at' => null,
            'verification_channel' => null,
            'verification_attempts' => 0,
        ]);
    }

    private function logAuthEvent(?User $user, string $event, Request $request): void
    {
        $actions = [
            'registered' => 'Register',
            'verified' => 'Verified',
            'verification_resent' => 'Verification Resent',
            'verification_required' => 'Verification Required',
            'login' => 'Login',
            'logout' => 'Logout',
        ];

        AuditLogger::log(
            $user,
            'Authentication',
            $actions[$event] ?? ucfirst($event),
            ucfirst(str_replace('_', ' ', $event)),
            $user,
            $request,
            event: "auth.{$event}",
        );
    }
}
