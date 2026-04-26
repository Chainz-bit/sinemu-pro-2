<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimVerificationListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_verification_index_shows_claims_and_can_export_csv(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Laptop ASUS',
            'lokasi_hilang' => 'Perpustakaan',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Tertinggal di meja baca',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop ASUS',
            'deskripsi' => 'Ditemukan di perpustakaan',
            'lokasi_ditemukan' => 'Perpustakaan',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Sedang diverifikasi',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.claim-verifications'));

        $response->assertOk();
        $response->assertSee('Laptop ASUS');
        $response->assertSee('Verifikasi Klaim');
        $response->assertViewHas('sort', 'terbaru');

        $exportResponse = $this->actingAs($admin, 'admin')->get(route('admin.claim-verifications', [
            'export' => 1,
        ]));

        $exportResponse->assertOk();
        $exportResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $exportResponse->assertHeader('content-disposition', 'attachment; filename="verifikasi-klaim.csv"');
        $exportContent = $exportResponse->streamedContent();
        $this->assertStringContainsString('Pelapor', $exportContent);
        $this->assertStringContainsString('Laptop ASUS', $exportContent);
    }

    public function test_claim_verification_index_applies_search_status_and_date_filters(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Aksesoris']);

        $matchingDate = now()->subDay();

        $matchingLostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Kamera Canon',
            'lokasi_hilang' => 'Aula',
            'tanggal_hilang' => $matchingDate->toDateString(),
            'keterangan' => 'Hilang saat seminar',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $matchingFoundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Kamera Canon',
            'deskripsi' => 'Ditemukan di aula',
            'lokasi_ditemukan' => 'Aula',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_CLAIMED,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $matchingClaim = Klaim::query()->create([
            'laporan_hilang_id' => $matchingLostReport->id,
            'barang_id' => $matchingFoundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
            'catatan' => 'Disetujui admin',
        ]);
        $matchingClaim->forceFill([
            'created_at' => $matchingDate->copy()->setTime(10, 0),
            'updated_at' => $matchingDate->copy()->setTime(11, 0),
        ])->save();

        $otherLostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'nama_barang' => 'Payung Merah',
            'lokasi_hilang' => 'Kantin',
            'tanggal_hilang' => now()->subDays(3)->toDateString(),
            'keterangan' => 'Tertinggal setelah makan',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $otherFoundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Payung Merah',
            'deskripsi' => 'Ditemukan di kantin',
            'lokasi_ditemukan' => 'Kantin',
            'tanggal_ditemukan' => now()->subDays(2)->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_MATCHED,
            'tampil_di_home' => false,
        ]);

        $otherClaim = Klaim::query()->create([
            'laporan_hilang_id' => $otherLostReport->id,
            'barang_id' => $otherFoundItem->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_REJECTED,
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
            'catatan' => 'Ditolak admin',
        ]);
        $otherClaim->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.claim-verifications', [
            'search' => 'Kamera Canon',
            'status' => WorkflowStatus::CLAIM_LEGACY_APPROVED,
            'date' => $matchingDate->toDateString(),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $tableBody = $this->extractTableBody($content);

        $this->assertStringContainsString('Kamera Canon', $tableBody);
        $this->assertStringNotContainsString('Payung Merah', $tableBody);
        $response->assertViewHas('sort', 'terbaru');
    }

    private function extractTableBody(string $content): string
    {
        $start = strpos($content, '<tbody>');
        $end = strpos($content, '</tbody>');

        if ($start === false || $end === false || $end <= $start) {
            return $content;
        }

        return substr($content, $start, $end - $start);
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Klaim',
            'nama' => 'User Klaim',
            'username' => 'user-klaim',
            'email' => 'claim-user@example.com',
            'nomor_telepon' => '081111111115',
            'password' => 'password123',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createAdmin(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Claim',
            'email' => 'claim-super@example.com',
            'username' => 'claim-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'nama' => 'Admin Claim',
            'email' => 'claim-admin@example.com',
            'username' => 'claim-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => 'Sindang',
            'alamat_lengkap' => 'Jl. Claim No. 1',
            'status_verifikasi' => 'active',
        ]);
    }
}
