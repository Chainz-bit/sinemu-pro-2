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
use App\Support\WorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyScopeAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_scope_audit_command_reports_unscoped_data_without_mutating_records(): void
    {
        $admin = $this->createAdminWithoutRegion();
        $user = $this->createUser();
        $kategori = Kategori::query()->create(['nama_kategori' => 'Elektronik']);

        $lostReport = LaporanBarangHilang::query()->create([
            'user_id' => $user->id,
            'region_id' => null,
            'nama_barang' => 'Laptop Legacy',
            'lokasi_hilang' => 'Perpustakaan Lama',
            'tanggal_hilang' => now()->subDay()->toDateString(),
            'keterangan' => 'Belum punya wilayah',
            'sumber_laporan' => 'lapor_hilang',
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $foundItem = Barang::query()->create([
            'admin_id' => $admin->id,
            'region_id' => null,
            'user_id' => $user->id,
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Laptop Legacy',
            'deskripsi' => 'Ditemukan sebelum scope wilayah rapi',
            'lokasi_ditemukan' => 'Perpustakaan Lama',
            'tanggal_ditemukan' => now()->toDateString(),
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'status_laporan' => WorkflowStatus::REPORT_SUBMITTED,
            'tampil_di_home' => false,
        ]);

        $match = Pencocokan::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'admin_id' => $admin->id,
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
            'catatan' => 'Perlu audit wilayah',
            'matched_at' => now(),
        ]);

        Klaim::query()->create([
            'laporan_hilang_id' => $lostReport->id,
            'barang_id' => $foundItem->id,
            'pencocokan_id' => $match->id,
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'status_klaim' => WorkflowStatus::CLAIM_LEGACY_PENDING,
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
            'catatan' => 'Menunggu scope',
        ]);

        $before = $this->snapshotAuditedTables();

        $exitCode = Artisan::call('sinemu:audit-legacy-scope', ['--sample' => 5]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Mode: read-only, tidak mengubah data.', $output);
        $this->assertStringContainsString('[barangs]', $output);
        $this->assertStringContainsString('region_id_null: 1', $output);
        $this->assertStringContainsString('[laporan_barang_hilangs]', $output);
        $this->assertStringContainsString('active_region_id_null: 1', $output);
        $this->assertStringContainsString('[klaims]', $output);
        $this->assertStringContainsString('pending_without_barang_region: 1', $output);
        $this->assertStringContainsString('[pencocokans]', $output);
        $this->assertStringContainsString('active_relation_region_missing: 1', $output);
        $this->assertStringContainsString('[recommendations]', $output);

        $this->assertSame($before, $this->snapshotAuditedTables());
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function snapshotAuditedTables(): array
    {
        return [
            'barangs' => DB::table('barangs')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'laporan_barang_hilangs' => DB::table('laporan_barang_hilangs')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'klaims' => DB::table('klaims')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'pencocokans' => DB::table('pencocokans')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'admins' => DB::table('admins')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    private function createAdminWithoutRegion(): Admin
    {
        $superAdmin = SuperAdmin::query()->create([
            'nama' => 'Super Admin Audit',
            'email' => 'audit-super@example.com',
            'username' => 'audit-super',
            'password' => Hash::make('password123'),
        ]);

        return Admin::query()->create([
            'super_admin_id' => $superAdmin->id,
            'region_id' => null,
            'nama' => 'Admin Audit',
            'email' => 'audit-admin@example.com',
            'username' => 'audit-admin',
            'password' => Hash::make('password123'),
            'instansi' => 'Kampus SINEMU',
            'kecamatan' => null,
            'alamat_lengkap' => 'Jl. Audit No. 1',
            'status_verifikasi' => 'active',
        ]);
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'User Audit',
            'nama' => 'User Audit',
            'username' => 'user-audit',
            'email' => 'audit-user@example.com',
            'nomor_telepon' => '081111111199',
            'password' => Hash::make('password123'),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
