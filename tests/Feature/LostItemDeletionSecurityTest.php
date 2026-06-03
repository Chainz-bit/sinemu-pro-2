<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LostItemDeletionSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('blockedReportStatusProvider')]
    public function test_admin_cannot_delete_active_lost_report(string $status): void
    {
        Storage::fake('public');

        $fixture = $this->createLostReportFixture(status: $status);
        Storage::disk('public')->put($fixture['photoPath'], 'foto laporan aktif');

        $this->from(route('admin.lost-items'))
            ->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.lost-items.destroy', $fixture['laporan']))
            ->assertRedirect(route('admin.lost-items'))
            ->assertSessionHas('error', 'Laporan yang masih aktif tidak dapat dihapus.');

        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'id' => $fixture['laporan']->id,
            'status_laporan' => $status,
        ]);
        Storage::disk('public')->assertExists($fixture['photoPath']);
    }

    public function test_admin_can_delete_rejected_lost_report_without_claim_or_matching_and_photo_is_cleaned(): void
    {
        Storage::fake('public');

        $fixture = $this->createLostReportFixture(status: WorkflowStatus::REPORT_REJECTED);
        Storage::disk('public')->put($fixture['photoPath'], 'foto laporan ditolak');

        $this->from(route('admin.lost-items'))
            ->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.lost-items.destroy', $fixture['laporan']))
            ->assertRedirect(route('admin.lost-items'))
            ->assertSessionHas('status', 'Laporan barang hilang berhasil dihapus.');

        $this->assertDatabaseMissing('laporan_barang_hilangs', ['id' => $fixture['laporan']->id]);
        Storage::disk('public')->assertMissing($fixture['photoPath']);
    }

    public function test_admin_cannot_delete_rejected_lost_report_with_claim_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $fixture = $this->createLostReportFixture(status: WorkflowStatus::REPORT_REJECTED);
        $foundItem = $this->createFoundItem($fixture['admin'], $fixture['user'], WorkflowStatus::FOUND_CLAIM_IN_PROGRESS);
        $proofPath = 'private/verifikasi-klaim/2026/06/bukti-klaim.webp';

        Storage::disk('public')->put($fixture['photoPath'], 'foto laporan dengan klaim');
        Storage::disk('local')->put($proofPath, 'evidence klaim');

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $fixture['laporan']->id,
            'barang_id' => $foundItem->id,
            'user_id' => $fixture['user']->id,
            'admin_id' => $fixture['admin']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
            'bukti_foto' => [$proofPath],
        ]);

        $this->assertRelationDeletionIsRejected($fixture);

        $this->assertDatabaseHas('klaims', ['id' => $claim->id]);
        $this->assertSame(WorkflowStatus::FOUND_CLAIM_IN_PROGRESS, $foundItem->fresh()?->status_barang);
        Storage::disk('local')->assertExists($proofPath);
    }

    public function test_admin_cannot_delete_rejected_lost_report_with_matching_history(): void
    {
        Storage::fake('public');

        $fixture = $this->createLostReportFixture(status: WorkflowStatus::REPORT_REJECTED);
        $foundItem = $this->createFoundItem($fixture['admin'], $fixture['user'], WorkflowStatus::FOUND_CLAIM_IN_PROGRESS);
        Storage::disk('public')->put($fixture['photoPath'], 'foto laporan dengan matching');

        $matching = Pencocokan::query()->create([
            'laporan_hilang_id' => $fixture['laporan']->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $fixture['admin']->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'matched_at' => now(),
        ]);

        $this->assertRelationDeletionIsRejected($fixture);

        $this->assertDatabaseHas('pencocokans', ['id' => $matching->id]);
        $this->assertSame(WorkflowStatus::FOUND_CLAIM_IN_PROGRESS, $foundItem->fresh()?->status_barang);
    }

    public function test_admin_from_other_region_cannot_delete_lost_report(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin('delete-admin-region-a');
        $otherRegion = Wilayah::query()->create(['nama_wilayah' => 'Wilayah Delete Lain']);
        $user = $this->createUser('delete-other-region-user');
        $photoPath = 'barang-hilang/2026/06/laporan-wilayah-lain.webp';
        Storage::disk('public')->put($photoPath, 'foto laporan wilayah lain');

        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $otherRegion->id,
            'nama_barang' => 'Dokumen Wilayah Lain',
            'lokasi_hilang' => 'Kantor wilayah lain',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Tidak boleh dihapus pengelola wilayah lain.',
            'foto_barang' => $photoPath,
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.lost-items.destroy', $laporan))
            ->assertForbidden();

        $this->assertDatabaseHas('laporan_barang_hilangs', ['id' => $laporan->id]);
        Storage::disk('public')->assertExists($photoPath);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function blockedReportStatusProvider(): array
    {
        return [
            'submitted' => [WorkflowStatus::REPORT_SUBMITTED],
            'approved' => [WorkflowStatus::REPORT_APPROVED],
            'matched' => [WorkflowStatus::REPORT_MATCHED],
            'claimed' => [WorkflowStatus::REPORT_CLAIMED],
            'completed' => [WorkflowStatus::REPORT_COMPLETED],
        ];
    }

    /**
     * @param array{admin:Admin,user:User,laporan:LaporanBarangHilang,photoPath:string} $fixture
     */
    private function assertRelationDeletionIsRejected(array $fixture): void
    {
        $this->from(route('admin.lost-items'))
            ->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.lost-items.destroy', $fixture['laporan']))
            ->assertRedirect(route('admin.lost-items'))
            ->assertSessionHas('error', 'Laporan ini tidak dapat dihapus karena sudah terhubung dengan proses klaim atau pencocokan.');

        $this->assertDatabaseHas('laporan_barang_hilangs', ['id' => $fixture['laporan']->id]);
        Storage::disk('public')->assertExists($fixture['photoPath']);
    }

    /**
     * @return array{admin:Admin,user:User,laporan:LaporanBarangHilang,photoPath:string}
     */
    private function createLostReportFixture(string $status): array
    {
        $admin = $this->createAdmin('delete-admin-' . $status);
        $user = $this->createUser('delete-user-' . $status);
        $photoPath = 'barang-hilang/2026/06/laporan-' . $status . '.webp';

        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Dompet ' . ucfirst($status),
            'lokasi_hilang' => 'Kantin Kampus',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Laporan untuk uji keamanan hapus.',
            'foto_barang' => $photoPath,
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => $status,
            'tampil_di_home' => $status === WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $status === WorkflowStatus::REPORT_SUBMITTED ? null : $admin->id,
            'verified_at' => $status === WorkflowStatus::REPORT_SUBMITTED ? null : now(),
        ]);

        return compact('admin', 'user', 'laporan', 'photoPath');
    }

    private function createFoundItem(Admin $admin, User $user, string $statusBarang): Barang
    {
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dokumen']);

        return Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Dompet Temuan',
            'deskripsi' => 'Barang temuan terkait laporan hilang.',
            'lokasi_ditemukan' => 'Kantin Kampus',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => $statusBarang,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    private function createUser(string $username): User
    {
        $user = User::query()->create([
            'name' => 'User ' . $username,
            'nama' => 'User ' . $username,
            'username' => $username,
            'email' => $username . '@example.com',
            'nomor_telepon' => '081111111111',
            'password' => Hash::make('password123'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super ' . $username,
            'email' => 'super-' . $username . '@example.com',
            'username' => 'super-' . $username,
            'password' => Hash::make('password123'),
        ]);
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah ' . $username,
            'lat' => -6.32,
            'lng' => 108.32,
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin ' . $username,
            'email' => $username . '@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Delete No. 1',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
    }
}
