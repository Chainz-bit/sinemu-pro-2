<?php

namespace App\Services\Admin\InputItems;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\User;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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
            'status_barang' => 'tersedia',
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

        Barang::create($payload);
    }

    private function resolveReporter(string $reporterName): ?User
    {
        $hasNamaColumn = Schema::hasColumn('users', 'nama');
        $hasNameColumn = Schema::hasColumn('users', 'name');

        return User::query()
            ->where(function ($query) use ($reporterName, $hasNamaColumn, $hasNameColumn): void {
                if ($hasNamaColumn) {
                    $query->where('nama', $reporterName);
                }

                if ($hasNameColumn) {
                    if ($hasNamaColumn) {
                        $query->orWhere('name', $reporterName);
                    } else {
                        $query->where('name', $reporterName);
                    }
                }

                $query->orWhere('email', $reporterName);
            })
            ->first();
    }
}
