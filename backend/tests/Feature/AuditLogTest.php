<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected User $resident;

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'auditadmin@example.com');
        $this->staff = $this->makeUser('barangay_staff', 'auditstaff@example.com');
        $this->resident = $this->makeUser('resident', 'auditresident@example.com');
        $this->actor = $this->makeUser('inspector', 'auditactor@example.com');
    }

    public function test_guest_cannot_access_audit_logs(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertStatus(401);
    }

    public function test_resident_cannot_access_audit_logs(): void
    {
        $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(403);
    }

    public function test_staff_can_view_audit_logs(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/audit-logs')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['logs', 'meta' => ['total']]]);
    }

    public function test_staff_cannot_filter_audit_logs(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/audit-logs?event=auth')
            ->assertStatus(403);
    }

    public function test_staff_cannot_access_admin_audit_log_endpoints(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/audit-logs/filters')
            ->assertStatus(403);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/audit-logs/export')
            ->assertStatus(403);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/audit-logs/archive')
            ->assertStatus(403);
    }

    public function test_admin_can_list_audit_logs_as_array(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'inspection_request.reviewed',
            'module' => 'Inspection Requests',
            'action' => 'Reviewed',
            'description' => 'Reviewed inspection request BRGY-REQ-1',
            'auditable_type' => InspectionRequest::class,
            'auditable_id' => 42,
            'old_values' => ['status' => 'submitted'],
            'new_values' => ['status' => 'approved_for_inspection'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'logs',
                    'meta' => ['total'],
                ],
            ]);

        $logs = $response->json('data.logs');
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertSame('inspection_request.reviewed', $logs[0]['event']);
        $this->assertSame('Inspection Requests', $logs[0]['module']);
        $this->assertSame('Reviewed', $logs[0]['action']);
        $this->assertSame('Reviewed inspection request BRGY-REQ-1', $logs[0]['description']);
        $this->assertSame($this->actor->name, $logs[0]['user']['name']);
        $this->assertSame('approved_for_inspection', $logs[0]['new_values']['status']);
        $this->assertSame('127.0.0.1', $logs[0]['ip_address']);
    }

    public function test_admin_can_filter_logs_by_event(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'document.verified',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs?event=auth');

        $this->assertSame(1, $response->json('data.meta.total'));
        $this->assertSame('auth.login', $response->json('data.logs.0.event'));
    }

    public function test_admin_can_filter_logs_by_module(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
            'module' => 'Authentication',
            'action' => 'Login',
        ]);
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'document.verified',
            'module' => 'Documents',
            'action' => 'Verified',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs?module=Documents');

        $this->assertSame(1, $response->json('data.meta.total'));
        $this->assertSame('Documents', $response->json('data.logs.0.module'));
    }

    public function test_admin_can_fetch_filter_options(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
            'module' => 'Authentication',
            'action' => 'Login',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs/filters');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['modules', 'actions', 'users']]);

        $this->assertContains('Authentication', $response->json('data.modules'));
        $this->assertContains('Login', $response->json('data.actions'));
    }

    public function test_admin_can_export_audit_logs_as_csv(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs/export?format=csv')
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_export_audit_logs_as_excel(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs/export?format=excel')
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_export_audit_logs_as_pdf(): void
    {
        AuditLog::query()->create([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs/export?format=pdf')
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_archive_old_logs_without_deleting_them(): void
    {
        $old = new AuditLog([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);
        $old->created_at = Carbon::now()->subDays(60);
        $old->save();

        $recent = new AuditLog([
            'user_id' => $this->actor->id,
            'event' => 'auth.logout',
        ]);
        $recent->created_at = Carbon::now()->subDays(1);
        $recent->save();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/audit-logs/archive', ['before' => Carbon::now()->subDays(30)->toDateString()]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.archived_count'));

        $this->assertSame(2, AuditLog::count());

        $this->assertNotNull(AuditLog::query()->where('event', 'auth.login')->first()->archived_at);
        $this->assertNull(AuditLog::query()->where('event', 'auth.logout')->first()->archived_at);

        $this->assertSame(1, $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs')->json('data.meta.total'));

        $this->assertSame(1, $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/audit-logs?archived=1')->json('data.meta.total'));
    }

    public function test_admin_archive_defaults_to_thirty_days(): void
    {
        $old = new AuditLog([
            'user_id' => $this->actor->id,
            'event' => 'auth.login',
        ]);
        $old->created_at = Carbon::now()->subDays(45);
        $old->save();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/audit-logs/archive');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.archived_count'));
        $this->assertSame(1, AuditLog::count());
    }

    protected function makeUser(string $roleSlug, string $email): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'email' => $email,
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }
}
