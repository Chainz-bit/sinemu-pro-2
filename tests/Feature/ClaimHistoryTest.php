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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_history_shows_user_claims(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $otherUser = $this->createUser(
            email: 'other-history@example.com',
            username: 'other-history',
            phone: '081111111114'
        );

        $claim = $this->createClaim($admin, $user, 'Laptop Lenovo', WorkflowStatus::CLAIM_UNDER_REVIEW);
        $this->createClaim($admin, $otherUser, 'Payung Hitam', WorkflowStatus::CLAIM_UNDER_REVIEW);

        $response = $this->actingAs($user)->get(route('user.claim-history'));

        $response->assertOk();
        $response->assertViewHas('search', '');
        $response->assertViewHas('statusFilter', 'semua');
        $response->assertViewHas('typeFilter', 'semua');

        $claims = $response->viewData('claims');

        $this->assertInstanceOf(LengthAwarePaginator::class, $claims);
        $this->assertSame(1, $claims->total());

        $items = collect($claims->items());
        $this->assertSame($claim->id, $items->first()->id);
        $this->assertSame('Laptop Lenovo', $items->first()->item_name);
        $this->assertSame('Barang Temuan', $items->first()->item_type);
        $this->assertSame('Menunggu Tinjauan', $items->first()->status_text);
        $this->assertSame('menunggu_tinjauan', $items->first()->status_key);
        $this->assertStringContainsString((string) $claim->barang_id, $items->first()->detail_url);
    }

    public function test_claim_history_applies_search_status_and_type_filters(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();

        $this->createClaim($admin, $user, 'Laptop Lenovo', WorkflowStatus::CLAIM_UNDER_REVIEW);
        $this->createClaim($admin, $user, 'Tablet Xiaomi', WorkflowStatus::CLAIM_APPROVED);
        $this->createClaim($admin, $user, 'Tablet Redmi', WorkflowStatus::CLAIM_REJECTED);

        $response = $this->actingAs($user)->get(route('user.claim-history', [
            'search' => 'Tablet',
            'status' => 'sedang_diproses',
            'type' => 'temuan',
        ]));

        $response->assertOk();
        $response->assertViewHas('search', 'Tablet');
        $response->assertViewHas('statusFilter', 'sedang_diproses');
        $response->assertViewHas('typeFilter', 'temuan');

        $claims = $response->viewData('claims');

        $this->assertInstanceOf(LengthAwarePaginator::class, $claims);
        $this->assertSame(1, $claims->total());

        $items = collect($claims->items());
        $this->assertSame('Tablet Xiaomi', $items->first()->item_name);
        $this->assertSame('Sedang Diproses', $items->first()->status_text);
        $this->assertSame('sedang_diproses', $items->first()->status_key);
    }

    private function createClaim(Admin $admin, User $user, string $itemName, string $verificationStatus): Klaim
    {
        $kategori = Kategori::query()->firstOrCreate(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => $itemName,
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDays(2)->toDateString(),
            'keterangan' => 'Hilang di area kampus',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => $itemName,
            'deskripsi' => 'Ditemukan di ruang baca',
            'lokasi_ditemukan' => 'Ruang Baca',
            'tanggal_ditemukan' => now()->subDay()->toDateString(),
            'status_barang' => $verificationStatus === WorkflowStatus::CLAIM_COMPLETED ? 'sudah_dikembalikan' : 'dalam_proses_klaim',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
            'lokasi_pengambilan' => 'Loket Barang Temuan',
            'penanggung_jawab_pengambilan' => 'Petugas Loket',
            'kontak_pengambilan' => '08123456789',
            'jam_layanan_pengambilan' => '08:00-15:00',
        ]);

        return Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => match ($verificationStatus) {
                WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW => 'pending',
                WorkflowStatus::CLAIM_REJECTED => 'ditolak',
                default => 'disetujui',
            },
            'status_verifikasi' => $verificationStatus,
            'catatan' => 'Bukti klaim',
            'bukti_foto' => ['verifikasi-klaim/2026/04/bukti.jpg'],
            'bukti_ciri_khusus' => 'Ada stiker kampus',
            'bukti_lokasi_spesifik' => 'Ruang baca',
            'bukti_waktu_hilang' => '10:00:00',
        ]);
    }

    private function createUser(
        string $email = 'claim-history@example.com',
        string $username = 'claim-history',
        string $phone = '081111111115'
    ): User {
        $user = User::query()->create([
            'name' => 'User Claim History',
            'nama' => 'User Claim History',
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
            'nama' => 'Super Claim History',
            'email' => 'claim-history-super@example.com',
            'username' => 'claim-history-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Claim History',
            'email' => 'claim-history-admin@example.com',
            'username' => 'claim-history-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Riwayat Klaim No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
