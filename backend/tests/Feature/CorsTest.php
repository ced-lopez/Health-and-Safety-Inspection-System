<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_returns_cors_headers_for_frontend_origin(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Cors User',
            'email' => 'cors@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'cors@example.com',
            'password' => 'password123',
            'portal' => 'staff',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_protected_route_returns_cors_headers_for_frontend_origin(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Cors User 2',
            'email' => 'cors2@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
