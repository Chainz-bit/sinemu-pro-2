<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
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
        $globalAdmin = $this->createAdmin(null, 'Admin Global', 'active', now()->subMinutes(30));
        $this->createAdmin($otherSuperAdmin, 'Admin Milik Super Lain', 'pending', now());

        $response = $this->actingAs($superAdmin, 'super_admin')->get(route('super.dashboard'));

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total' => 4,
            'pending' => 1,
            'active' => 2,
            'rejected' => 1,
            'newThisWeek' => 4,
        ]);

        $priorityAdmins = $response->viewData('priorityAdmins');
        $newestAdmins = $response->viewData('newestAdmins');
        $latestActivities = $response->viewData('latestActivities');

        $this->assertSame([$pendingAdmin->id], $priorityAdmins->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$pendingAdmin->id, $activeAdmin->id, $rejectedAdmin->id, $globalAdmin->id],
            $newestAdmins->pluck('id')->all()
        );
        $this->assertFalse($newestAdmins->pluck('nama')->contains('Admin Milik Super Lain'));
        $this->assertTrue($latestActivities->pluck('id')->contains($activeAdmin->id));

        $response->assertSee('Admin Pending');
        $response->assertSee('Admin Global');
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
        string $status,
        mixed $createdAt
    ): Admin {
        return Admin::query()->create([
            'super_admin_id' => $superAdmin?->id,
            'nama' => $name,
            'email' => str($name)->slug('-') . '@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus ' . $name,
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Super Dashboard No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : now()->subMinutes(10),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
