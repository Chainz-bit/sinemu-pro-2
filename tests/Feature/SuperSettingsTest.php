<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_settings_page(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.settings'));

        $response->assertOk();
        $response->assertSee('Pengaturan Super Admin');
        $response->assertSee('Identitas Akun');
    }

    public function test_super_admin_can_update_settings(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'super_admin')
            ->put(route('super.settings.update'), [
                'nama' => 'Super Pengelola',
                'username' => 'super-pengelola',
                'email' => 'super-pengelola@example.com',
            ]);

        $response->assertRedirect(route('super.settings'));
        $response->assertSessionHas('status', 'Pengaturan super admin berhasil diperbarui.');

        $superAdmin->refresh();

        $this->assertSame('Super Pengelola', $superAdmin->nama);
        $this->assertSame('super-pengelola', $superAdmin->username);
        $this->assertSame('super-pengelola@example.com', $superAdmin->email);
    }

    public function test_super_settings_update_validates_required_fields(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->from(route('super.settings'))
            ->actingAs($superAdmin, 'super_admin')
            ->put(route('super.settings.update'), [
                'email' => 'bukan-email',
            ]);

        $response->assertRedirect(route('super.settings'));
        $response->assertSessionHasErrors([
            'nama',
            'username',
            'email',
        ]);
    }

    public function test_super_admin_can_open_history_page_and_filter_it(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Riwayat Aktif',
            'email' => 'admin-riwayat-aktif@example.com',
            'username' => 'admin-riwayat-aktif',
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi Aktif',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Riwayat No. 1',
            'status_verifikasi' => 'active',
            'verified_at' => now()->subHour(),
        ]);

        Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Riwayat Pending',
            'email' => 'admin-riwayat-pending@example.com',
            'username' => 'admin-riwayat-pending',
            'password' => Hash::make('password123'),
            'instansi' => 'Instansi Pending',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Riwayat No. 2',
            'status_verifikasi' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin, 'super_admin')
            ->get(route('super.settings.history', [
                'status' => 'active',
                'search' => 'Aktif',
            ]));

        $response->assertOk();
        $response->assertSee('Log / Riwayat');
        $response->assertSee('Admin Riwayat Aktif');
        $response->assertViewHas('histories', function ($histories) {
            return $histories->count() === 1
                && $histories->first()?->nama === 'Admin Riwayat Aktif';
        });
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'nama' => 'Super Settings',
            'email' => 'super-settings@example.com',
            'username' => 'super-settings',
            'password' => Hash::make('password123'),
        ]);
    }
}
