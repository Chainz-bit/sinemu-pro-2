<?php

namespace App\Services\Admin\InputItems;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\User;
use App\Support\Media\OptimizedImageUploader;
use App\Support\WorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InputItemService
{
    public function __construct(private readonly OptimizedImageUploader $imageUploader)
    {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function storeLostItem(array $validated, ?UploadedFile $photo): bool
    {
        $reporterName = trim((string) ($validated['nama_pelapor'] ?? ''));
        $reportDate = Carbon::parse((string) $validated['tanggal_waktu'])->toDateString();

        $user = $this->resolveReporter($reporterName);

        if (!$user) {
            return false;
        }

        $keterangan = trim((string) ($validated['deskripsi'] ?? ''));
        $fotoPath = $photo ? $this->imageUploader->upload($photo, 'barang-hilang/'.now()->format('Y/m')) : null;

        $payload = [
            'user_id' => (int) $user->id,
            'nama_barang' => $validated['nama_barang'],
            'lokasi_hilang' => $validated['lokasi'],
            'tanggal_hilang' => $reportDate,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'foto_barang' => $fotoPath,
        ];

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $payload['sumber_laporan'] = 'lapor_hilang';
        }
        if (Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home')) {
            $payload['tampil_di_home'] = true;
        }
        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            $payload['status_laporan'] = WorkflowStatus::REPORT_APPROVED;
            $payload['verified_at'] = now();
        }

        LaporanBarangHilang::create($payload);

        return true;
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function storeFoundItem(int $adminId, array $validated, ?UploadedFile $photo): void
    {
        $kategoriId = $validated['kategori_id'] ?? null;

        if (!$kategoriId) {
            $defaultKategori = Kategori::query()->first();
            if (!$defaultKategori) {
                $defaultKategori = Kategori::query()->create(['nama_kategori' => 'Umum']);
            }

            $kategoriId = $defaultKategori->id;
        }

        $deskripsiBase = trim((string) ($validated['deskripsi'] ?? ''));
        $reportDate = Carbon::parse((string) $validated['tanggal_waktu'])->toDateString();
        $metaPelapor = 'Pelapor/Penemu: '.trim((string) $validated['nama_pelapor']);
        $deskripsiGabungan = $deskripsiBase !== '' ? $deskripsiBase.PHP_EOL.PHP_EOL.$metaPelapor : $metaPelapor;

        $fotoPath = $photo ? $this->imageUploader->upload($photo, 'barang-temuan/'.now()->format('Y/m')) : null;

        $payload = [
            'admin_id' => $adminId,
            'kategori_id' => (int) $kategoriId,
            'nama_barang' => $validated['nama_barang'],
            'deskripsi' => $deskripsiGabungan,
            'lokasi_ditemukan' => $validated['lokasi'],
            'tanggal_ditemukan' => $reportDate,
            'status_barang' => WorkflowStatus::FOUND_AVAILABLE,
            'foto_barang' => $fotoPath,
            'lokasi_pengambilan' => trim((string) ($validated['lokasi_pengambilan'] ?? '')) ?: null,
            'alamat_pengambilan' => trim((string) ($validated['alamat_pengambilan'] ?? '')) ?: null,
            'penanggung_jawab_pengambilan' => trim((string) ($validated['penanggung_jawab_pengambilan'] ?? '')) ?: null,
            'kontak_pengambilan' => trim((string) ($validated['kontak_pengambilan'] ?? '')) ?: null,
            'jam_layanan_pengambilan' => trim((string) ($validated['jam_layanan_pengambilan'] ?? '')) ?: null,
            'catatan_pengambilan' => trim((string) ($validated['catatan_pengambilan'] ?? '')) ?: null,
        ];

        if (Schema::hasColumn('barangs', 'tampil_di_home')) {
            $payload['tampil_di_home'] = true;
        }
        if (Schema::hasColumn('barangs', 'status_laporan')) {
            $payload['status_laporan'] = WorkflowStatus::REPORT_APPROVED;
            $payload['verified_by_admin_id'] = $adminId;
            $payload['verified_at'] = now();
        }

        Barang::create($payload);
    }

    private function resolveReporter(string $reporterName): ?User
    {
        $normalizedReporter = trim($reporterName);
        if ($normalizedReporter === '') {
            $normalizedReporter = 'Pelapor Admin';
        }

        $hasNamaColumn = Schema::hasColumn('users', 'nama');
        $hasNameColumn = Schema::hasColumn('users', 'name');

        $existingUser = User::query()
            ->where(function ($query) use ($normalizedReporter, $hasNamaColumn, $hasNameColumn): void {
                if ($hasNamaColumn) {
                    $query->where('nama', $normalizedReporter);
                }

                if ($hasNameColumn) {
                    if ($hasNamaColumn) {
                        $query->orWhere('name', $normalizedReporter);
                    } else {
                        $query->where('name', $normalizedReporter);
                    }
                }

                $query->orWhere('email', $normalizedReporter);
            })
            ->first();

        if ($existingUser) {
            return $existingUser;
        }

        $baseSlug = Str::slug($normalizedReporter);
        if ($baseSlug === '') {
            $baseSlug = 'pelapor-admin';
        }

        $username = $baseSlug;
        $suffix = 1;
        while (User::query()->where('username', $username)->exists()) {
            $username = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $email = $username.'@sinemu.local';
        while (User::query()->where('email', $email)->exists()) {
            $email = $username.'-'.Str::random(4).'@sinemu.local';
        }

        return User::query()->create([
            'name' => $normalizedReporter,
            'nama' => $normalizedReporter,
            'username' => $username,
            'email' => $email,
            'password' => Str::random(24),
        ]);
    }
}
