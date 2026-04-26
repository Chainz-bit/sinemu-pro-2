<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\BarangStatusHistory;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminItemWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_verify_found_item_report(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $response = $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'approved',
            ]);

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHas('status', 'Verifikasi laporan barang temuan berhasil diperbarui.');

        $barang = $barang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_APPROVED, $barang?->status_laporan);
        $this->assertTrue((bool) $barang?->tampil_di_home);
        $this->assertSame($admin->id, $barang?->verified_by_admin_id);
        $this->assertNotNull($barang?->verified_at);
    }

    public function test_found_item_verify_validates_allowed_status(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);

        $response = $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'invalid-status',
            ]);

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHasErrors('status_laporan');
        $this->assertSame(WorkflowStatus::REPORT_SUBMITTED, $barang->fresh()?->status_laporan);
    }

    public function test_admin_can_update_found_item_status_and_record_history(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
                'catatan_status' => 'Sedang menunggu proses klaim pengguna.',
            ]);

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHas('status', 'Perubahan status berhasil disimpan.');

        $this->assertSame(WorkflowStatus::FOUND_CLAIM_IN_PROGRESS, $barang->fresh()?->status_barang);
        $this->assertDatabaseHas((new BarangStatusHistory())->getTable(), [
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_lama' => WorkflowStatus::FOUND_AVAILABLE,
            'status_baru' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'catatan' => 'Sedang menunggu proses klaim pengguna.',
        ]);
    }

    public function test_found_item_update_status_validates_allowed_workflow_status(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori);

        $response = $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => 'invalid-status',
            ]);

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHasErrors('status_barang');
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang->fresh()?->status_barang);
    }

    public function test_lost_item_update_status_returns_error_when_no_claim_exists(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user);

        $response = $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update-status', $laporanBarangHilang));

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('error', 'Belum ada klaim aktif untuk laporan ini.');
    }

    public function test_lost_item_update_status_redirects_to_claim_verification_when_claim_exists(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user);
        $barang = $this->createFoundItem($admin, $user, $kategori);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Menunggu verifikasi admin.',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-3.jpg'],
            'bukti_ciri_khusus' => 'Ada stiker kecil.',
            'bukti_lokasi_spesifik' => 'Dekat pintu masuk.',
            'bukti_waktu_hilang' => '11:00:00',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.update-status', $laporanBarangHilang));

        $response->assertRedirect(route('admin.claim-verifications.show', $claim));
        $response->assertSessionHas('error', 'Perbarui status klaim dari halaman Verifikasi Klaim agar checklist keamanan tetap diterapkan.');
    }

    public function test_admin_can_verify_lost_item_report(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $response = $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.verify', $laporanBarangHilang), [
                'status_laporan' => 'rejected',
                'catatan' => 'Data belum cukup kuat untuk diverifikasi.',
            ]);

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('status', 'Verifikasi laporan barang hilang berhasil diperbarui.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_REJECTED, $laporanBarangHilang?->status_laporan);
        $this->assertFalse((bool) $laporanBarangHilang?->tampil_di_home);
        $this->assertSame($admin->id, $laporanBarangHilang?->verified_by_admin_id);
        $this->assertNotNull($laporanBarangHilang?->verified_at);
    }

    public function test_lost_item_verify_validates_allowed_status(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);

        $response = $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.verify', $laporanBarangHilang), [
                'status_laporan' => 'draft',
            ]);

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHasErrors('status_laporan');
        $this->assertSame(WorkflowStatus::REPORT_SUBMITTED, $laporanBarangHilang->fresh()?->status_laporan);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createFoundItem(Admin $admin, User $user, Kategori $kategori, array $overrides = []): Barang
    {
        return Barang::query()->create(array_merge([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Headset Hitam',
            'deskripsi' => 'Ditemukan di ruang multimedia.',
            'lokasi_ditemukan' => 'Ruang Multimedia',
            'tanggal_ditemukan' => '2026-04-20',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createLostItem(User $user, array $overrides = []): LaporanBarangHilang
    {
        return LaporanBarangHilang::query()->create(array_merge([
            'user_id' => $user->id,
            'nama_barang' => 'Jam Tangan',
            'lokasi_hilang' => 'Aula Kampus',
            'tanggal_hilang' => '2026-04-19',
            'keterangan' => 'Hilang setelah acara seminar.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ], $overrides));
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Workflow Admin',
            'nama' => 'User Workflow Admin',
            'username' => 'user-workflow-admin',
            'email' => 'admin-item-workflow-user@example.com',
            'nomor_telepon' => '081111111114',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Workflow',
            'email' => 'admin-item-workflow-super@example.com',
            'username' => 'super-item-workflow',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Workflow',
            'email' => 'admin-item-workflow@example.com',
            'username' => 'admin-item-workflow',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Workflow No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
