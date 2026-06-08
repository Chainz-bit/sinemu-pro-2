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
use App\Models\UserNotification;
use App\Models\Wilayah;
use App\Services\Admin\Claims\ClaimVerificationWorkflowService;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ClaimVerificationWorkflowPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_on_legacy_approved_claim_is_rejected_without_side_effects(): void
    {
        $fixture = $this->createClaimFixture([
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ], [
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
        ], [
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_APPROVED,
        ]);
        $before = $this->snapshot($fixture);

        $result = $this->workflow()->approve($fixture['claim'], $this->approvalPayload(), $fixture['admin']->id);

        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_reject_on_legacy_rejected_claim_is_rejected_without_duplicate_notification(): void
    {
        $fixture = $this->createClaimFixture([
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
        ]);
        UserNotification::query()->create([
            'user_id' => $fixture['user']->id,
            'type' => 'klaim_ditolak',
            'title' => 'Klaim Ditolak',
            'message' => 'Notifikasi lama.',
        ]);
        $before = $this->snapshot($fixture);

        $result = $this->workflow()->reject($fixture['claim'], $this->rejectionPayload(), $fixture['admin']->id);

        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(1, UserNotification::query()->where('user_id', $fixture['user']->id)->count());
    }

    public function test_complete_only_succeeds_from_approved_claim(): void
    {
        $fixture = $this->createApprovedClaimFixture();

        $result = $this->workflow()->complete($fixture['claim'], $fixture['admin']->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('klaims', [
            'id' => $fixture['claim']->id,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $fixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_RETURNED,
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
        ]);
        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'id' => $fixture['lostReport']->id,
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
        ]);
        $this->assertDatabaseHas('pencocokans', [
            'id' => $fixture['match']->id,
            'status_pencocokan' => WorkflowStatus::MATCH_COMPLETED,
        ]);
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_selesai')->count());
    }

    #[DataProvider('nonCompletableStatusProvider')]
    public function test_complete_from_invalid_status_is_rejected_without_side_effects(string $claimStatus, ?string $verificationStatus): void
    {
        $fixture = $this->createApprovedClaimFixture([
            'status_klaim' => $claimStatus,
            'status_verifikasi' => $verificationStatus,
        ]);
        $before = $this->snapshot($fixture);

        $result = $this->workflow()->complete($fixture['claim'], $fixture['admin']->id);

        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_approve_is_rejected_when_found_item_is_already_completed(): void
    {
        $fixture = $this->createClaimFixture([], [
            'status_barang' => WorkflowStatus::FOUND_RETURNED,
            'status_laporan' => WorkflowStatus::REPORT_COMPLETED,
        ]);
        $before = $this->snapshot($fixture);

        $result = $this->workflow()->approve($fixture['claim'], $this->approvalPayload(), $fixture['admin']->id);

        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_approve_and_complete_are_rejected_when_another_completed_claim_exists_for_same_item(): void
    {
        $pendingFixture = $this->createClaimFixture();
        $this->createCompletedClaimForSameItem($pendingFixture);

        $this->assertFalse($this->workflow()->approve($pendingFixture['claim'], $this->approvalPayload(), $pendingFixture['admin']->id));
        $this->assertSame(0, UserNotification::query()->count());

        UserNotification::query()->delete();
        $approvedFixture = $this->createApprovedClaimFixture();
        $this->createCompletedClaimForSameItem($approvedFixture);
        $before = $this->snapshot($approvedFixture);

        $this->assertFalse($this->workflow()->complete($approvedFixture['claim'], $approvedFixture['admin']->id));
        $this->assertSame($before, $this->snapshot($approvedFixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    #[DataProvider('workflowActionProvider')]
    public function test_admin_without_region_cannot_execute_workflow_actions(string $action): void
    {
        $fixture = $action === 'complete'
            ? $this->createApprovedClaimFixture()
            : $this->createClaimFixture();
        $fixture['admin']->update(['region_id' => null]);
        $before = $this->snapshot($fixture);

        $result = match ($action) {
            'approve' => $this->workflow()->approve($fixture['claim'], $this->approvalPayload(), $fixture['admin']->id),
            'reject' => $this->workflow()->reject($fixture['claim'], $this->rejectionPayload(), $fixture['admin']->id),
            'complete' => $this->workflow()->complete($fixture['claim'], $fixture['admin']->id),
        };

        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_invalid_match_context_does_not_change_claim_related_records_or_notify_user(): void
    {
        $fixture = $this->createClaimFixture([], [], [
            'status_pencocokan' => WorkflowStatus::MATCH_COMPLETED,
        ]);
        $before = $this->snapshot($fixture);

        $result = $this->workflow()->approve($fixture['claim'], $this->approvalPayload(), $fixture['admin']->id);

        $this->assertFalse($result);
        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_transaction_rolls_back_when_approve_fails_after_claim_update(): void
    {
        $fixture = $this->createClaimFixture();
        $before = $this->snapshot($fixture);

        Pencocokan::updating(static function (): void {
            throw new RuntimeException('Simulasi gagal update pencocokan klaim.');
        });

        try {
            $this->workflow()->approve($fixture['claim'], $this->approvalPayload(), $fixture['admin']->id);
            $this->fail('Approve seharusnya gagal ketika update pencocokan gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi gagal update pencocokan klaim.', $exception->getMessage());
        } finally {
            Pencocokan::flushEventListeners();
        }

        $this->assertSame($before, $this->snapshot($fixture));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_normal_approve_reject_and_complete_flows_still_work(): void
    {
        $approveFixture = $this->createClaimFixture();

        $this->assertTrue($this->workflow()->approve($approveFixture['claim'], $this->approvalPayload(), $approveFixture['admin']->id));
        $this->assertDatabaseHas('klaims', [
            'id' => $approveFixture['claim']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $approveFixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
        ]);
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_disetujui')->count());

        UserNotification::query()->delete();
        $rejectFixture = $this->createClaimFixture();

        $this->assertTrue($this->workflow()->reject($rejectFixture['claim'], $this->rejectionPayload(), $rejectFixture['admin']->id));
        $this->assertDatabaseHas('klaims', [
            'id' => $rejectFixture['claim']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $rejectFixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
        ]);
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_ditolak')->count());

        UserNotification::query()->delete();
        $completeFixture = $this->createApprovedClaimFixture();

        $this->assertTrue($this->workflow()->complete($completeFixture['claim'], $completeFixture['admin']->id));
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_selesai')->count());
    }

    public function test_direct_claim_approve_reject_and_complete_flows_work(): void
    {
        $approveFixture = $this->createClaimFixture();
        $approveFixture['claim']->update([
            'laporan_hilang_id' => null,
            'pencocokan_id' => null,
        ]);
        $approveFixture['barang']->update([
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
        ]);

        $this->assertTrue($this->workflow()->approve($approveFixture['claim'], $this->approvalPayload(), $approveFixture['admin']->id));
        $this->assertDatabaseHas('klaims', [
            'id' => $approveFixture['claim']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $approveFixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
        ]);
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_disetujui')->count());

        UserNotification::query()->delete();
        $rejectFixture = $this->createClaimFixture();
        $rejectFixture['claim']->update([
            'laporan_hilang_id' => null,
            'pencocokan_id' => null,
        ]);
        $rejectFixture['barang']->update([
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
        ]);

        $this->assertTrue($this->workflow()->reject($rejectFixture['claim'], $this->rejectionPayload(), $rejectFixture['admin']->id));
        $this->assertDatabaseHas('klaims', [
            'id' => $rejectFixture['claim']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $rejectFixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
        ]);
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_ditolak')->count());

        UserNotification::query()->delete();
        $completeFixture = $this->createApprovedClaimFixture();
        $completeFixture['claim']->update([
            'laporan_hilang_id' => null,
            'pencocokan_id' => null,
        ]);
        $completeFixture['barang']->update([
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
        ]);

        $this->assertTrue($this->workflow()->complete($completeFixture['claim'], $completeFixture['admin']->id));
        $this->assertSame(1, UserNotification::query()->where('type', 'klaim_selesai')->count());
    }

    /**
     * @return array<string,array{string,?string}>
     */
    public static function nonCompletableStatusProvider(): array
    {
        return [
            'pending' => [WorkflowStatus::CLAIM_LEGACY_PENDING, WorkflowStatus::CLAIM_UNDER_REVIEW],
            'rejected' => [WorkflowStatus::CLAIM_LEGACY_REJECTED, WorkflowStatus::CLAIM_REJECTED],
            'completed' => [WorkflowStatus::CLAIM_LEGACY_APPROVED, WorkflowStatus::CLAIM_COMPLETED],
        ];
    }

    /**
     * @return array<string,array{string}>
     */
    public static function workflowActionProvider(): array
    {
        return [
            'approve' => ['approve'],
            'reject' => ['reject'],
            'complete' => ['complete'],
        ];
    }

    private function workflow(): ClaimVerificationWorkflowService
    {
        return $this->app->make(ClaimVerificationWorkflowService::class);
    }

    /**
     * @param array<string,mixed> $claimOverrides
     * @param array<string,mixed> $barangOverrides
     * @param array<string,mixed> $matchOverrides
     * @return array{admin:Admin,user:User,finder:User,lostReport:LaporanBarangHilang,barang:Barang,match:Pencocokan,claim:Klaim}
     */
    private function createClaimFixture(array $claimOverrides = [], array $barangOverrides = [], array $matchOverrides = []): array
    {
        $admin = $this->createAdmin();
        $user = $this->createUser('claim-policy-user-' . str()->uuid());
        $finder = $this->createUser('claim-policy-finder-' . str()->uuid());
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ponsel Workflow',
            'lokasi_hilang' => 'Aula',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang di aula.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_CLAIMED,
            'tampil_di_home' => false,
        ]);

        $barang = Barang::query()->create(array_merge([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ponsel Workflow',
            'deskripsi' => 'Ditemukan di aula.',
            'lokasi_ditemukan' => 'Aula',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ], $barangOverrides));

        $match = Pencocokan::query()->create(array_merge([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
            'matched_at' => now(),
        ], $matchOverrides));

        $claim = Klaim::query()->create(array_merge([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $barang->id,
            'pencocokan_id' => $match->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Menunggu verifikasi.',
        ], $claimOverrides));

        return compact('admin', 'user', 'finder', 'lostReport', 'barang', 'match', 'claim');
    }

    /**
     * @param array<string,mixed> $claimOverrides
     * @return array{admin:Admin,user:User,finder:User,lostReport:LaporanBarangHilang,barang:Barang,match:Pencocokan,claim:Klaim}
     */
    private function createApprovedClaimFixture(array $claimOverrides = []): array
    {
        return $this->createClaimFixture(array_merge([
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ], $claimOverrides), [
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
        ], [
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_APPROVED,
        ]);
    }

    /**
     * @param array{admin:Admin,user:User,finder:User,lostReport:LaporanBarangHilang,barang:Barang,match:Pencocokan,claim:Klaim} $fixture
     */
    private function createCompletedClaimForSameItem(array $fixture): Klaim
    {
        return Klaim::query()->create([
            'laporan_hilang_id' => $fixture['lostReport']->id,
            'barang_id' => $fixture['barang']->id,
            'pencocokan_id' => $fixture['match']->id,
            'user_id' => $fixture['user']->id,
            'admin_id' => $fixture['admin']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
            'catatan' => 'Klaim selesai lain.',
        ]);
    }

    /**
     * @param array{lostReport:LaporanBarangHilang,barang:Barang,match:Pencocokan,claim:Klaim} $fixture
     * @return array<string,string|null>
     */
    private function snapshot(array $fixture): array
    {
        $claim = $fixture['claim']->fresh();
        $barang = $fixture['barang']->fresh();
        $lostReport = $fixture['lostReport']->fresh();
        $match = $fixture['match']->fresh();

        return [
            'claim_status_klaim' => $claim?->status_klaim,
            'claim_status_verifikasi' => $claim?->status_verifikasi,
            'claim_admin_id' => (string) ($claim?->admin_id ?? ''),
            'claim_skor_validitas' => is_null($claim?->skor_validitas) ? null : (string) $claim?->skor_validitas,
            'barang_status_barang' => $barang?->status_barang,
            'barang_status_laporan' => $barang?->status_laporan,
            'lost_status_laporan' => $lostReport?->status_laporan,
            'match_status' => $match?->status_pencocokan,
        ];
    }

    /**
     * @return array<string,string>
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
     * @return array<string,string>
     */
    private function rejectionPayload(): array
    {
        return [
            'identitas_pelapor_valid' => '0',
            'detail_barang_valid' => '0',
            'kronologi_valid' => '0',
            'bukti_visual_valid' => '0',
            'kecocokan_data_laporan' => '0',
            'catatan_verifikasi_admin' => 'Bukti tidak cukup.',
            'alasan_penolakan' => 'Data klaim tidak sesuai.',
        ];
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Workflow',
            'email' => 'super-workflow-' . str()->uuid() . '@example.test',
            'username' => 'super-workflow-' . str()->uuid(),
            'password' => Hash::make('password123'),
        ]);
        $region = Wilayah::query()->create(['nama_wilayah' => 'Wilayah Workflow ' . str()->uuid()]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Workflow',
            'email' => 'admin-workflow-' . str()->uuid() . '@example.test',
            'username' => 'admin-workflow-' . str()->uuid(),
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Workflow No. 1',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function createUser(string $username): User
    {
        $user = User::query()->create([
            'name' => 'User Workflow',
            'nama' => 'User Workflow',
            'username' => $username,
            'email' => $username . '@example.test',
            'nomor_telepon' => '08123456789',
            'password' => Hash::make('password123'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
