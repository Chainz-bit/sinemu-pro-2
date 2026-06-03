<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiClaimPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_claim_requires_authentication(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();

        $this->postJson('/api/barang-temuan/'.$fixture['barang']->id.'/klaim')
            ->assertUnauthorized();

        $this->assertSame(0, Klaim::query()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    public function test_api_claim_without_lost_report_uses_web_policy_stores_private_proof_and_notifies_assigned_manager(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(withMatch: false);
        $otherAdmin = $this->createActiveAdmin('other-manager-no-report');

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.barang_id', $fixture['barang']->id)
            ->assertJsonPath('data.status', WorkflowStatus::CLAIM_LEGACY_PENDING);

        $klaim = Klaim::query()->sole();
        $proofPath = $klaim->bukti_foto[0] ?? '';

        $this->assertNull($klaim->laporan_hilang_id);
        $this->assertNull($klaim->pencocokan_id);
        $this->assertSame($fixture['admin']->id, $klaim->admin_id);
        $this->assertSame('081234567890', $klaim->kontak);
        $this->assertSame('Saya memiliki kotak pembelian dengan nomor seri.', $klaim->bukti_kepemilikan);
        $this->assertSame(WorkflowStatus::CLAIM_UNDER_REVIEW, $klaim->status_verifikasi);
        $this->assertStringStartsWith('private/verifikasi-klaim/', $proofPath);
        Storage::disk('local')->assertExists($proofPath);

        $this->assertDatabaseHas('barangs', [
            'id' => $fixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'admin_id' => $fixture['admin']->id,
            'type' => 'klaim_baru',
        ]);
        $this->assertDatabaseMissing('admin_notifications', [
            'admin_id' => $otherAdmin->id,
            'type' => 'klaim_baru',
        ]);
    }

    public function test_api_claim_requires_evidence_without_side_effects(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();
        $notificationsBefore = AdminNotification::query()->count();
        $payload = $this->validClaimPayload();
        unset($payload['bukti_foto']);

        Sanctum::actingAs($fixture['claimer']);

        $this->postJson('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bukti_foto');

        $this->assertInvalidSubmissionHasNoSideEffects($notificationsBefore);
    }

    public function test_api_claim_rejects_unclaimable_item_without_side_effects(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture([
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
        ]);
        $notificationsBefore = AdminNotification::query()->count();

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload(), ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('message', 'Barang ini sedang tidak tersedia untuk diklaim.');

        $this->assertInvalidSubmissionHasNoSideEffects($notificationsBefore);
    }

    public function test_api_claim_rejects_lost_report_owned_by_another_user_without_side_effects(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();
        $fixture['laporan']->update(['user_id' => $fixture['finder']->id]);
        $notificationsBefore = AdminNotification::query()->count();

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload($fixture['laporan']), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pilih laporan barang hilang milik Anda yang valid sebelum mengajukan klaim.');

        $this->assertInvalidSubmissionHasNoSideEffects($notificationsBefore);
    }

    public function test_api_claim_rejects_unmatched_report_and_item_without_side_effects(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(withMatch: false);
        $notificationsBefore = AdminNotification::query()->count();

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload($fixture['laporan']), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Barang ini belum ditandai cocok oleh pengelola barang dengan laporan Anda.');

        $this->assertInvalidSubmissionHasNoSideEffects($notificationsBefore);
    }

    public function test_api_claim_rejects_active_duplicate_without_storing_new_proof_or_notification(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(withMatch: false);
        Klaim::query()->create([
            'laporan_hilang_id' => null,
            'barang_id' => $fixture['barang']->id,
            'pencocokan_id' => null,
            'user_id' => $fixture['claimer']->id,
            'admin_id' => $fixture['admin']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);
        $notificationsBefore = AdminNotification::query()->count();

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload(), ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('message', 'Anda sudah pernah mengajukan klaim aktif untuk barang ini.');

        $this->assertSame(1, Klaim::query()->count());
        $this->assertSame($notificationsBefore, AdminNotification::query()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    public function test_api_claim_allows_retry_after_rejected_claim(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture(withMatch: false);
        Klaim::query()->create([
            'laporan_hilang_id' => null,
            'barang_id' => $fixture['barang']->id,
            'pencocokan_id' => null,
            'user_id' => $fixture['claimer']->id,
            'admin_id' => $fixture['admin']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
        ]);
        $notificationsBefore = AdminNotification::query()->count();

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(2, Klaim::query()->count());
        $this->assertSame($notificationsBefore + 1, AdminNotification::query()->count());

        $newClaim = Klaim::query()->latest('id')->firstOrFail();
        $proofPath = $newClaim->bukti_foto[0] ?? '';

        $this->assertSame(WorkflowStatus::CLAIM_LEGACY_PENDING, $newClaim->status_klaim);
        $this->assertSame(WorkflowStatus::CLAIM_UNDER_REVIEW, $newClaim->status_verifikasi);
        Storage::disk('local')->assertExists($proofPath);
    }

    public function test_api_claim_rejects_completed_item_without_new_proof_or_notification(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();
        Klaim::query()->create([
            'laporan_hilang_id' => $fixture['laporan']->id,
            'barang_id' => $fixture['barang']->id,
            'pencocokan_id' => $fixture['pencocokan']->id,
            'user_id' => $fixture['claimer']->id,
            'admin_id' => $fixture['admin']->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
        ]);
        $notificationsBefore = AdminNotification::query()->count();

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload(), ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('message', 'Barang ini sudah selesai diklaim dan tidak dapat diajukan ulang.');

        $this->assertSame(1, Klaim::query()->count());
        $this->assertSame($notificationsBefore, AdminNotification::query()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    public function test_api_claim_database_failure_cleans_uploaded_proof_and_creates_no_notification(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();
        $notificationsBefore = AdminNotification::query()->count();
        DB::unprepared("CREATE TRIGGER fail_klaim_insert BEFORE INSERT ON klaims BEGIN SELECT RAISE(ABORT, 'forced failure'); END;");

        Sanctum::actingAs($fixture['claimer']);
        $this->withoutExceptionHandling();

        try {
            $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload(), ['Accept' => 'application/json']);
            $this->fail('Submit klaim seharusnya gagal saat insert database dipaksa gagal.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_klaim_insert');
        }

        $this->assertInvalidSubmissionHasNoSideEffects($notificationsBefore);
    }

    public function test_valid_api_claim_uses_web_policy_stores_private_proof_and_notifies_assigned_manager(): void
    {
        Storage::fake('local');

        $fixture = $this->createClaimFixture();
        $otherAdmin = $this->createActiveAdmin('other-manager');

        Sanctum::actingAs($fixture['claimer']);

        $this->post('/api/barang-temuan/'.$fixture['barang']->id.'/klaim', $this->validClaimPayload($fixture['laporan']), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.barang_id', $fixture['barang']->id)
            ->assertJsonPath('data.status', WorkflowStatus::CLAIM_LEGACY_PENDING);

        $klaim = Klaim::query()->sole();
        $proofPath = $klaim->bukti_foto[0] ?? '';

        $this->assertSame($fixture['laporan']->id, $klaim->laporan_hilang_id);
        $this->assertSame($fixture['pencocokan']->id, $klaim->pencocokan_id);
        $this->assertSame($fixture['admin']->id, $klaim->admin_id);
        $this->assertSame(WorkflowStatus::CLAIM_UNDER_REVIEW, $klaim->status_verifikasi);
        $this->assertStringStartsWith('private/verifikasi-klaim/', $proofPath);
        Storage::disk('local')->assertExists($proofPath);

        $this->assertDatabaseHas('barangs', [
            'id' => $fixture['barang']->id,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
        ]);
        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'id' => $fixture['laporan']->id,
            'status_laporan' => WorkflowStatus::REPORT_CLAIMED,
        ]);
        $this->assertDatabaseHas('pencocokans', [
            'id' => $fixture['pencocokan']->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'admin_id' => $fixture['admin']->id,
            'type' => 'klaim_baru',
        ]);
        $this->assertDatabaseMissing('admin_notifications', [
            'admin_id' => $otherAdmin->id,
            'type' => 'klaim_baru',
        ]);
    }

    /**
     * @param array<string,mixed> $barangOverrides
     * @return array{claimer:User,finder:User,admin:Admin,laporan:LaporanBarangHilang,barang:Barang,pencocokan:?Pencocokan}
     */
    private function createClaimFixture(array $barangOverrides = [], bool $withMatch = true): array
    {
        $claimer = $this->createUser('api-claimer');
        $finder = $this->createUser('api-finder');
        $admin = $this->createActiveAdmin('claim-manager');
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);
        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $claimer->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ponsel API',
            'lokasi_hilang' => 'Aula kampus',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Ponsel hilang saat kegiatan kampus.',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);
        $barang = Barang::query()->create(array_replace([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Ponsel API',
            'deskripsi' => 'Ponsel ditemukan setelah kegiatan kampus.',
            'lokasi_ditemukan' => 'Aula kampus',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ], $barangOverrides));
        $pencocokan = $withMatch
            ? Pencocokan::query()->create([
                'laporan_hilang_id' => $laporan->id,
                'barang_id' => $barang->id,
                'admin_id' => $admin->id,
                'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
                'matched_at' => now(),
            ])
            : null;

        return compact('claimer', 'finder', 'admin', 'laporan', 'barang', 'pencocokan');
    }

    /**
     * @return array<string,mixed>
     */
    private function validClaimPayload(?LaporanBarangHilang $laporan = null): array
    {
        $payload = [
            'kontak_pelapor' => '081234567890',
            'bukti_kepemilikan' => 'Saya memiliki kotak pembelian dengan nomor seri.',
            'bukti_ciri_khusus' => 'Ada goresan kecil pada sudut kanan.',
            'bukti_detail_isi' => 'Menggunakan casing abu-abu.',
            'bukti_lokasi_spesifik' => 'Kursi baris kedua dekat pintu.',
            'bukti_waktu_hilang' => '10:30',
            'bukti_foto' => [$this->fakePng('bukti-api.png')],
            'catatan' => 'Mohon diverifikasi.',
            'persetujuan_klaim' => '1',
        ];

        if ($laporan) {
            $payload['laporan_hilang_id'] = $laporan->id;
        }

        return $payload;
    }

    private function assertInvalidSubmissionHasNoSideEffects(int $notificationsBefore): void
    {
        $this->assertSame(0, Klaim::query()->count());
        $this->assertSame($notificationsBefore, AdminNotification::query()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
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

    private function createActiveAdmin(string $username): Admin
    {
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Wilayah '.str()->random(8),
        ]);

        return Admin::query()->create([
            'region_id' => $region->id,
            'nama' => 'Admin '.$username,
            'email' => $username.'-'.str()->random(8).'@example.test',
            'nomor_telepon' => '08111111111',
            'username' => $username.'-'.str()->random(8),
            'password' => Hash::make('password'),
            'instansi' => 'Polindra',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sinemu-api-claim-');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
