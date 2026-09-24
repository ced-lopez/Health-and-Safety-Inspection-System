<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\SendVerificationCode;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles as they are required for registration and profile structures
        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_register_successfully(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '09151112222',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'is_active',
                        'role' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                        ],
                        'created_at',
                    ],
                    'verification_required',
                    'verification_channel',
                    'email',
                ],
            ])
            ->assertJsonFragment([
                'verification_required' => true,
                'verification_channel' => 'email',
            ]);

        $user = User::query()->where('email', 'john.doe@example.com')->first();

        // New registrations are assigned the default 'resident' role and are unverified
        $residentRole = Role::query()->where('slug', 'resident')->first();
        $this->assertEquals($residentRole->id, $user->role_id);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->verification_code_hash);

        // A verification code notification is dispatched
        Notification::assertSentTo($user, SendVerificationCode::class);

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.registered',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_user_can_verify_registration_code(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '09151113333',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/v1/auth/register', $payload);

        $user = User::query()->where('email', 'jane.doe@example.com')->first();

        Notification::assertSentTo($user, SendVerificationCode::class, function ($notification) use ($user) {
            $response = $this->postJson('/api/v1/auth/verify', [
                'email' => $user->email,
                'code' => $notification->code,
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'token',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'is_active',
                            'role',
                        ],
                    ],
                ]);

            $user->refresh();

            $this->assertNotNull($user->email_verified_at);
            $this->assertNull($user->verification_code_hash);

            return true;
        });
    }

    public function test_verification_fails_with_wrong_code(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Joe Doe',
            'email' => 'joe.doe@example.com',
            'phone' => '09151114444',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/v1/auth/register', $payload);

        $user = User::query()->where('email', 'joe.doe@example.com')->first();

        $response = $this->postJson('/api/v1/auth/verify', [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_unverified_user_login_requires_verification(): void
    {
        Notification::fake();

        $residentRole = Role::query()->where('slug', 'resident')->first();
        $user = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Unverified Resident',
            'email' => 'unverified@example.com',
            'phone' => '09151115555',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $payload = [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        // Login for an unverified account requires verification and issues no token
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'verification_required',
                    'verification_channel',
                    'email',
                ],
            ])
            ->assertJsonFragment([
                'verification_required' => true,
                'verification_channel' => 'email',
            ])
            ->assertJsonMissingPath('data.token');

        // A fresh verification code is issued
        Notification::assertSentTo($user, SendVerificationCode::class);

        // The issued code lets the resident verify and obtain a token
        $code = Notification::sent($user, SendVerificationCode::class)->first()->code;

        $this->postJson('/api/v1/auth/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertStatus(200)->assertJsonStructure(['data' => ['token']]);

        $this->assertNotNull($user->refresh()->email_verified_at);

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.verification_required',
        ]);
    }

    public function test_registration_validation_fails_with_invalid_data(): void
    {
        // Missing name, invalid email, password mismatch
        $payload = [
            'email' => 'not-an-email',
            'password' => 'pass',
            'password_confirmation' => 'different',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_successfully(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $payload = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'role',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'role',
                    ],
                ],
            ]);

        $response->assertJsonPath('data.role', 'barangay_staff');

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_login_fails_with_incorrect_credentials(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $payload = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Deactivated User',
            'email' => 'deactivated@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $payload = [
            'email' => 'deactivated@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'email' => ['This account has been deactivated. Contact your administrator.'],
            ]);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Profile User',
            'email' => 'profile@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => 'profile@example.com',
                'name' => 'Profile User',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role' => [
                        'id',
                        'name',
                        'slug',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_retrieve_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_user_can_logout_successfully(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Logout User',
            'email' => 'logout@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Generate token and act as user with token
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        // Assert token was deleted
        $this->assertCount(0, $user->tokens);

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.logout',
        ]);
    }

    public function test_verified_resident_login_requires_fresh_verification_code(): void
    {
        Notification::fake();

        $residentRole = Role::query()->where('slug', 'resident')->first();
        $user = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Returning Resident',
            'email' => 'returning@example.com',
            'phone' => '09151116666',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'returning@example.com',
            'password' => 'password123',
        ]);

        // A verified resident still receives a fresh code and no token on login
        $response->assertStatus(200)
            ->assertJsonFragment([
                'verification_required' => true,
                'verification_channel' => 'email',
            ])
            ->assertJsonMissingPath('data.token');

        Notification::assertSentTo($user, SendVerificationCode::class);

        // The resident must enter the freshly issued code before a token is granted
        $code = Notification::sent($user, SendVerificationCode::class)->first()->code;

        $this->postJson('/api/v1/auth/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertStatus(200)->assertJsonStructure(['data' => ['token']]);

        // A wrong code is rejected even though the email was already verified
        $this->postJson('/api/v1/auth/login', [
            'email' => 'returning@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/verify', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_staff_login_is_not_blocked_by_resident_verification(): void
    {
        Notification::fake();

        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Verified Staff',
            'email' => 'staffverified@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'staffverified@example.com',
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonStructure(['data' => ['token']]);

        Notification::assertNothingSent();
    }

    public function test_resident_can_login_through_the_unified_endpoint(): void
    {
        Notification::fake();

        $residentRole = Role::query()->where('slug', 'resident')->first();
        $user = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Unified Resident User',
            'email' => 'unified.resident@example.com',
            'phone' => '09151117777',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unified.resident@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'resident')
            ->assertJsonPath('data.verification_required', true);

        Notification::assertSentTo($user, SendVerificationCode::class);
    }

    public function test_staff_can_login_through_the_unified_endpoint(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Unified Staff User',
            'email' => 'unified.staff@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unified.staff@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'barangay_staff')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_unified_login_enforces_a_single_session_for_each_internal_role(): void
    {
        foreach (['administrator', 'barangay_staff', 'inspector'] as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            $user = User::query()->create([
                'role_id' => $role->id,
                'name' => "Unified {$roleSlug} User",
                'email' => "unified.{$roleSlug}@example.com",
                'password' => Hash::make('password123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->createToken('previous-session');

            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertOk()
                ->assertJsonPath('data.role', $roleSlug)
                ->assertJsonStructure(['data' => ['token']]);

            $this->assertSame(1, $user->tokens()->count());
        }
    }
}
