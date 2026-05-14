<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\Super\Admins\AdminApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_dashboard_shows_summary_priority_and_newest_admins(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $otherSuperAdmin = $this->createSuperAdmin(
            email: 'other-super-dashboard@example.com',
            username: 'other-super-dashboard'
        );

        $pendingAdmin = $this->createAdmin($superAdmin, 'Admin Pending', 'pending', now()->subHour());
        $activeAdmin = $this->createAdmin($superAdmin, 'Admin Aktif', 'active', now()->subDays(2));
        $rejectedAdmin = $this->createAdmin($superAdmin, 'Admin Ditolak', 'rejected', now()->subDays(3));
        $inactiveAdmin = $this->createAdmin($superAdmin, 'Admin Nonaktif', 'inactive', now()->subDays(4));
        $globalAdmin = $this->createAdmin(null, 'Admin Global', 'active', now()->subMinutes(30));
        $oldAdmin = $this->createAdmin($superAdmin, 'Admin Lama', 'active', now()->subDays(8));
        $this->createAdmin($otherSuperAdmin, 'Admin Milik Super Lain', 'pending', now());
        User::factory()->create();

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.dashboard'));

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total' => 6,
            'pending' => 1,
            'active' => 3,
            'rejected' => 1,
            'inactive' => 1,
            'newThisWeek' => 5,
        ]);

        $priorityAdmins = $response->viewData('priorityAdmins');
        $newestAdmins = $response->viewData('newestAdmins');
        $latestActivities = $response->viewData('latestActivities');

        $this->assertSame([$pendingAdmin->id], $priorityAdmins->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$pendingAdmin->id, $activeAdmin->id, $rejectedAdmin->id, $globalAdmin->id],
            $newestAdmins->pluck('id')->all()
        );
        $this->assertCount(4, $newestAdmins);
        $this->assertFalse($newestAdmins->pluck('nama')->contains('Admin Milik Super Lain'));
        $this->assertTrue($latestActivities->pluck('id')->contains($activeAdmin->id));
        $this->assertTrue($latestActivities->pluck('id')->contains($inactiveAdmin->id));
        $this->assertFalse($latestActivities->pluck('id')->contains($pendingAdmin->id));
        $this->assertCount(4, $latestActivities);

        $response->assertSee('Admin Pending');
        $response->assertSee('Admin Global');
    }

    public function test_super_dashboard_requires_super_admin_guard(): void
    {
        $this->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));

        $admin = $this->createAdmin(null, 'Admin Guard Dashboard', 'active', now());

        $this->actingAs($admin, 'admin')
            ->get(route('super.dashboard'))
            ->assertRedirect(route('super.login'));
    }

    public function test_super_dashboard_summary_changes_after_status_actions(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $pendingToActive = $this->createAdmin($superAdmin, 'Admin Pending Ke Aktif', 'pending', now());
        $pendingToRejected = $this->createAdmin($superAdmin, 'Admin Pending Ke Tolak', 'pending', now());
        $activeToInactive = $this->createAdmin($superAdmin, 'Admin Aktif Ke Nonaktif', 'active', now());
        $inactiveToActive = $this->createAdmin($superAdmin, 'Admin Nonaktif Ke Aktif', 'inactive', now());

        /** @var AdminApprovalService $approvalService */
        $approvalService = app(AdminApprovalService::class);
        $approvalService->accept($pendingToActive, $superAdmin->id);
        $approvalService->reject($pendingToRejected, 'Data perlu diperbaiki', $superAdmin->id);
        $approvalService->deactivate($activeToInactive, $superAdmin->id);
        $approvalService->accept($inactiveToActive, $superAdmin->id);

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.dashboard'));

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total' => 4,
            'pending' => 0,
            'active' => 2,
            'rejected' => 1,
            'inactive' => 1,
            'newThisWeek' => 4,
        ]);

        $this->assertCount(0, $response->viewData('priorityAdmins'));
    }

    private function createSuperAdmin(
        string $email = 'super-dashboard@example.com',
        string $username = 'super-dashboard'
    ): SuperAdmin {
        return SuperAdmin::query()->create([
            'nama' => 'Super Dashboard',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
        ]);
    }

    private function createAdmin(
        ?SuperAdmin $superAdmin,
        string $name,
        ?string $status,
        mixed $createdAt
    ): Admin {
        $admin = Admin::query()->create([
            'super_admin_id' => $superAdmin?->id,
            'nama' => $name,
            'email' => str($name)->slug('-') . '@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus ' . $name,
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Super Dashboard No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : $createdAt,
        ]);

        $admin->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $admin->refresh();
    }
}
