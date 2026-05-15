<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_upload_lost_report_photo(): void
    {
        Storage::fake('public');

        $this->post(route('user.lost-reports.store'), [
            'foto_barang' => $this->fakePng('barang.png'),
        ])->assertRedirect(route('login'));

        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_user_can_upload_valid_lost_report_photo_with_safe_relative_path(): void
    {
        Storage::fake('public');

        $user = $this->createUser();
        $region = Wilayah::query()->create(['nama_wilayah' => 'Indramayu']);
        $this->createAdminForRegion($region, 'admin-upload-lost-valid');

        $this->actingAs($user)->post(route('user.lost-reports.store'), [
            'nama_barang' => 'Dompet Aman',
            'region_id' => $region->id,
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Dompet warna hitam',
            'kontak_pelapor' => '081111111111',
            'foto_barang' => $this->fakePng('nama-asli-user.png'),
        ])->assertSessionHasNoErrors();

        $report = LaporanBarangHilang::query()->firstOrFail();
        $this->assertIsString($report->foto_barang);
        $this->assertStringStartsWith('barang-hilang/', $report->foto_barang);
        $this->assertStringNotContainsString('nama-asli-user', $report->foto_barang);
        $this->assertStringNotContainsString('..', $report->foto_barang);
        Storage::disk('public')->assertExists($report->foto_barang);
    }

    public function test_user_cannot_upload_php_or_oversized_lost_report_photo(): void
    {
        Storage::fake('public');

        $user = $this->createUser();
        $region = Wilayah::query()->create(['nama_wilayah' => 'Sindang']);
        $this->createAdminForRegion($region, 'admin-upload-lost-invalid');
        $payload = [
            'nama_barang' => 'Tas Aman',
            'region_id' => $region->id,
            'lokasi_hilang' => 'Kantin',
            'tanggal_hilang' => now()->toDateString(),
            'keterangan' => 'Tas biru',
            'kontak_pelapor' => '081111111112',
        ];

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $payload + [
                'foto_barang' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            ])
            ->assertRedirect(route('user.lost-reports.create'))
            ->assertSessionHasErrors('foto_barang');

        $this->actingAs($user)
            ->from(route('user.lost-reports.create'))
            ->post(route('user.lost-reports.store'), $payload + [
                'foto_barang' => $this->fakePng('besar.png', 2049),
            ])
            ->assertRedirect(route('user.lost-reports.create'))
            ->assertSessionHasErrors('foto_barang');

        $this->assertSame(0, LaporanBarangHilang::query()->count());
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_failed_claim_business_validation_does_not_store_uploaded_proofs(): void
    {
        Storage::fake('public');

        $user = $this->createUser('claim-upload-user@example.com', 'claim-upload-user');
        $admin = $this->createAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'HP Klaim',
            'lokasi_hilang' => 'Aula',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Hilang di aula',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'sumber_laporan' => 'lapor_hilang',
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'HP Klaim',
            'deskripsi' => 'Ditemukan di aula',
            'lokasi_ditemukan' => 'Aula',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
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
            'kontak_pelapor' => '081111111113',
            'bukti_kepemilikan' => 'Nomor seri',
            'bukti_ciri_khusus' => 'Ada stiker',
            'bukti_lokasi_spesifik' => 'Dekat panggung',
            'bukti_waktu_hilang' => '10:30',
            'bukti_foto' => [$this->fakePng('bukti.png')],
            'persetujuan_klaim' => '1',
        ])->assertSessionHas('error');

        $this->assertCount(0, Storage::disk('public')->allFiles('verifikasi-klaim'));
    }

    public function test_manager_cannot_upload_found_item_photo_outside_region(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        $otherRegion = Wilayah::query()->create(['nama_wilayah' => 'Luar Wilayah']);
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dokumen']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $otherRegion->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Map Luar',
            'deskripsi' => 'Tidak boleh diedit',
            'lokasi_ditemukan' => 'Kantor lain',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.found-items.update', $foundItem), [
                'nama_barang' => 'Map Diubah',
                'lokasi_ditemukan' => 'Lokasi Diubah',
                'tanggal_ditemukan' => now()->toDateString(),
                'foto_barang' => $this->fakePng('luar.png'),
            ])
            ->assertForbidden();

        $this->assertNull($foundItem->fresh()?->foto_barang);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_upload_file_audit_command_is_read_only(): void
    {
        Storage::fake('public');

        $user = $this->createUser('audit-upload-user@example.com', 'audit-upload-user');
        $user->forceFill(['profil' => 'profil-user/2026/05/missing.webp'])->save();
        Storage::disk('public')->put('barang-temuan/2026/05/orphan.webp', 'fake-image');

        $beforeUser = $user->fresh()?->profil;
        $beforeFiles = Storage::disk('public')->allFiles();

        $exitCode = Artisan::call('sinemu:audit-upload-files', ['--sample' => 5]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Mode: read-only, tidak mengubah file atau data.', $output);
        $this->assertStringContainsString('missing_files: 1', $output);
        $this->assertStringContainsString('orphan_files: 1', $output);
        $this->assertSame($beforeUser, $user->fresh()?->profil);
        $this->assertSame($beforeFiles, Storage::disk('public')->allFiles());
    }

    private function createUser(string $email = 'upload-user@example.com', string $username = 'upload-user'): User
    {
        $user = User::query()->create([
            'name' => 'User Upload',
            'nama' => 'User Upload',
            'username' => $username,
            'email' => $email,
            'nomor_telepon' => '081111111110',
            'password' => Hash::make('password123'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function fakePng(string $name, int $kilobytes = 1): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sinemu-upload-');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
        $targetBytes = max(1, $kilobytes) * 1024;
        file_put_contents($path, $png . str_repeat('0', max(0, $targetBytes - strlen($png))));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Upload',
            'email' => 'super-upload@example.com',
            'username' => 'super-upload',
            'password' => Hash::make('password123'),
        ]);
        $region = Wilayah::query()->create(['nama_wilayah' => 'Upload Region']);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin Upload',
            'email' => 'admin-upload@example.com',
            'username' => 'admin-upload',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Indramayu',
            'alamat_lengkap' => 'Jl. Upload No. 1',
            'status_verifikasi' => 'active',
        ]);
    }

    private function createAdminForRegion(Wilayah $region, string $username): Admin
    {
        $superAdmin = SuperAdmin::query()->firstOrCreate(
            ['email' => 'super-' . $username . '@example.com'],
            [
                'nama' => 'Super ' . $username,
                'username' => 'super-' . $username,
                'password' => Hash::make('password123'),
            ]
        );

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => $region->id,
            'nama' => 'Admin ' . $username,
            'email' => $username . '@example.com',
            'username' => $username,
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Indramayu',
            'alamat_lengkap' => 'Jl. Upload Region No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
