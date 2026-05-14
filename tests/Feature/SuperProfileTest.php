<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_profile_summary_and_activity_are_scoped_to_current_super_admin(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $otherSuperAdmin = $this->createSuperAdmin(
            email: 'other-profile-super@example.com',
            username: 'other-profile-super'
        );

        $this->createAdmin($superAdmin, 'Admin Profile Pending', 'pending');
        $this->createAdmin($superAdmin, 'Admin Profile Aktif', 'active');
        $this->createAdmin(null, 'Admin Profile Global', 'active');
        $this->createAdmin($otherSuperAdmin, 'Admin Profile Super Lain', 'active');

        $response = $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.profile'));

        $response->assertOk();
        $response->assertViewHas('totalAdmin', 3);
        $response->assertViewHas('pendingAdmin', 1);
        $response->assertViewHas('activeAdmin', 2);

        $activities = $response->viewData('recentActivities');

        $this->assertTrue($activities->pluck('title')->contains(fn (string $title) => str_contains($title, 'Admin Profile Global')));
        $this->assertFalse($activities->pluck('title')->contains(fn (string $title) => str_contains($title, 'Admin Profile Super Lain')));
    }

    private function createSuperAdmin(
        string $email = 'super-profile@example.com',
        string $username = 'super-profile'
    ): SuperAdmin {
        return SuperAdmin::query()->create([
            'nama' => 'Super Profile',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
        ]);
    }

    private function createAdmin(?SuperAdmin $superAdmin, string $name, string $status): Admin
    {
        return Admin::query()->create([
            'super_admin_id' => $superAdmin?->id,
            'nama' => $name,
            'email' => str($name)->slug('-') . '@example.com',
            'username' => (string) str($name)->slug('-'),
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi ' . $name,
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Profile No. 1',
            'status_verifikasi' => $status,
            'verified_at' => $status === 'pending' ? null : now(),
        ]);
    }
}
