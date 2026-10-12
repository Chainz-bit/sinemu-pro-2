<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Admin\Claims\ClaimVerificationWorkflowService;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimNotificationDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_claim_cannot_be_approved_again_and_does_not_duplicate_notification(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $klaim = $this->createClaim($admin, $user, [
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ]);
        $this->createUserNotification($user, 'klaim_disetujui');

        $result = $this->app
            ->make(ClaimVerificationWorkflowService::class)
            ->approve($klaim, $this->approvalPayload(), $admin->id);

        $this->assertFalse($result);
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
        $this->assertSame(WorkflowStatus::CLAIM_LEGACY_APPROVED, $klaim->fresh()?->status_klaim);
        $this->assertSame(WorkflowStatus::CLAIM_APPROVED, $klaim->fresh()?->status_verifikasi);
    }

    public function test_rejected_claim_cannot_be_rejected_again_and_does_not_duplicate_notification(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $klaim = $this->createClaim($admin, $user, [
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
        ]);
        $this->createUserNotification($user, 'klaim_ditolak');

        $result = $this->app
            ->make(ClaimVerificationWorkflowService::class)
            ->reject($klaim, $this->rejectionPayload(), $admin->id);

        $this->assertFalse($result);
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
        $this->assertSame(WorkflowStatus::CLAIM_LEGACY_REJECTED, $klaim->fresh()?->status_klaim);
        $this->assertSame(WorkflowStatus::CLAIM_REJECTED, $klaim->fresh()?->status_verifikasi);
    }

    public function test_completed_claim_cannot_be_completed_again_and_does_not_duplicate_notification(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $klaim = $this->createClaim($admin, $user, [
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
        ]);
        $this->createUserNotification($user, 'klaim_selesai');

        $result = $this->app
            ->make(ClaimVerificationWorkflowService::class)
            ->complete($klaim, $admin->id);

        $this->assertFalse($result);
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
        $this->assertSame(WorkflowStatus::CLAIM_LEGACY_APPROVED, $klaim->fresh()?->status_klaim);
        $this->assertSame(WorkflowStatus::CLAIM_COMPLETED, $klaim->fresh()?->status_verifikasi);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createClaim(Admin $admin, User $user, array $overrides = []): Klaim
    {
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Klaim',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang di area kampus.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Klaim',
            'deskripsi' => 'Ditemukan di area kampus.',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        return Klaim::query()->create(array_merge([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Menunggu verifikasi.',
        ], $overrides));
    }

    private function createUserNotification(User $user, string $type): void
    {
        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Notifikasi Klaim',
            'message' => 'Notifikasi klaim yang sudah ada.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function approvalPayload(): array
    {
        return [
            'identitas_pelapor_valid' => '1',
            'detail_barang_valid' => '1',
            'kronologi_valid' => '1',
            'bukti_visual_valid' => '1',
            'kecocokan_data_laporan' => '1',
            'catatan_verifikasi_admin' => 'Bukti kuat.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function rejectionPayload(): array
    {
        return [
            'identitas_pelapor_valid' => '0',
            'detail_barang_valid' => '0',
            'kronologi_valid' => '0',
            'bukti_visual_valid' => '0',
            'kecocokan_data_laporan' => '0',
            'catatan_verifikasi_admin' => 'Bukti belum cukup.',
            'alasan_penolakan' => 'Data klaim tidak sesuai.',
        ];
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Klaim Duplicate',
            'nama' => 'User Klaim Duplicate',
            'username' => 'user-klaim-duplicate',
            'email' => 'claim-duplicate-user@example.com',
            'nomor_telepon' => '081111111120',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Klaim Duplicate',
            'email' => 'claim-duplicate-super@example.com',
            'username' => 'claim-duplicate-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Klaim Duplicate',
            'email' => 'claim-duplicate-admin@example.com',
            'username' => 'claim-duplicate-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Klaim Duplicate No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
