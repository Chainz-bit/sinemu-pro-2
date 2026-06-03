<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wilayah;
use App\Services\Google\GoogleIdTokenVerifier;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_login_with_username_and_receives_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Mobile User',
            'username' => 'mobileuser',
            'email' => 'mobile@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/login', [
            'login' => 'mobileuser',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);
    }

    public function test_mobile_user_can_register_and_receives_token(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Register Mobile',
            'email' => 'register-mobile@example.test',
            'username' => 'registermobile',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '081234567890',
            'alamat' => 'Indramayu',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Registrasi berhasil')
            ->assertJsonPath('user.name', 'Register Mobile')
            ->assertJsonPath('user.email', 'register-mobile@example.test')
            ->assertJsonPath('user.username', 'registermobile')
            ->assertJsonPath('user.phone', '081234567890')
            ->assertJsonPath('user.alamat', 'Indramayu')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token')
            ->assertJsonStructure(['token']);

        $user = User::query()->where('email', 'register-mobile@example.test')->firstOrFail();

        $this->assertSame('Register Mobile', $user->name);
        $this->assertSame('registermobile', $user->username);
        $this->assertSame('081234567890', $user->nomor_telepon);
        $this->assertSame('Indramayu', $user->alamat);
        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);

        $this->assertDatabaseMissing('admins', [
            'email' => 'register-mobile@example.test',
        ]);
    }

    public function test_mobile_register_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'duplicate-email@example.test',
            'username' => 'existingemailuser',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Duplicate Email',
            'email' => 'duplicate-email@example.test',
            'username' => 'newduplicateemailuser',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '081234567890',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_mobile_register_rejects_duplicate_username(): void
    {
        User::factory()->create([
            'email' => 'existing-username@example.test',
            'username' => 'duplicateusername',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Duplicate Username',
            'email' => 'new-username@example.test',
            'username' => 'duplicateusername',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '081234567890',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonValidationErrors(['username']);
    }

    public function test_mobile_register_rejects_wrong_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Wrong Confirmation',
            'email' => 'wrong-confirmation@example.test',
            'username' => 'wrongconfirmation',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
            'phone' => '081234567890',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'wrong-confirmation@example.test',
        ]);
    }

    public function test_google_login_auto_registers_new_mobile_user_and_returns_token(): void
    {
        $this->fakeGoogleToken([
            'sub' => 'google-new-user-123',
            'email' => 'nama.google@example.test',
            'name' => 'Nama Google',
            'picture' => 'https://example.test/avatar.jpg',
        ]);

        $this->postJson('/api/login/google', [
            'id_token' => 'valid-google-token',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil')
            ->assertJsonPath('user.name', 'Nama Google')
            ->assertJsonPath('user.email', 'nama.google@example.test')
            ->assertJsonPath('user.username', 'nama_google')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['token']);

        $user = User::query()->where('email', 'nama.google@example.test')->firstOrFail();

        $this->assertSame('google-new-user-123', $user->google_id);
        $this->assertSame('https://example.test/avatar.jpg', $user->avatar);
        $this->assertSame('nama_google', $user->username);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);
    }

    public function test_google_login_reuses_existing_user_without_duplicate(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing User',
            'username' => 'existinguser',
            'email' => 'existing-google@example.test',
            'password' => Hash::make('password'),
            'google_id' => null,
            'avatar' => null,
        ]);

        $this->fakeGoogleToken([
            'sub' => 'google-existing-123',
            'email' => 'existing-google@example.test',
            'name' => 'Existing Google',
            'picture' => 'https://example.test/existing-avatar.jpg',
        ]);

        $this->postJson('/api/login/google', [
            'id_token' => 'valid-google-token',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'existing-google@example.test')
            ->assertJsonPath('user.username', 'existinguser')
            ->assertJsonStructure(['token']);

        $this->assertSame(1, User::query()->where('email', 'existing-google@example.test')->count());
        $this->assertSame('google-existing-123', $user->refresh()->google_id);
        $this->assertSame('https://example.test/existing-avatar.jpg', $user->avatar);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);
    }

    public function test_google_login_returns_validation_error_when_email_missing(): void
    {
        $this->fakeGoogleToken([
            'sub' => 'google-no-email-123',
            'name' => 'No Email',
        ]);

        $this->postJson('/api/login/google', [
            'id_token' => 'valid-google-token',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Email Google tidak tersedia.');
    }

    public function test_google_login_rejects_invalid_id_token(): void
    {
        $this->fakeGoogleToken(null);

        $this->postJson('/api/login/google', [
            'id_token' => 'invalid-google-token',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token Google tidak valid.');
    }

    public function test_profile_requires_sanctum_token(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_user_can_create_lost_report_without_trusting_mobile_user_id(): void
    {
        $user = $this->createUser('lost-owner');
        $otherUser = $this->createUser('lost-other');
        $admin = $this->createActiveAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dokumen']);

        Sanctum::actingAs($user);

        $this->postJson('/api/laporan/hilang', [
            'user_id' => $otherUser->id,
            'admin_id' => $admin->id,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
            'verified_by_admin_id' => $admin->id,
            'tampil_di_home' => true,
            'nama_barang' => 'Dompet hitam',
            'kategori_id' => $kategori->id,
            'wilayah_id' => $admin->region_id,
            'lokasi' => 'Kampus Polindra',
            'deskripsi' => 'Dompet warna hitam',
            'tanggal_hilang' => '2026-05-26',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'hilang')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.kategori_id', $kategori->id);

        $this->assertDatabaseHas('laporan_barang_hilangs', [
            'user_id' => $user->id,
            'nama_barang' => 'Dompet hitam',
            'kategori_id' => $kategori->id,
            'region_id' => $admin->region_id,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => null,
            'verified_at' => null,
        ]);

        $this->assertDatabaseMissing('laporan_barang_hilangs', [
            'user_id' => $otherUser->id,
            'nama_barang' => 'Dompet hitam',
        ]);

        $this->assertDatabaseMissing('laporan_barang_hilangs', [
            'user_id' => $user->id,
            'nama_barang' => 'Dompet hitam',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
        ]);
    }

    public function test_user_can_create_found_report_without_trusting_mobile_workflow_fields(): void
    {
        $user = $this->createUser('found-owner');
        $otherUser = $this->createUser('found-other');
        $admin = $this->createActiveAdmin();
        $otherAdmin = $this->createActiveAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        Sanctum::actingAs($user);

        $this->postJson('/api/laporan/temuan', [
            'user_id' => $otherUser->id,
            'admin_id' => $otherAdmin->id,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
            'verified_by_admin_id' => $otherAdmin->id,
            'tampil_di_home' => true,
            'nama_barang' => 'Powerbank hitam',
            'kategori_id' => $kategori->id,
            'wilayah_id' => $admin->region_id,
            'lokasi' => 'Kantin',
            'deskripsi' => 'Powerbank ditemukan di meja kantin',
            'tanggal_ditemukan' => '2026-05-26',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'temuan')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.claimable', false);

        $this->assertDatabaseHas('barangs', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Powerbank hitam',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
            'verified_by_admin_id' => null,
            'verified_at' => null,
        ]);

        $this->assertDatabaseMissing('barangs', [
            'user_id' => $otherUser->id,
            'admin_id' => $otherAdmin->id,
            'nama_barang' => 'Powerbank hitam',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'tampil_di_home' => true,
        ]);
    }

    public function test_public_laporan_endpoint_only_returns_verified_published_reports_for_any_owner(): void
    {
        $viewer = $this->createUser('public-viewer');
        $otherUser = $this->createUser('public-other');
        $admin = $this->createActiveAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        LaporanBarangHilang::query()->create([
            'user_id' => $viewer->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'kategori_barang' => $kategori->nama_kategori,
            'nama_barang' => 'Submitted Hilang Milik Sendiri',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => '2026-05-26',
            'keterangan' => 'Belum diverifikasi',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => true,
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $viewer->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Submitted Temuan Milik Sendiri',
            'deskripsi' => 'Belum diverifikasi',
            'lokasi_ditemukan' => 'Kantin',
            'tanggal_ditemukan' => '2026-05-26',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => true,
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $otherUser->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'kategori_barang' => $kategori->nama_kategori,
            'nama_barang' => 'Submitted Hilang User Lain',
            'lokasi_hilang' => 'Aula',
            'tanggal_hilang' => '2026-05-26',
            'keterangan' => 'Belum diverifikasi',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => true,
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $otherUser->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Rejected Temuan Publik',
            'deskripsi' => 'Ditolak pengelola',
            'lokasi_ditemukan' => 'Parkiran',
            'tanggal_ditemukan' => '2026-05-26',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_REJECTED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'tampil_di_home' => true,
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $viewer->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'kategori_barang' => $kategori->nama_kategori,
            'nama_barang' => 'Approved Hidden Hilang',
            'lokasi_hilang' => 'Lab',
            'tanggal_hilang' => '2026-05-26',
            'keterangan' => 'Sudah diverifikasi tapi tidak dipublikasi',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'tampil_di_home' => false,
        ]);

        LaporanBarangHilang::query()->create([
            'user_id' => $viewer->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'kategori_barang' => $kategori->nama_kategori,
            'nama_barang' => 'Approved Hilang Milik Sendiri',
            'lokasi_hilang' => 'Ruang kelas',
            'tanggal_hilang' => '2026-05-26',
            'keterangan' => 'Sudah diverifikasi',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'tampil_di_home' => true,
        ]);

        Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $otherUser->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Approved Temuan User Lain',
            'deskripsi' => 'Sudah diverifikasi',
            'lokasi_ditemukan' => 'Lobi',
            'tanggal_ditemukan' => '2026-05-26',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'tampil_di_home' => true,
        ]);

        Sanctum::actingAs($viewer);

        $publicResponse = $this->getJson('/api/laporan/publik')
            ->assertOk();

        $publicNames = collect($publicResponse->json('data'))->pluck('nama_barang');

        $this->assertTrue($publicNames->contains('Approved Hilang Milik Sendiri'));
        $this->assertTrue($publicNames->contains('Approved Temuan User Lain'));
        $this->assertFalse($publicNames->contains('Submitted Hilang Milik Sendiri'));
        $this->assertFalse($publicNames->contains('Submitted Temuan Milik Sendiri'));
        $this->assertFalse($publicNames->contains('Submitted Hilang User Lain'));
        $this->assertFalse($publicNames->contains('Rejected Temuan Publik'));
        $this->assertFalse($publicNames->contains('Approved Hidden Hilang'));

        $privateResponse = $this->getJson('/api/laporan')
            ->assertOk();

        $privateNames = collect($privateResponse->json('data'))->pluck('nama_barang');

        $this->assertTrue($privateNames->contains('Submitted Hilang Milik Sendiri'));
        $this->assertTrue($privateNames->contains('Submitted Temuan Milik Sendiri'));
        $this->assertFalse($privateNames->contains('Submitted Hilang User Lain'));
    }

    public function test_user_cannot_view_other_user_lost_report(): void
    {
        $user = $this->createUser('viewer');
        $owner = $this->createUser('owner');
        $admin = $this->createActiveAdmin();

        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $owner->id,
            'region_id' => $admin->region_id,
            'nama_barang' => 'Tas pemilik lain',
            'lokasi_hilang' => 'Terminal',
            'tanggal_hilang' => '2026-05-26',
            'keterangan' => 'Bukan milik user login',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/laporan/hilang/'.$laporan->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'Tidak punya akses untuk data ini.');
    }

    public function test_user_can_claim_approved_found_item_once(): void
    {
        Storage::fake('local');

        $claimer = $this->createUser('claimer');
        $finder = $this->createUser('finder');
        $admin = $this->createActiveAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $laporan = LaporanBarangHilang::query()->create([
            'user_id' => $claimer->id,
            'region_id' => $admin->region_id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kunci motor',
            'lokasi_hilang' => 'Parkiran kampus',
            'tanggal_hilang' => '2026-05-26',
            'keterangan' => 'Kunci hilang dengan gantungan biru',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);

        $barang = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kunci motor',
            'deskripsi' => 'Kunci ditemukan di parkiran',
            'lokasi_ditemukan' => 'Parkiran kampus',
            'tanggal_ditemukan' => '2026-05-26',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
        ]);

        Pencocokan::query()->create([
            'laporan_hilang_id' => $laporan->id,
            'barang_id' => $barang->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'matched_at' => now(),
        ]);

        Sanctum::actingAs($claimer);

        $this->post('/api/barang-temuan/'.$barang->id.'/klaim', [
            'laporan_hilang_id' => $laporan->id,
            'kontak_pelapor' => '08123456789',
            'bukti_kepemilikan' => 'Saya memiliki kunci cadangan kendaraan.',
            'bukti_ciri_khusus' => 'Ada gantungan biru.',
            'bukti_lokasi_spesifik' => 'Parkiran kampus dekat pos.',
            'bukti_waktu_hilang' => '10:30',
            'bukti_foto' => [$this->fakePng('bukti-klaim.png')],
            'catatan' => 'Barang ini milik saya.',
            'persetujuan_klaim' => '1',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.barang_id', $barang->id)
            ->assertJsonPath('data.status', WorkflowStatus::CLAIM_LEGACY_PENDING);

        $this->assertDatabaseHas('klaims', [
            'laporan_hilang_id' => $laporan->id,
            'barang_id' => $barang->id,
            'user_id' => $claimer->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'catatan' => 'Barang ini milik saya.',
        ]);

        $this->assertDatabaseHas('barangs', [
            'id' => $barang->id,
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
        ]);

        $this->post('/api/barang-temuan/'.$barang->id.'/klaim', [
            'laporan_hilang_id' => $laporan->id,
            'kontak_pelapor' => '08123456789',
            'bukti_kepemilikan' => 'Saya memiliki kunci cadangan kendaraan.',
            'bukti_ciri_khusus' => 'Ada gantungan biru.',
            'bukti_lokasi_spesifik' => 'Parkiran kampus dekat pos.',
            'bukti_waktu_hilang' => '10:30',
            'bukti_foto' => [$this->fakePng('bukti-klaim-kedua.png')],
            'persetujuan_klaim' => '1',
        ], ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('message', 'Barang ini sedang tidak tersedia untuk diklaim.');
    }

    public function test_found_item_detail_includes_claimability_metadata(): void
    {
        $claimer = $this->createUser('claim-metadata');
        $finder = $this->createUser('finder-metadata');
        $admin = $this->createActiveAdmin();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $claimableBarang = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Jam tangan',
            'deskripsi' => 'Jam tangan ditemukan di aula',
            'lokasi_ditemukan' => 'Aula',
            'tanggal_ditemukan' => '2026-05-26',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
        ]);

        Sanctum::actingAs($claimer);

        $this->getJson('/api/laporan/temuan/'.$claimableBarang->id)
            ->assertOk()
            ->assertJsonPath('data.status_barang', WorkflowStatus::FOUND_AVAILABLE)
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.claimable', true)
            ->assertJsonPath('data.claim_block_reason', null);

        Sanctum::actingAs($finder);

        $this->getJson('/api/laporan/temuan/'.$claimableBarang->id)
            ->assertOk()
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.claimable', false)
            ->assertJsonPath('data.claim_block_reason', 'Tidak bisa mengklaim barang temuan milik sendiri.');

        $previouslyClaimedBarang = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => $admin->region_id,
            'user_id' => $finder->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Powerbank',
            'deskripsi' => 'Powerbank ditemukan di kantin',
            'lokasi_ditemukan' => 'Kantin',
            'tanggal_ditemukan' => '2026-05-27',
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
        ]);

        Klaim::query()->create([
            'barang_id' => $previouslyClaimedBarang->id,
            'user_id' => $claimer->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
            'catatan' => 'Klaim sebelumnya',
        ]);

        Sanctum::actingAs($claimer);

        $this->getJson('/api/laporan/temuan/'.$previouslyClaimedBarang->id)
            ->assertOk()
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.claimable', true)
            ->assertJsonPath('data.claim_block_reason', null);
    }

    public function test_user_can_only_mark_own_notification_as_read(): void
    {
        $user = $this->createUser('notif-owner');
        $otherUser = $this->createUser('notif-other');

        $ownNotification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'status_klaim',
            'title' => 'Status klaim diperbarui',
            'message' => 'Klaim sedang diproses.',
        ]);

        $otherNotification = UserNotification::query()->create([
            'user_id' => $otherUser->id,
            'type' => 'status_klaim',
            'title' => 'Milik user lain',
            'message' => 'Tidak boleh diubah.',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/notifikasi/'.$ownNotification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.dibaca', true);

        $this->assertNotNull($ownNotification->refresh()->read_at);

        $this->patchJson('/api/notifikasi/'.$otherNotification->id.'/read')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tidak punya akses untuk data ini.');
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

    private function fakePng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sinemu-mobile-api-');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function createActiveAdmin(): Admin
    {
        $region = Wilayah::query()->create([
            'nama_wilayah' => 'Indramayu '.str()->random(6),
        ]);

        return Admin::query()->create([
            'region_id' => $region->id,
            'nama' => 'Admin Wilayah',
            'email' => 'admin-'.str()->random(8).'@example.test',
            'nomor_telepon' => '08111111111',
            'username' => 'admin-'.str()->random(8),
            'password' => Hash::make('password'),
            'instansi' => 'Polindra',
            'status_verifikasi' => Admin::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function fakeGoogleToken(?array $payload): void
    {
        $this->app->instance(GoogleIdTokenVerifier::class, new class($payload) implements GoogleIdTokenVerifier
        {
            /**
             * @param array<string, mixed>|null $payload
             */
            public function __construct(private readonly ?array $payload)
            {
            }

            /**
             * @return array<string, mixed>|null
             */
            public function verify(string $idToken): ?array
            {
                return $idToken === 'valid-google-token' ? $this->payload : null;
            }
        });
    }
}
