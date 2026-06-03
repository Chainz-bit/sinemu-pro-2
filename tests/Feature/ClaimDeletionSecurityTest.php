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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ClaimDeletionSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('blockedClaimStatusProvider')]
    public function test_user_cannot_delete_non_terminal_claim(string $claimStatus, ?string $verificationStatus): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture($claimStatus, $verificationStatus);
        $this->assertActiveDeletionIsRejected(
            $this->actingAs($fixture['user'])
                ->delete(route('user.claim-history.destroy', $fixture['klaim'])),
            $fixture
        );
    }

    public function test_user_can_delete_rejected_claim_and_evidence_is_cleaned_after_commit(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );

        $this->actingAs($fixture['user'])
            ->delete(route('user.claim-history.destroy', $fixture['klaim']))
            ->assertRedirect(route('user.claim-history'))
            ->assertSessionHas('status', 'Riwayat klaim berhasil dihapus.');

        $this->assertDatabaseMissing('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertMissing($fixture['proofPath']);
    }

    public function test_user_cannot_delete_claim_owned_by_another_user(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );
        $otherUser = $this->createUser('other-user');

        $this->actingAs($otherUser)
            ->delete(route('user.claim-history.destroy', $fixture['klaim']))
            ->assertForbidden();

        $this->assertDatabaseHas('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertExists($fixture['proofPath']);
    }

    public function test_manager_cannot_delete_active_claim(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();

        $this->assertActiveDeletionIsRejected(
            $this->actingAs($fixture['admin'], 'admin')
                ->delete(route('admin.claim-verifications.destroy', $fixture['klaim'])),
            $fixture
        );
    }

    public function test_manager_can_delete_rejected_claim_in_scope(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );

        $this->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.claim-verifications.destroy', $fixture['klaim']))
            ->assertRedirect()
            ->assertSessionHas('status', 'Data klaim berhasil dihapus.');

        $this->assertDatabaseMissing('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertMissing($fixture['proofPath']);
    }

    public function test_manager_cannot_delete_claim_owned_by_another_manager(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );
        $otherAdmin = $this->createAdmin('other-manager');

        $this->actingAs($otherAdmin, 'admin')
            ->delete(route('admin.claim-verifications.destroy', $fixture['klaim']))
            ->assertForbidden();

        $this->assertDatabaseHas('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertExists($fixture['proofPath']);
    }

    #[DataProvider('nonActiveManagerStatusProvider')]
    public function test_non_active_manager_cannot_access_claim_delete(string $managerStatus): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );
        $fixture['admin']->update(['status_verifikasi' => $managerStatus]);

        $this->actingAs($fixture['admin'], 'admin')
            ->delete(route('admin.claim-verifications.destroy', $fixture['klaim']))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertExists($fixture['proofPath']);
    }

    public function test_database_delete_failure_keeps_evidence_file(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );
        Klaim::deleting(static function (): void {
            throw new RuntimeException('Simulasi kegagalan delete klaim.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($fixture['user'])
                ->delete(route('user.claim-history.destroy', $fixture['klaim']));
            $this->fail('Delete klaim seharusnya gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi kegagalan delete klaim.', $exception->getMessage());
        }

        $this->assertDatabaseHas('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertExists($fixture['proofPath']);
    }

    public function test_failed_file_cleanup_after_commit_is_logged_without_server_error(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(
            WorkflowStatus::CLAIM_LEGACY_REJECTED,
            WorkflowStatus::CLAIM_REJECTED
        );
        Log::spy();
        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andThrow(new RuntimeException('Simulasi storage tidak tersedia.'));

        $this->actingAs($fixture['user'])
            ->delete(route('user.claim-history.destroy', $fixture['klaim']))
            ->assertRedirect(route('user.claim-history'))
            ->assertSessionHas('status', 'Riwayat klaim berhasil dihapus.');

        $this->assertDatabaseMissing('klaims', ['id' => $fixture['klaim']->id]);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(static fn (string $message): bool => $message === 'Evidence klaim gagal dihapus setelah record klaim dihapus.');
    }

    /**
     * @return array<string,array{string,?string}>
     */
    public static function blockedClaimStatusProvider(): array
    {
        return [
            'pending' => [WorkflowStatus::CLAIM_LEGACY_PENDING, WorkflowStatus::CLAIM_SUBMITTED],
            'submitted' => [WorkflowStatus::CLAIM_LEGACY_PENDING, WorkflowStatus::CLAIM_SUBMITTED],
            'under review' => [WorkflowStatus::CLAIM_LEGACY_PENDING, WorkflowStatus::CLAIM_UNDER_REVIEW],
            'approved' => [WorkflowStatus::CLAIM_LEGACY_APPROVED, WorkflowStatus::CLAIM_APPROVED],
            'completed' => [WorkflowStatus::CLAIM_LEGACY_APPROVED, WorkflowStatus::CLAIM_COMPLETED],
        ];
    }

    /**
     * @return array<string,array{string}>
     */
    public static function nonActiveManagerStatusProvider(): array
    {
        return [
            'pending' => [Admin::STATUS_PENDING],
            'rejected' => [Admin::STATUS_REJECTED],
            'inactive' => [Admin::STATUS_INACTIVE],
        ];
    }

    /**
     * @return array{user:User,admin:Admin,laporan:LaporanBarangHilang,barang:Barang,pencocokan:Pencocokan,klaim:Klaim,proofPath:string}
     */
    private function createClaimFixture(
        string $claimStatus = WorkflowStatus::CLAIM_LEGACY_PENDING,
        ?string $verificationStatus = WorkflowStatus::CLAIM_UNDER_REVIEW
    ): array {
        $user = $this->createUser('claim-owner');
        $finder = $this->createUser('claim-finder');
        $admin = $this->createAdmin('claim-manager');
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ponsel Klaim',
            'lokasi_hilang' => 'Aula kampus',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang saat kegiatan kampus.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_CLAIMED,
        ]);
        $barang = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ponsel Klaim',
            'deskripsi' => 'Ditemukan setelah kegiatan kampus.',
            'lokasi_ditemukan' => 'Aula kampus',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_CLAIMED,
        ]);
        $pencocokan = Pencocokan::query()->create([
            'laporan_hilang_id' => $laporan->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
            'matched_at' => now(),
        ]);
        $proofPath = 'private/verifikasi-klaim/2026/06/'.str()->uuid().'.png';
        Storage::disk('local')->put($proofPath, 'claim-proof');
        $klaim = Klaim::query()->create([
            'laporan_hilang_id' => $laporan->id,
            'barang_id' => $barang->id,
            'pencocokan_id' => $pencocokan->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => $claimStatus,
            'status_verifikasi' => $verificationStatus,
            'bukti_foto' => [$proofPath],
        ]);

        return compact('user', 'admin', 'laporan', 'barang', 'pencocokan', 'klaim', 'proofPath');
    }

    /**
     * @param array{user:User,admin:Admin,laporan:LaporanBarangHilang,barang:Barang,pencocokan:Pencocokan,klaim:Klaim,proofPath:string} $fixture
     */
    private function assertActiveDeletionIsRejected($response, array $fixture): void
    {
        $response
            ->assertRedirect()
            ->assertSessionHas('error', 'Klaim yang masih aktif tidak dapat dihapus.');

        $this->assertDatabaseHas('klaims', ['id' => $fixture['klaim']->id]);
        Storage::disk('local')->assertExists($fixture['proofPath']);
        $this->assertSame(WorkflowStatus::FOUND_CLAIM_IN_PROGRESS, $fixture['barang']->fresh()?->status_barang);
        $this->assertSame(WorkflowStatus::REPORT_CLAIMED, $fixture['laporan']->fresh()?->status_laporan);
        $this->assertSame(WorkflowStatus::MATCH_CLAIM_IN_PROGRESS, $fixture['pencocokan']->fresh()?->status_pencocokan);
    }

    private function createUser(string $username): User
    {
        return User::factory()->create([
            'name' => 'User '.$username,
            'username' => $username,
            'email' => $username.'@example.test',
            'nomor_telepon' => '08123456789',
            'password' => Hash::make('password'),
        ]);
    }

    private function createAdmin(string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super '.$username,
            'email' => 'super-'.$username.'@example.test',
            'username' => 'super-'.$username,
            'password' => Hash::make('password'),
        ]);
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah '.$username,
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin '.$username,
            'email' => $username.'@example.test',
            'nomor_telepon' => '08111111111',
            'username' => $username,
            'password' => Hash::make('password'),
            'instansi' => 'Polindra',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
    }
}
