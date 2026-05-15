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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClaimEvidenceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_claim_evidence(): void
    {
        Storage::fake('local');
        $claim = $this->createClaimWithEvidence();

        $this->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertForbidden();
    }

    public function test_claim_owner_can_view_private_evidence_and_other_user_cannot(): void
    {
        Storage::fake('local');
        $claim = $this->createClaimWithEvidence();
        $otherUser = $this->createUser('other-evidence-user@example.com', 'other-evidence-user');

        $this->actingAs($claim->user)
            ->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff');

        $this->actingAs($otherUser)
            ->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertForbidden();
    }

    public function test_authorized_manager_can_view_evidence_and_other_manager_cannot(): void
    {
        Storage::fake('local');
        $claim = $this->createClaimWithEvidence();
        $otherAdmin = $this->createAdmin('other-evidence-admin@example.com', 'other-evidence-admin', 'Wilayah Lain');

        $this->actingAs($claim->admin, 'admin')
            ->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertOk();

        $this->actingAs($otherAdmin, 'admin')
            ->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_claim_evidence(): void
    {
        Storage::fake('local');
        $claim = $this->createClaimWithEvidence();
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Evidence',
            'email' => 'super-evidence-view@example.com',
            'username' => 'super-evidence-view',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($superAdmin, 'super_admin')
            ->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertOk();
    }

    public function test_legacy_public_evidence_is_only_read_through_authorized_controller(): void
    {
        Storage::fake('public');
        $claim = $this->createClaimWithEvidence('verifikasi-klaim/2026/05/legacy.png', 'public');

        $this->actingAs($claim->user)
            ->get(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]))
            ->assertOk();

        $this->get(route('media.image', ['folder' => 'verifikasi-klaim', 'path' => '2026/05/legacy.png']))
            ->assertNotFound();
    }

    public function test_missing_or_malicious_evidence_path_returns_404(): void
    {
        Storage::fake('local');
        $missingClaim = $this->createClaimWithEvidence('private/verifikasi-klaim/2026/05/missing.png', null);
        $maliciousClaim = $this->createClaimWithEvidence('private/verifikasi-klaim/../secret.png', null);

        $this->actingAs($missingClaim->user)
            ->get(route('claims.evidence.show', ['klaim' => $missingClaim->id, 'index' => 0]))
            ->assertNotFound();

        $this->actingAs($maliciousClaim->user)
            ->get(route('claims.evidence.show', ['klaim' => $maliciousClaim->id, 'index' => 0]))
            ->assertNotFound();
    }

    public function test_new_claim_upload_stores_evidence_on_private_local_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createUser('private-upload-user@example.com', 'private-upload-user');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Laptop Private',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang di meja baca',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'sumber_laporan' => 'lapor_hilang',
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Private',
            'deskripsi' => 'Ditemukan di perpustakaan',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'matched_at' => now(),
        ]);

        $this->actingAs($user)->post(route('user.claims.store'), [
            'barang_id' => $foundItem->id,
            'laporan_hilang_id' => $lostReport->id,
            'kontak_pelapor' => '081111111114',
            'bukti_kepemilikan' => 'Nomor seri cocok',
            'bukti_ciri_khusus' => 'Ada stiker',
            'bukti_lokasi_spesifik' => 'Meja baca',
            'bukti_waktu_hilang' => '09:30',
            'bukti_foto' => [$this->fakePng('bukti-private.png')],
            'persetujuan_klaim' => '1',
        ])->assertRedirect(route('user.claim-history'));

        $claim = Klaim::query()->firstOrFail();
        $path = $claim->bukti_foto[0] ?? '';

        $this->assertStringStartsWith('private/verifikasi-klaim/', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(0, Storage::disk('public')->allFiles('verifikasi-klaim'));
    }

    public function test_claim_detail_view_uses_authorized_evidence_route(): void
    {
        Storage::fake('local');
        $claim = $this->createClaimWithEvidence();

        $response = $this->actingAs($claim->admin, 'admin')
            ->get(route('admin.claim-verifications.show', $claim));

        $response->assertOk();
        $response->assertSee(route('claims.evidence.show', ['klaim' => $claim->id, 'index' => 0]), false);
        $response->assertDontSee('/media/verifikasi-klaim', false);
        $response->assertDontSee('/storage/verifikasi-klaim', false);
    }

    public function test_claim_evidence_audit_command_is_read_only(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $claim = $this->createClaimWithEvidence('verifikasi-klaim/2026/05/legacy.png', 'public');
        $before = $claim->fresh()?->bukti_foto;

        $exitCode = Artisan::call('sinemu:audit-claim-evidence-files', ['--sample' => 5]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Mode: read-only, tidak mengubah file atau data.', $output);
        $this->assertStringContainsString('legacy_public_paths: 1', $output);
        $this->assertSame($before, $claim->fresh()?->bukti_foto);
        Storage::disk('public')->assertExists('verifikasi-klaim/2026/05/legacy.png');
    }

    public function test_migration_command_defaults_to_dry_run_without_changing_database_or_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $claim = $this->createClaimWithEvidence('verifikasi-klaim/2026/05/default-dry.png', 'public');
        $before = $claim->fresh()?->bukti_foto;

        $exitCode = Artisan::call('sinemu:migrate-claim-evidence-to-private');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringContainsString('will_migrate: 1', $output);
        $this->assertStringContainsString('database_changes: 0', $output);
        $this->assertSame($before, $claim->fresh()?->bukti_foto);
        Storage::disk('public')->assertExists('verifikasi-klaim/2026/05/default-dry.png');
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    public function test_migration_command_execute_copies_legacy_public_file_to_private_and_keeps_public_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $claim = $this->createClaimWithEvidence('verifikasi-klaim/2026/05/execute.png', 'public');

        $exitCode = Artisan::call('sinemu:migrate-claim-evidence-to-private', ['--execute' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('EXECUTE MODE', $output);
        $this->assertStringContainsString('files_copied: 1', $output);
        $this->assertStringContainsString('database_changes: 1', $output);
        $newPath = $claim->fresh()?->bukti_foto[0] ?? '';
        $this->assertStringStartsWith('private/verifikasi-klaim/', $newPath);
        $this->assertNotSame('verifikasi-klaim/2026/05/execute.png', $newPath);
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('public')->assertExists('verifikasi-klaim/2026/05/execute.png');
    }

    public function test_migration_command_preserves_multiple_evidence_order_and_skips_private_paths(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('verifikasi-klaim/2026/05/first.png', $this->pngBytes());
        Storage::disk('public')->put('verifikasi-klaim/2026/05/third.png', $this->pngBytes());
        Storage::disk('local')->put('private/verifikasi-klaim/2026/05/second.png', $this->pngBytes());
        $claim = $this->createClaimWithEvidence('private/verifikasi-klaim/2026/05/placeholder.png', null);
        $claim->forceFill([
            'bukti_foto' => [
                'verifikasi-klaim/2026/05/first.png',
                'private/verifikasi-klaim/2026/05/second.png',
                'verifikasi-klaim/2026/05/third.png',
            ],
        ])->save();

        Artisan::call('sinemu:migrate-claim-evidence-to-private', ['--execute' => true, '--claim-id' => $claim->id]);

        $paths = $claim->fresh()?->bukti_foto ?? [];
        $this->assertCount(3, $paths);
        $this->assertStringStartsWith('private/verifikasi-klaim/', $paths[0]);
        $this->assertSame('private/verifikasi-klaim/2026/05/second.png', $paths[1]);
        $this->assertStringStartsWith('private/verifikasi-klaim/', $paths[2]);
        $this->assertNotSame($paths[0], $paths[2]);
        Storage::disk('public')->assertExists('verifikasi-klaim/2026/05/first.png');
        Storage::disk('public')->assertExists('verifikasi-klaim/2026/05/third.png');
    }

    public function test_migration_command_reports_missing_and_unsafe_paths_without_crashing(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $claim = $this->createClaimWithEvidence('private/verifikasi-klaim/2026/05/placeholder.png', null);
        $claim->forceFill([
            'bukti_foto' => [
                'verifikasi-klaim/2026/05/missing.png',
                '../secret.jpg',
                'https://example.com/a.jpg',
                'verifikasi-klaim/2026/05/file.txt',
            ],
        ])->save();

        $exitCode = Artisan::call('sinemu:migrate-claim-evidence-to-private', ['--execute' => true, '--claim-id' => $claim->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('missing_files: 1', $output);
        $this->assertStringContainsString('skipped: 4', $output);
        $this->assertStringContainsString('unsafe_path', $output);
        $this->assertStringContainsString('external_url', $output);
        $this->assertStringContainsString('unsupported_extension', $output);
        $this->assertSame([
            'verifikasi-klaim/2026/05/missing.png',
            '../secret.jpg',
            'https://example.com/a.jpg',
            'verifikasi-klaim/2026/05/file.txt',
        ], $claim->fresh()?->bukti_foto);
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    public function test_migration_command_cleans_copied_private_file_when_database_update_fails(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $claim = $this->createClaimWithEvidence('verifikasi-klaim/2026/05/db-fail.png', 'public');

        DB::unprepared("CREATE TRIGGER fail_klaim_update BEFORE UPDATE ON klaims BEGIN SELECT RAISE(ABORT, 'forced failure'); END;");

        $exitCode = Artisan::call('sinemu:migrate-claim-evidence-to-private', ['--execute' => true, '--claim-id' => $claim->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('database_update_failed', $output);
        $this->assertSame(['verifikasi-klaim/2026/05/db-fail.png'], $claim->fresh()?->bukti_foto);
        Storage::disk('public')->assertExists('verifikasi-klaim/2026/05/db-fail.png');
        $this->assertCount(0, Storage::disk('local')->allFiles('private/verifikasi-klaim'));
    }

    private function createClaimWithEvidence(
        string $path = 'private/verifikasi-klaim/2026/05/evidence.png',
        ?string $disk = 'local'
    ): Klaim {
        static $sequence = 0;
        $sequence++;

        if ($disk) {
            Storage::disk($disk)->put($path, $this->pngBytes());
        }

        $user = $this->createUser('evidence-user-' . $sequence . '@example.com', 'evidence-user-' . $sequence);
        $admin = $this->createAdmin(
            'evidence-admin-' . $sequence . '@example.com',
            'evidence-admin-' . $sequence,
            'Wilayah Evidence ' . $sequence
        );
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dokumen']);
        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Barang Evidence',
            'lokasi_hilang' => 'Aula',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang di aula',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'sumber_laporan' => 'lapor_hilang',
            'tampil_di_home' => false,
        ]);
        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Barang Evidence',
            'deskripsi' => 'Ditemukan di aula',
            'lokasi_ditemukan' => 'Aula',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);
        $match = Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
            'matched_at' => now(),
        ]);

        return Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $match->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'bukti_foto' => [$path],
            'bukti_ciri_khusus' => 'Ada stiker kecil',
            'bukti_lokasi_spesifik' => 'Dekat pintu',
            'bukti_waktu_hilang' => '10:30',
        ]);
    }

    private function createUser(string $email = 'evidence-user@example.com', string $username = 'evidence-user'): User
    {
        $user = User::query()->create([
            'name' => 'User Evidence',
            'nama' => 'User Evidence',
            'username' => $username,
            'email' => $email,
            'nomor_telepon' => '081111111120',
            'password' => Hash::make('password123'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(
        string $email = 'evidence-admin@example.com',
        string $username = 'evidence-admin',
        string $regionName = 'Wilayah Evidence'
    ): Admin {
        $superAdmin = SuperAdmin::query()->firstOrCreate(
            ['email' => 'super-' . $email],
            [
                'nama' => 'Super Evidence',
                'username' => 'super-' . $username,
                'password' => Hash::make('password123'),
            ]
        );
        $region = Wilayah::query()->create(['nama_wilayah' => $regionName]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Evidence',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Indramayu',
            'alamat_lengkap' => 'Jl. Evidence No. 1',
            'status_verifikasi' => 'active',
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sinemu-evidence-');
        file_put_contents($path, $this->pngBytes());

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
    }
}
