<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\BarangStatusHistory;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('verifiableFoundReportStatuses')]
    public function test_admin_can_approve_verifiable_found_item_report_statuses(string $currentStatus): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => $currentStatus,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'verified_by_admin_id' => null,
            'verified_at' => null,
            'tampil_di_home' => false,
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'approved',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('status', 'Verifikasi laporan barang temuan berhasil diperbarui.');

        $barang = $barang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_APPROVED, $barang?->status_laporan);
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang?->status_barang);
        $this->assertTrue((bool) $barang?->tampil_di_home);
        $this->assertSame($admin->id, $barang?->verified_by_admin_id);
        $this->assertNotNull($barang?->verified_at);
    }

    #[DataProvider('verifiableFoundReportStatuses')]
    public function test_admin_can_reject_verifiable_found_item_report_statuses(string $currentStatus): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => $currentStatus,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'verified_by_admin_id' => null,
            'verified_at' => null,
            'tampil_di_home' => false,
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'rejected',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('status', 'Verifikasi laporan barang temuan berhasil diperbarui.');

        $barang = $barang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_REJECTED, $barang?->status_laporan);
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang?->status_barang);
        $this->assertFalse((bool) $barang?->tampil_di_home);
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

    public function test_found_item_verify_with_same_status_does_not_duplicate_user_notification(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $verifiedAt = now()->subDay()->startOfSecond();
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => $verifiedAt,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'verifikasi_laporan_temuan',
            'title' => 'Verifikasi Laporan Temuan',
            'message' => 'Notifikasi verifikasi awal.',
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'approved',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang));

        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
        $this->assertSame($verifiedAt->toDateTimeString(), \Illuminate\Support\Carbon::parse($barang->fresh()?->verified_at)->toDateTimeString());
    }

    #[DataProvider('processedFoundItemVerificationStates')]
    public function test_processed_found_item_report_cannot_be_verified_again(array $overrides, string $targetStatus): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $verifiedAt = now()->subDay()->startOfSecond();
        $barang = $this->createFoundItem($admin, $user, $kategori, array_merge([
            'verified_by_admin_id' => $admin->id,
            'verified_at' => $verifiedAt,
            'tampil_di_home' => true,
        ], $overrides));
        $originalReportStatus = (string) $barang->status_laporan;
        $originalItemStatus = (string) $barang->status_barang;
        $originalHomeStatus = (bool) $barang->tampil_di_home;

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'verifikasi_laporan_temuan',
            'title' => 'Verifikasi Laporan Temuan',
            'message' => 'Notifikasi verifikasi awal.',
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => $targetStatus,
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diverifikasi ulang karena sudah diproses.');

        $barang = $barang->fresh();

        $this->assertSame($originalReportStatus, $barang?->status_laporan);
        $this->assertSame($originalItemStatus, $barang?->status_barang);
        $this->assertSame($admin->id, $barang?->verified_by_admin_id);
        $this->assertSame($verifiedAt->toDateTimeString(), \Illuminate\Support\Carbon::parse($barang?->verified_at)->toDateTimeString());
        $this->assertSame($originalHomeStatus, (bool) $barang?->tampil_di_home);
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_found_item_with_claim_cannot_be_verified_again(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $verifiedAt = now()->subDay()->startOfSecond();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => $verifiedAt,
            'tampil_di_home' => false,
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-found-verify.jpg'],
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'approved',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diverifikasi ulang karena sudah diproses.');

        $barang = $barang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_SUBMITTED, $barang?->status_laporan);
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang?->status_barang);
        $this->assertSame($admin->id, $barang?->verified_by_admin_id);
        $this->assertSame($verifiedAt->toDateTimeString(), \Illuminate\Support\Carbon::parse($barang?->verified_at)->toDateTimeString());
        $this->assertFalse((bool) $barang?->tampil_di_home);
        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_found_item_with_matching_cannot_be_verified_again(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $verifiedAt = now()->subDay()->startOfSecond();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => $verifiedAt,
            'tampil_di_home' => false,
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'catatan' => 'Barang temuan sudah punya pencocokan.',
            'matched_at' => now(),
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'approved',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Barang temuan ini tidak dapat diverifikasi ulang karena sudah diproses.');

        $barang = $barang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_SUBMITTED, $barang?->status_laporan);
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang?->status_barang);
        $this->assertSame($admin->id, $barang?->verified_by_admin_id);
        $this->assertSame($verifiedAt->toDateTimeString(), \Illuminate\Support\Carbon::parse($barang?->verified_at)->toDateTimeString());
        $this->assertFalse((bool) $barang?->tampil_di_home);
        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_found_item_detail_hides_verification_buttons_after_report_processed(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.show', $barang))
            ->assertOk()
            ->assertSee('Barang temuan ini sudah diproses dan tidak dapat diverifikasi ulang.')
            ->assertDontSee('Setujui Laporan')
            ->assertDontSee('Tolak Laporan');
    }

    public function test_admin_from_other_region_cannot_verify_found_report(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'verified_by_admin_id' => null,
            'verified_at' => null,
            'tampil_di_home' => false,
        ]);
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Lain Temuan Workflow',
            'lat' => -6.42,
            'lng' => 108.42,
        ]);
        $otherAdmin = $this->createAdminForRegion($otherRegion, 'found-other-region');

        $this->actingAs($otherAdmin, 'admin')
            ->patch(route('admin.found-items.verify', $barang), [
                'status_laporan' => 'approved',
            ])
            ->assertForbidden();

        $barang = $barang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_SUBMITTED, $barang?->status_laporan);
        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang?->status_barang);
        $this->assertNull($barang?->verified_by_admin_id);
        $this->assertNull($barang?->verified_at);
        $this->assertFalse((bool) $barang?->tampil_di_home);
    }

    public function test_admin_can_update_eligible_found_item_status_to_allowed_manual_target(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => '',
            'tampil_di_home' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'catatan_status' => 'Barang dicek dan tetap tersedia.',
            ]);

        $response->assertRedirect(route('admin.found-items.show', $barang));
        $response->assertSessionHas('status', 'Perubahan status berhasil disimpan.');

        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang->fresh()?->status_barang);
        $this->assertDatabaseHas((new BarangStatusHistory())->getTable(), [
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_lama' => '',
            'status_baru' => WorkflowStatus::FOUND_AVAILABLE,
            'catatan' => 'Barang dicek dan tetap tersedia.',
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

    #[DataProvider('lockedFoundItemStatusUpdateStates')]
    public function test_locked_found_item_status_update_is_rejected(array $overrides): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, $overrides);
        $originalStatus = (string) $barang->status_barang;

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'status_barang_temuan',
            'title' => 'Status Barang Temuan Diperbarui',
            'message' => 'Notifikasi lama.',
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'catatan_status' => 'Percobaan regresi status.',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Status barang tidak dapat diubah karena sudah masuk proses klaim atau belum siap diproses.');

        $this->assertSame($originalStatus, $barang->fresh()?->status_barang);
        $this->assertSame(0, BarangStatusHistory::query()->where('barang_id', $barang->id)->count());
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
    }

    #[DataProvider('manualFoundItemWorkflowTargets')]
    public function test_claim_workflow_status_targets_cannot_be_set_manually(string $targetStatus): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => $targetStatus,
                'catatan_status' => 'Percobaan status workflow klaim.',
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Status barang tidak dapat diubah karena sudah masuk proses klaim atau belum siap diproses.');

        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang->fresh()?->status_barang);
        $this->assertSame(0, BarangStatusHistory::query()->where('barang_id', $barang->id)->count());
        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_found_item_with_claim_cannot_update_status_manually(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti-status.jpg'],
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Status barang tidak dapat diubah karena sudah masuk proses klaim atau belum siap diproses.');

        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang->fresh()?->status_barang);
        $this->assertSame(0, BarangStatusHistory::query()->where('barang_id', $barang->id)->count());
        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_found_item_with_matching_cannot_update_status_manually(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $laporanBarangHilang->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'catatan' => 'Pencocokan aktif.',
            'matched_at' => now(),
        ]);

        $this->from(route('admin.found-items.show', $barang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            ])
            ->assertRedirect(route('admin.found-items.show', $barang))
            ->assertSessionHas('error', 'Status barang tidak dapat diubah karena sudah masuk proses klaim atau belum siap diproses.');

        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang->fresh()?->status_barang);
        $this->assertSame(0, BarangStatusHistory::query()->where('barang_id', $barang->id)->count());
        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_found_item_detail_hides_status_form_for_locked_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.show', $barang))
            ->assertOk()
            ->assertSee('Status barang tidak dapat diubah karena sudah masuk proses klaim atau belum siap diproses.')
            ->assertDontSee('Status Baru')
            ->assertDontSee('Perbarui Status');
    }

    public function test_found_item_detail_shows_only_allowed_manual_status_targets_for_eligible_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.found-items.show', $barang))
            ->assertOk()
            ->assertSee('Status Baru')
            ->assertSee('Tersedia')
            ->assertDontSee('Dalam Proses Klaim')
            ->assertDontSee('Sudah Diklaim')
            ->assertDontSee('Sudah Dikembalikan');
    }

    public function test_admin_from_other_region_cannot_update_found_item_status(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $barang = $this->createFoundItem($admin, $user, $kategori, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'tampil_di_home' => false,
        ]);
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Status Temuan Lain',
            'lat' => -6.43,
            'lng' => 108.43,
        ]);
        $otherAdmin = $this->createAdminForRegion($otherRegion, 'found-status-other');

        $this->actingAs($otherAdmin, 'admin')
            ->patch(route('admin.found-items.update-status', $barang), [
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            ])
            ->assertForbidden();

        $this->assertSame(WorkflowStatus::FOUND_AVAILABLE, $barang->fresh()?->status_barang);
        $this->assertSame(0, BarangStatusHistory::query()->where('barang_id', $barang->id)->count());
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
            'catatan' => 'Menunggu verifikasi pengelola barang.',
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

    public function test_admin_can_approve_submitted_lost_item_report(): void
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
                'status_laporan' => 'approved',
            ]);

        $response->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang));
        $response->assertSessionHas('status', 'Verifikasi laporan barang hilang berhasil diperbarui.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_APPROVED, $laporanBarangHilang?->status_laporan);
        $this->assertTrue((bool) $laporanBarangHilang?->tampil_di_home);
        $this->assertSame($admin->id, $laporanBarangHilang?->verified_by_admin_id);
        $this->assertNotNull($laporanBarangHilang?->verified_at);
    }

    public function test_admin_can_reject_submitted_lost_item_report(): void
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

    #[DataProvider('processedLostReportStatuses')]
    public function test_processed_lost_item_report_cannot_be_verified_again(string $currentStatus, string $targetStatus): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $verifiedAt = now()->subDay()->startOfSecond();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => $currentStatus,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => $verifiedAt,
            'tampil_di_home' => $currentStatus === WorkflowStatus::REPORT_APPROVED,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'verifikasi_laporan_hilang',
            'title' => 'Verifikasi Laporan Hilang',
            'message' => 'Notifikasi verifikasi awal.',
        ]);

        $this->from(route('admin.lost-items.show', $laporanBarangHilang))
            ->actingAs($admin, 'admin')
            ->patch(route('admin.lost-items.verify', $laporanBarangHilang), [
                'status_laporan' => $targetStatus,
            ])
            ->assertRedirect(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertSessionHas('error', 'Laporan ini tidak dapat diverifikasi ulang karena sudah diproses.');

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame($currentStatus, $laporanBarangHilang?->status_laporan);
        $this->assertSame($admin->id, $laporanBarangHilang?->verified_by_admin_id);
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
        $this->assertSame($verifiedAt->toDateTimeString(), \Illuminate\Support\Carbon::parse($laporanBarangHilang?->verified_at)->toDateTimeString());
    }

    public function test_lost_item_detail_hides_verification_buttons_after_report_processed(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.lost-items.show', $laporanBarangHilang))
            ->assertOk()
            ->assertSee('Laporan ini sudah diproses dan tidak dapat diverifikasi ulang.')
            ->assertDontSee('Setujui Laporan')
            ->assertDontSee('Tolak Laporan');
    }

    public function test_admin_from_other_region_cannot_verify_lost_report(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $laporanBarangHilang = $this->createLostItem($user, [
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'verified_by_admin_id' => null,
            'verified_at' => null,
        ]);
        $otherRegion = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Lain Workflow',
            'lat' => -6.41,
            'lng' => 108.41,
        ]);
        $otherAdmin = $this->createAdminForRegion($otherRegion, 'other-region');

        $this->actingAs($otherAdmin, 'admin')
            ->patch(route('admin.lost-items.verify', $laporanBarangHilang), [
                'status_laporan' => 'approved',
            ])
            ->assertForbidden();

        $laporanBarangHilang = $laporanBarangHilang->fresh();

        $this->assertSame(WorkflowStatus::REPORT_SUBMITTED, $laporanBarangHilang?->status_laporan);
        $this->assertNull($laporanBarangHilang?->verified_by_admin_id);
        $this->assertNull($laporanBarangHilang?->verified_at);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function verifiableFoundReportStatuses(): array
    {
        return [
            'legacy empty' => [''],
            'pending' => ['pending'],
            'submitted' => [WorkflowStatus::REPORT_SUBMITTED],
            'menunggu' => ['menunggu'],
        ];
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function processedFoundItemVerificationStates(): array
    {
        return [
            'approved' => [[
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            ], 'rejected'],
            'rejected' => [[
                'status_laporan' => WorkflowStatus::REPORT_REJECTED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ], 'approved'],
            'matched report' => [[
                'status_laporan' => WorkflowStatus::REPORT_MATCHED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            ], 'rejected'],
            'claimed report' => [[
                'status_laporan' => WorkflowStatus::REPORT_CLAIMED,
                'status_barang' => WorkflowStatus::FOUND_CLAIMED,
            ], 'rejected'],
            'completed report' => [[
                'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
                'status_barang' => WorkflowStatus::FOUND_RETURNED,
            ], 'rejected'],
            'claim in progress item' => [[
                'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
                'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            ], 'approved'],
            'claimed item' => [[
                'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
                'status_barang' => WorkflowStatus::FOUND_CLAIMED,
            ], 'approved'],
            'returned item' => [[
                'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
                'status_barang' => WorkflowStatus::FOUND_RETURNED,
            ], 'approved'],
            'unknown report status' => [[
                'status_laporan' => 'arsip',
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            ], 'approved'],
        ];
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function manualFoundItemWorkflowTargets(): array
    {
        return [
            'claim in progress' => [WorkflowStatus::FOUND_CLAIM_IN_PROGRESS],
            'claimed' => [WorkflowStatus::FOUND_CLAIMED],
            'returned' => [WorkflowStatus::FOUND_RETURNED],
        ];
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function lockedFoundItemStatusUpdateStates(): array
    {
        return [
            'pending report' => [[
                'status_laporan' => 'pending',
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'submitted report' => [[
                'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'menunggu report' => [[
                'status_laporan' => 'menunggu',
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'rejected report' => [[
                'status_laporan' => WorkflowStatus::REPORT_REJECTED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'matched report' => [[
                'status_laporan' => WorkflowStatus::REPORT_MATCHED,
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
            'claim in progress item' => [[
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
                'tampil_di_home' => false,
            ]],
            'claimed item' => [[
                'status_laporan' => WorkflowStatus::REPORT_APPROVED,
                'status_barang' => WorkflowStatus::FOUND_CLAIMED,
                'tampil_di_home' => false,
            ]],
            'returned item' => [[
                'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
                'status_barang' => WorkflowStatus::FOUND_RETURNED,
                'tampil_di_home' => false,
            ]],
            'unknown report status' => [[
                'status_laporan' => 'arsip',
                'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
                'tampil_di_home' => false,
            ]],
        ];
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function processedLostReportStatuses(): array
    {
        return [
            'approved' => [WorkflowStatus::REPORT_APPROVED, 'rejected'],
            'rejected' => [WorkflowStatus::REPORT_REJECTED, 'approved'],
            'matched' => [WorkflowStatus::REPORT_MATCHED, 'rejected'],
            'claimed' => [WorkflowStatus::REPORT_CLAIMED, 'rejected'],
            'completed' => [WorkflowStatus::REPORT_COMPLETED, 'rejected'],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createFoundItem(Admin $admin, User $user, Kategori $kategori, array $overrides = []): Barang
    {
        return Barang::query()->create(array_merge([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
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
            'region_id' => Wilayah::query()->value('id'),
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
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah Admin Workflow',
            'lat' => -6.326,
            'lng' => 108.32,
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
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

    private function createAdminForRegion(Wilayah $region, string $suffix): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Workflow ' . $suffix,
            'email' => 'admin-item-workflow-super-' . $suffix . '@example.com',
            'username' => 'super-item-workflow-' . $suffix,
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Workflow ' . $suffix,
            'email' => 'admin-item-workflow-' . $suffix . '@example.com',
            'username' => 'admin-item-workflow-' . $suffix,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Admin Workflow No. ' . $suffix,
            'status_verifikasi' => 'active',
        ]);
    }
}
