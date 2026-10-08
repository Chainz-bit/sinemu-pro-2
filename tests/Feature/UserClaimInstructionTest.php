<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserClaimInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_dashboard_routes_approved_claim_to_claim_show_instead_of_public_item(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'MacBook Air M2',
            'lokasi_hilang' => 'Perpustakaan Lt. 2',
            'tanggal_hilang' => now()->subDays(2)->toDateString(),
            'keterangan' => 'Tertinggal di meja baca',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'MacBook Air M2',
            'deskripsi' => 'Ditemukan di meja baca pojok',
            'lokasi_ditemukan' => 'Perpustakaan Lt. 2',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'lokasi_pengambilan' => 'Posko Gedung Rektorat Lt. 1',
            'alamat_pengambilan' => 'Jl. Kampus Utama No. 10',
            'penanggung_jawab_pengambilan' => 'Bpk. Ahmad Petugas Posko',
            'kontak_pengambilan' => '081298765432',
            'jam_layanan_pengambilan' => '08:30 - 15:30 WIB',
        ]);

        $claim = Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
            'catatan' => 'Klaim telah disetujui',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti.jpg'],
            'bukti_ciri_khusus' => 'Ada casing matte transparan',
            'bukti_lokasi_spesifik' => 'Meja pojok',
            'bukti_waktu_hilang' => '14:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('user.dashboard'));

        $response->assertOk();
        $activities = $response->viewData('latestActivities');
        $claimActivity = collect($activities->items())->firstWhere('type', 'claim');

        $this->assertNotNull($claimActivity);
        $this->assertSame('terverifikasi', $claimActivity->status);
        $this->assertSame('Ambil Barang', $claimActivity->action_label);
        // Verify action url points to user.claims.show, NOT home.found-detail!
        $this->assertSame(route('user.claims.show', $claim->id), $claimActivity->detail_url);
        $this->assertStringNotContainsString(route('home.found-detail', $foundItem->id), $claimActivity->detail_url);
    }

    public function test_user_can_view_claim_instruction_ticket_page(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Dompet Kulit Cokelat',
            'deskripsi' => 'Ditemukan di kantin kampus',
            'lokasi_ditemukan' => 'Kantin Pusat',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => true,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'lokasi_pengambilan' => 'Posko Layanan Mahasiswa',
            'alamat_pengambilan' => 'Gedung Pelayanan Terpadu Lt. 1',
            'penanggung_jawab_pengambilan' => 'Ibu Siti Layanan',
            'kontak_pengambilan' => '081234567890',
            'jam_layanan_pengambilan' => '08:00 - 16:00 WIB',
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
            'catatan' => 'Bukti KTM dan KTP sesuai',
            'bukti_kepemilikan' => 'Ada kartu ATM dan SIM A',
            'bukti_ciri_khusus' => 'Ada goresan kecil di bagian lipatan',
        ]);

        $response = $this->actingAs($user)->get(route('user.claims.show', $claim->id));

        $response->assertOk();
        // 1. Status: Siap Diambil
        $response->assertSee('Siap Diambil');
        // 2. Kode/ID Klaim
        $claimCode = '#KLM-' . str_pad((string) $claim->id, 5, '0', STR_PAD_LEFT);
        $response->assertSee($claimCode);
        // 3. Lokasi Posko Layanan
        $response->assertSee('Posko Layanan Mahasiswa');
        $response->assertSee('Gedung Pelayanan Terpadu Lt. 1');
        $response->assertSee('08:00 - 16:00 WIB');
        // 4. Syarat penyerahan fisik KTM/KTP
        $response->assertSee('Membawa Identitas Asli');
        $response->assertSee('KTM');
        // 5. Tombol hubungi pengelola WhatsApp/Telepon
        $response->assertSee('Hubungi via WhatsApp');
        $response->assertSee('Telepon Posko');
    }

    public function test_user_cannot_view_another_users_claim_ticket(): void
    {
        $admin = $this->createAdmin();
        $userA = $this->createUser(email: 'user-a@example.com', username: 'usera', phone: '081111111121');
        $userB = $this->createUser(email: 'user-b@example.com', username: 'userb', phone: '081111111122');
        $kategori = Kategori::query()->create(['nama_kategori' => 'Dokumen']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $userA->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'KTP & SIM',
            'deskripsi' => 'Ditemukan di parkiran',
            'lokasi_ditemukan' => 'Parkiran Motor',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'user_id' => $userA->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ]);

        // User B tries to view User A's claim
        $response = $this->actingAs($userB)->get(route('user.claims.show', $claim->id));

        $response->assertForbidden();
    }

    public function test_public_found_detail_shows_approved_banner_and_hides_contradictory_warning_for_owner(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $otherUser = $this->createUser(email: 'other-detail@example.com', username: 'otherdetail', phone: '081111111123');
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Headphone Sony WH-1000XM4',
            'deskripsi' => 'Ditemukan di coworking space',
            'lokasi_ditemukan' => 'Coworking Space Lt. 3',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_APPROVED,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'tampil_di_home' => true,
        ]);

        $claim = Klaim::query()->create([
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
            'catatan' => 'Serial number terverifikasi',
        ]);

        // When the approved claim owner views the public detail page
        $responseOwner = $this->actingAs($user)->get(route('home.found-detail', $foundItem->id));

        $responseOwner->assertOk();
        // 1. Warning alert must NOT appear
        $responseOwner->assertDontSee('Anda wajib mengajukan klaim dengan bukti kepemilikan');
        // 2. Success banner MUST appear
        $responseOwner->assertSee('Klaim Anda untuk barang ini telah disetujui. Silakan ikuti instruksi pengambilan di bawah atau hubungi pengelola.');
        // 3. Direct button to collection instruction must appear
        $responseOwner->assertSee('Instruksi Pengambilan');
        $responseOwner->assertSee(route('user.claims.show', $claim->id));

        // When another user (not the claimant) views the same page
        $responseOther = $this->actingAs($otherUser)->get(route('home.found-detail', $foundItem->id));
        $responseOther->assertOk();
        $responseOther->assertDontSee('Klaim Anda untuk barang ini telah disetujui');
    }

    private function createUser(string $email = 'instruction-user@example.com', string $username = 'user-instruction', string $phone = '081111111120'): User
    {
        $user = User::query()->create([
            'name' => 'Budi Santoso',
            'nama' => 'Budi Santoso',
            'username' => $username,
            'email' => $email,
            'nomor_telepon' => $phone,
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Instruction',
            'email' => 'instruction-super@example.com',
            'username' => 'super-instruction',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Layanan Kampus',
            'email' => 'posko-admin@example.com',
            'username' => 'admin-instruction',
            'password' => Hash::make('password123'),
            'instansi' => 'Posko Pusat Layanan Kampus',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Rektorat No. 1 Gedung A',
            'nomor_telepon' => '081234567890',
            'status_verifikasi' => 'active',
        ]);
    }
}
