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

class FoundItemDeletionSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('blockedReportStatusProvider')]
    public function test_admin_cannot_delete_active_found_item_by_report_status(string $reportStatus): void
    {
        Storage::fake('public');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => $reportStatus,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        Storage::disk('public')->put($fixture['photoPath'], 'foto barang aktif');

        $this->from(route('admin.found-items'))
            ->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.found-items.destroy', $fixture['barang']))
            ->assertRedirect(route('admin.found-items'))
            ->assertSessionHas('error', 'Barang temuan yang masih aktif tidak dapat dihapus.');

        $this->assertDatabaseHas('barangs', [
            'id' => $fixture['barang']->id,
            'status_laporan' => $reportStatus,
        ]);
        Storage::disk('public')->assertExists($fixture['photoPath']);
    }

    #[DataProvider('blockedFoundItemStatusProvider')]
    public function test_admin_cannot_delete_found_item_locked_by_claim_workflow_status(string $itemStatus): void
    {
        Storage::fake('public');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => $itemStatus,
            'tampil_di_home' => false,
        ]);
        Storage::disk('public')->put($fixture['photoPath'], 'foto barang workflow klaim');

        $this->assertWorkflowDeletionIsRejected($fixture);

        $this->assertSame($itemStatus, $fixture['barang']->fresh()?->status_barang);
    }

    public function test_admin_can_delete_rejected_found_item_without_claim_matching_or_home_and_photo_is_cleaned(): void
    {
        Storage::fake('public');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        Storage::disk('public')->put($fixture['photoPath'], 'foto barang ditolak');

        $this->from(route('admin.found-items'))
            ->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.found-items.destroy', $fixture['barang']))
            ->assertRedirect(route('admin.found-items'))
            ->assertSessionHas('status', 'Laporan barang temuan berhasil dihapus.');

        $this->assertDatabaseMissing('barangs', ['id' => $fixture['barang']->id]);
        Storage::disk('public')->assertMissing($fixture['photoPath']);
    }

    public function test_admin_cannot_delete_rejected_found_item_with_claim_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        $lostReport = $this->createLostReport($fixture['admin'], $fixture['owner'], [
            'status_laporan' => WorkflowStatus::REPORT_CLAIMED,
        ]);
        $proofPath = 'private/verifikasi-klaim/2026/06/bukti-barang-temuan.webp';

        Storage::disk('public')->put($fixture['photoPath'], 'foto barang dengan klaim');
        Storage::disk('local')->put($proofPath, 'evidence klaim');

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $fixture['barang']->id,
            'user_id' => $fixture['owner']->id,
            'admin_id' => $fixture['admin']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
            'bukti_foto' => [$proofPath],
        ]);

        $this->assertWorkflowDeletionIsRejected($fixture);

        $this->assertDatabaseHas('klaims', ['id' => $claim->id]);
        $this->assertSame(WorkflowStatus::REPORT_CLAIMED, $lostReport->fresh()?->status_laporan);
        Storage::disk('local')->assertExists($proofPath);
    }

    public function test_admin_cannot_delete_rejected_found_item_with_matching_history(): void
    {
        Storage::fake('public');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        $lostReport = $this->createLostReport($fixture['admin'], $fixture['owner'], [
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);

        Storage::disk('public')->put($fixture['photoPath'], 'foto barang dengan matching');

        $matching = Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $fixture['barang']->id,
            'admin_id' => $fixture['admin']->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'matched_at' => now(),
        ]);

        $this->assertWorkflowDeletionIsRejected($fixture);

        $this->assertDatabaseHas('pencocokans', ['id' => $matching->id]);
        $this->assertSame(WorkflowStatus::REPORT_MATCHED, $lostReport->fresh()?->status_laporan);
    }

    public function test_admin_cannot_delete_rejected_found_item_that_is_visible_on_home(): void
    {
        Storage::fake('public');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => true,
        ]);
        Storage::disk('public')->put($fixture['photoPath'], 'foto barang home');

        $this->assertWorkflowDeletionIsRejected($fixture);

        $this->assertTrue((bool) $fixture['barang']->fresh()?->tampil_di_home);
    }

    public function test_admin_from_other_region_cannot_delete_found_item(): void
    {
        Storage::fake('public');

        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        $otherRegion = Wilayah::query()->create(['nama_wilayah' => 'Wilayah Delete Temuan Lain']);
        $otherAdmin = $this->createAdminForRegion($otherRegion, 'delete-found-other-region');

        Storage::disk('public')->put($fixture['photoPath'], 'foto barang luar wilayah');

        $this->actingAs($otherAdmin, 'admin')
            ->delete(route('admin.found-items.destroy', $fixture['barang']))
            ->assertForbidden();

        $this->assertDatabaseHas('barangs', ['id' => $fixture['barang']->id]);
        Storage::disk('public')->assertExists($fixture['photoPath']);
    }

    public function test_found_item_index_hides_delete_menu_for_locked_item(): void
    {
        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($fixture['admin'], 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertDontSee('<button type="submit" class="menu-submit danger">Hapus</button>', false);
    }

    public function test_found_item_index_shows_delete_menu_for_safe_rejected_item(): void
    {
        $fixture = $this->createFoundItemFixture([
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($fixture['admin'], 'admin')
            ->get(route('admin.found-items'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertSee('<button type="submit" class="menu-submit danger">Hapus</button>', false);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function blockedReportStatusProvider(): array
    {
        return [
            'pending' => ['pending'],
            'submitted' => [WorkflowStatus::REPORT_SUBMITTED],
            'menunggu' => ['menunggu'],
            'approved tersedia' => [WorkflowStatus::REPORT_APPROVED],
            'matched' => [WorkflowStatus::REPORT_MATCHED],
            'claimed' => [WorkflowStatus::REPORT_CLAIMED],
            'completed' => [WorkflowStatus::REPORT_COMPLETED],
        ];
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function blockedFoundItemStatusProvider(): array
    {
        return [
            'dalam proses klaim' => [WorkflowStatus::FOUND_CLAIM_IN_PROGRESS],
            'sudah diklaim' => [WorkflowStatus::FOUND_CLAIMED],
            'selesai sudah dikembalikan' => [WorkflowStatus::FOUND_RETURNED],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{admin:Admin,owner:User,barang:Barang,photoPath:string}
     */
    private function createFoundItemFixture(array $overrides = []): array
    {
        $suffix = $this->uniqueSuffix($overrides['status_laporan'] ?? WorkflowStatus::REPORT_REJECTED, $overrides['status_barang'] ?? WorkflowStatus::FOUND_AVAILABLE);
        $admin = $this->createAdmin('delete-found-admin-' . $suffix);
        $owner = $this->createUser('delete-found-user-' . $suffix);
        $kategori = Kategori::query()->create(['nama_kategori' => 'Kategori Temuan ' . $suffix]);
        $photoPath = 'barang-temuan/2026/06/barang-temuan-' . $suffix . '.webp';

        $barang = Barang::query()->create(array_merge([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $owner->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Barang Temuan ' . $suffix,
            'deskripsi' => 'Barang temuan untuk uji keamanan hapus.',
            'lokasi_ditemukan' => 'Ruang Keamanan',
            'tanggal_ditemukan' => now()->toDateString(),
            'foto_barang' => $photoPath,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ], $overrides));

        return compact('admin', 'owner', 'barang', 'photoPath');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createLostReport(Admin $admin, User $user, array $overrides = []): LaporanBarangHilang
    {
        return LaporanBarangHilang::query()->create(array_merge([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laporan Hilang Terkait',
            'lokasi_hilang' => 'Ruang Keamanan',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Laporan terkait barang temuan.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ], $overrides));
    }

    /**
     * @param array{admin:Admin,owner:User,barang:Barang,photoPath:string} $fixture
     */
    private function assertWorkflowDeletionIsRejected(array $fixture): void
    {
        $this->from(route('admin.found-items'))
            ->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.found-items.destroy', $fixture['barang']))
            ->assertRedirect(route('admin.found-items'))
            ->assertSessionHas('error', 'Barang ini tidak dapat dihapus karena sudah terhubung dengan proses klaim atau pencocokan.');

        $this->assertDatabaseHas('barangs', ['id' => $fixture['barang']->id]);
        Storage::disk('public')->assertExists($fixture['photoPath']);
    }

    private function uniqueSuffix(string $reportStatus, string $itemStatus): string
    {
        $suffix = strtolower($reportStatus . '-' . $itemStatus . '-' . str()->random(6));

        return preg_replace('/[^a-z0-9]+/', '-', $suffix) ?: 'item';
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
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah ' . $username,
            'lat' => -6.32,
            'lng' => 108.32,
        ]);

        return $this->createAdminForRegion($region, $username);
    }

    private function createAdminForRegion(Wilayah $region, string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super ' . $username,
            'email' => 'super-' . $username . '@example.com',
            'username' => 'super-' . $username,
            'password' => Hash::make('password123'),
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
            'alamat_lengkap' => 'Jl. Delete Temuan No. 1',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
    }
}
