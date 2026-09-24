<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_reset_request_is_generic_for_known_and_unknown_email_addresses(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $knownResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);
        $unknownResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $knownResponse->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'If that email is registered, a password reset link has been sent.',
            ]);
        $unknownResponse->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'If that email is registered, a password reset link has been sent.',
            ]);

        Notification::assertSentTo($user, ResetPasswordLink::class);
    }

    public function test_password_broker_stores_only_a_hash_of_the_reset_token(): void
    {
        $user = $this->makeUser();
        $token = Password::broker()->createToken($user);
        $storedToken = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->value('token');

        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));
    }

    public function test_password_reset_updates_the_password_revokes_tokens_and_consumes_the_link(): void
    {
        $user = $this->makeUser();
        $user->createToken('browser');
        $user->createToken('mobile');
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Password reset successfully. Please sign in with your new password.',
            ]);
        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.password_reset',
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'This password reset link is invalid or has expired.',
            ]);
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = $this->makeUser();
        $token = Password::broker()->createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'This password reset link is invalid or has expired.',
            ]);
    }

    public function test_reset_password_must_match_the_existing_strength_policy(): void
    {
        $user = $this->makeUser();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('slug', 'resident')->value('id'),
            'name' => 'Password Reset User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('OriginalPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
