<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimVerificationDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_verification_detail_shows_claim_summary_and_statuses(): void
    {
        $admin = $this->createAdmin('detail-admin@example.com', 'detail-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Kamera Sony',
            'lokasi_hilang' => 'Studio Foto',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang setelah sesi foto',
            'ciri_khusus' => 'Ada stiker kecil di sisi samping',
            'bukti_kepemilikan' => 'Nomor seri dan tas kamera asli',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kamera Sony',
            'deskripsi' => 'Ditemukan di studio foto',
            'lokasi_ditemukan' => 'Studio Foto',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_RETURNED,
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
            'catatan' => 'Pengaju membawa bukti kepemilikan lengkap.',
            'catatan_verifikasi_admin' => 'Barang sudah diserahkan langsung.',
            'bukti_ciri_khusus' => 'Ada stiker kecil di sisi samping',
            'bukti_detail_isi' => 'Tas kamera dan baterai cadangan',
            'bukti_lokasi_spesifik' => 'Dekat meja pencahayaan',
            'bukti_waktu_hilang' => '15:30',
            'skor_validitas' => 95,
            'hasil_checklist' => [
                'identitas_pelapor_valid' => true,
                'detail_barang_valid' => true,
                'kronologi_valid' => true,
                'bukti_visual_valid' => true,
                'kecocokan_data_laporan' => true,
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.claim-verifications.show', $claim));

        $response->assertOk();
        $response->assertSee('Detail Verifikasi Klaim');
        $response->assertSee('Kamera Sony');
        $response->assertSee('SELESAI');
        $response->assertSee('Studio Foto');
        $response->assertSee('Pengaju membawa bukti kepemilikan lengkap.');
        $response->assertSee('Barang sudah diserahkan langsung.');
        $response->assertSee('Ada stiker kecil di sisi samping');
    }

    public function test_claim_verification_detail_for_other_admin_claim_returns_forbidden(): void
    {
        $ownerAdmin = $this->createAdmin('owner-admin@example.com', 'owner-admin');
        $otherAdmin = $this->createAdmin('other-admin@example.com', 'other-admin');
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Aksesoris']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Jam Tangan',
            'lokasi_hilang' => 'Lobby',
            'tanggal_hilang' => now()->subDays(2)->toDateString(),
            'keterangan' => 'Hilang saat menunggu teman',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $ownerAdmin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Jam Tangan',
            'deskripsi' => 'Ditemukan di kursi lobby',
            'lokasi_ditemukan' => 'Lobby',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $ownerAdmin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Masih diverifikasi',
        ]);

        $this->actingAs($otherAdmin, 'admin')
            ->get(route('admin.claim-verifications.show', $claim))
            ->assertForbidden();
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Detail Klaim',
            'nama' => 'User Detail Klaim',
            'username' => 'user-detail-klaim',
            'email' => 'claim-detail-user@example.com',
            'nomor_telepon' => '081111111116',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(string $email, string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin ' . $username,
            'email' => 'super-' . $email,
            'username' => 'super-' . $username,
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin ' . $username,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Detail Klaim No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
