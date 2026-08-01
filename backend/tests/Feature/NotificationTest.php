<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\SendVerificationCode;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_notifications_as_array(): void
    {
        $residentRole = Role::query()->where('slug', 'resident')->first();
        $user = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Notif User',
            'email' => 'notif@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $user->notify(new SendVerificationCode('123456', 'email'));

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications?per_page=50');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'notifications',
                    'unread_count',
                    'meta' => [
                        'total',
                    ],
                ],
            ]);

        // notifications must serialize as a plain array, not a paginator object
        $payload = $response->json('data.notifications');
        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);
        $this->assertEquals('SendVerificationCode', $payload[0]['type']);
        $this->assertStringContainsString('123456', $payload[0]['data']['message']);
        $this->assertNull($payload[0]['read_at']);
        $this->assertEquals(1, $response->json('data.unread_count'));
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $residentRole = Role::query()->where('slug', 'resident')->first();
        $user = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Notif User 2',
            'email' => 'notif2@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $user->notify(new SendVerificationCode('111111', 'email'));
        $user->notify(new SendVerificationCode('222222', 'email'));

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/notifications/read-all');

        $response->assertStatus(200);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }
}
