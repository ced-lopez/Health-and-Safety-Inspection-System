<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', 'resident')->firstOrFail();
        $this->user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Notif User',
            'email' => 'notif@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    public function test_user_can_delete_own_notification(): void
    {
        $notification = $this->user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\ApplicationSubmitted',
            'data' => ['message' => 'hello'],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $other = User::query()->create([
            'role_id' => $this->user->role_id,
            'name' => 'Other User',
            'email' => 'other2@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $notification = $other->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\ApplicationSubmitted',
            'data' => ['message' => 'hello'],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }
}
