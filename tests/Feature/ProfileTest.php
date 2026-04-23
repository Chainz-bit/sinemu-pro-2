<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('user.profile'));

        $response->assertOk();
    }

    public function test_profile_page_shows_stats_and_recent_activity(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'nama' => 'Diwan',
        ]);
        $kategori = Kategori::query()->create([
            'nama_kategori' => 'Elektronik',
        ]);

        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Laptop Asus',
            'kategori_barang' => 'Elektronik',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => '2026-04-20',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
        ]);

        $barang = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Asus',
            'deskripsi' => 'Ditemukan di meja baca perpustakaan',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => '2026-04-21',
            'status_barang' => 'tersedia',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);

        Klaim::query()->create([
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'laporan_hilang_id' => $laporan->id,
            'barang_id' => $barang->id,
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_SUBMITTED,
            'catatan' => 'Ini milik saya',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-1.jpg'],
            'bukti_ciri_khusus' => 'Ada stiker kampus',
            'bukti_lokasi_spesifik' => 'Meja baca dekat jendela',
            'bukti_waktu_hilang' => '10:00:00',
        ]);

        Klaim::query()->create([
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'laporan_hilang_id' => $laporan->id,
            'barang_id' => $barang->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
            'catatan' => 'Sudah selesai',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-2.jpg'],
            'bukti_ciri_khusus' => 'Ada stiker kampus',
            'bukti_lokasi_spesifik' => 'Meja baca dekat jendela',
            'bukti_waktu_hilang' => '10:00:00',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('user.profile'));

        $response->assertOk();
        $response->assertViewHas('laporanDiajukan', 1);
        $response->assertViewHas('klaimMenunggu', 1);
        $response->assertViewHas('klaimSelesai', 1);
        $response->assertViewHas('verificationLabel', 'Terverifikasi');
        $response->assertViewHas('recentActivities', function ($activities) {
            return $activities->count() === 3
                && $activities->contains(fn ($activity) => str_contains((string) $activity->title, 'Laptop Asus'));
        });
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Profile',
            'email' => 'profile-super@example.com',
            'username' => 'profile-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Profile',
            'email' => 'profile-admin@example.com',
            'username' => 'profile-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Profil No. 1',
            'status_verifikasi' => 'active',
        ]);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nama' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nama' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
