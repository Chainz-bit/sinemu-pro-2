<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_settings(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.update'), [
                'kecamatan' => 'Indramayu Kota',
                'nama' => 'Camat Baru',
                'email' => 'camat-baru@example.com',
                'alamat_lengkap' => 'Jl. Kecamatan Baru No. 5',
            ]);

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHas('status', 'Pengaturan sistem berhasil diperbarui.');

        $admin->refresh();

        $this->assertSame('Indramayu Kota', $admin->kecamatan);
        $this->assertSame('Camat Baru', $admin->nama);
        $this->assertSame('camat-baru@example.com', $admin->email);
        $this->assertSame('Jl. Kecamatan Baru No. 5', $admin->alamat_lengkap);
    }

    public function test_settings_update_validates_required_fields(): void
    {
        $admin = $this->createAdmin();

        $response = $this->from(route('admin.settings'))
            ->actingAs($admin, 'admin')
            ->put(route('admin.settings.update'), [
                'email' => 'bukan-email',
            ]);

        $response->assertRedirect(route('admin.settings'));
        $response->assertSessionHasErrors([
            'kecamatan',
            'nama',
            'email',
            'alamat_lengkap',
        ]);
    }

    public function test_settings_logs_applies_filters(): void
    {
        $admin = $this->createAdmin();

        AdminNotification::query()->create([
            'admin_id' => $admin->id,
            'type' => 'klaim_baru',
            'title' => 'Klaim Baru Masuk',
            'message' => 'Ada klaim baru untuk diperiksa.',
            'created_at' => '2026-04-24 08:00:00',
        ]);

        AdminNotification::query()->create([
            'admin_id' => $admin->id,
            'type' => 'barang_temuan_baru',
            'title' => 'Barang Temuan Baru',
            'message' => 'Laporan temuan baru ditambahkan.',
            'read_at' => now(),
            'created_at' => '2026-04-24 09:00:00',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.logs', [
                'status' => 'unread',
                'type' => 'klaim_baru',
                'date' => '2026-04-24',
                'search' => 'Klaim',
            ]));

        $response->assertOk();
        $response->assertSee('Klaim Baru Masuk');
        $response->assertViewHas('statusFilter', 'unread');
        $response->assertViewHas('typeFilter', 'klaim_baru');
        $response->assertViewHas('dateFilter', '2026-04-24');
        $response->assertViewHas('search', 'Klaim');
        $response->assertViewHas('logs', function ($logs) {
            return $logs->count() === 1
                && $logs->first()?->title === 'Klaim Baru Masuk';
        });
    }

    public function test_settings_logs_validates_status_filter(): void
    {
        $admin = $this->createAdmin();

        $response = $this->from(route('admin.settings.logs'))
            ->actingAs($admin, 'admin')
            ->get(route('admin.settings.logs', [
                'status' => 'invalid',
            ]));

        $response->assertRedirect(route('admin.settings.logs'));
        $response->assertSessionHasErrors('status');
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Settings',
            'email' => 'settings-super@example.com',
            'username' => 'settings-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Settings',
            'email' => 'settings-admin@example.com',
            'username' => 'settings-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Pengaturan No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
