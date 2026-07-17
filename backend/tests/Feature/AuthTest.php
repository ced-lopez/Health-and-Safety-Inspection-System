<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
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
        $payload = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '09151112222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
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
                ],
            ]);

        // Assert user was created in the database and assigned the default 'staff' role
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
            'phone' => '09151112222',
            'is_active' => true,
        ]);

        $user = User::query()->where('email', 'john.doe@example.com')->first();
        $staffRole = Role::query()->where('slug', 'staff')->first();
        $this->assertEquals($staffRole->id, $user->role_id);

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.registered',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
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
        $staffRole = Role::query()->where('slug', 'staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
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
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'role',
                    ],
                ],
            ]);

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_login_fails_with_incorrect_credentials(): void
    {
        $staffRole = Role::query()->where('slug', 'staff')->first();
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
        $staffRole = Role::query()->where('slug', 'staff')->first();
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
                'email' => ['This account has been deactivated. Contact your administrator.']
            ]);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $staffRole = Role::query()->where('slug', 'staff')->first();
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
        $staffRole = Role::query()->where('slug', 'staff')->first();
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
            'Authorization' => 'Bearer ' . $token,
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
}
