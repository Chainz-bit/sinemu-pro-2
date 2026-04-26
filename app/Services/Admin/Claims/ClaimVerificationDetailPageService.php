<?php

namespace App\Services\Admin\Claims;

use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClaimVerificationDetailPageService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Klaim $klaim): array
    {
        $barang = $klaim->barang;
        $laporanHilang = $klaim->laporanHilang;

        $statusKey = ClaimStatusPresenter::key(
            statusKlaim: (string) $klaim->status_klaim,
            statusVerifikasi: Schema::hasColumn('klaims', 'status_verifikasi') ? (string) ($klaim->status_verifikasi ?? '') : null,
            statusBarang: (string) ($barang?->status_barang ?? '')
        );

        [$statusBarangLabel, $statusBarangClass] = $this->resolveFoundItemStatus((string) ($barang?->status_barang ?? ''));

        $pelapor = $klaim->user;

        return [
            'statusKey' => $statusKey,
            'statusLabel' => $this->resolveClaimStatusLabel($statusKey),
            'statusClass' => ClaimStatusPresenter::cssClass($statusKey),
            'namaBarang' => $barang?->nama_barang ?? $laporanHilang?->nama_barang ?? 'Barang tidak ditemukan',
            'kategoriNama' => $barang?->kategori?->nama_kategori ?? 'Tidak tersedia',
            'lokasi' => $barang?->lokasi_ditemukan ?? $laporanHilang?->lokasi_hilang ?? '-',
            'tanggalLaporan' => $barang?->tanggal_ditemukan ?? $laporanHilang?->tanggal_hilang ?? $klaim->created_at,
            'deskripsi' => $barang?->deskripsi ?? $laporanHilang?->keterangan ?? 'Belum ada deskripsi.',
            'fotoUrl' => $this->resolveItemImageUrl((string) ($barang?->foto_barang ?? $laporanHilang?->foto_barang ?? '')),
            'statusBarangLabel' => $statusBarangLabel,
            'statusBarangClass' => $statusBarangClass,
            'pelaporNama' => $pelapor?->nama ?? $pelapor?->name ?? 'Pengguna',
            'pelaporEmail' => $pelapor?->email ?? '-',
        ];
    }

    private function resolveClaimStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'menunggu' => 'Menunggu Verifikasi',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            default => 'Selesai',
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveFoundItemStatus(string $status): array
    {
        return match ($status) {
            WorkflowStatus::FOUND_AVAILABLE => ['Tersedia', 'status-dalam_peninjauan'],
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => ['Dalam Proses Klaim', 'status-diproses'],
            WorkflowStatus::FOUND_CLAIMED => ['Sudah Diklaim', 'status-selesai'],
            WorkflowStatus::FOUND_RETURNED => ['Selesai', 'status-selesai'],
            default => ['Tidak tersedia', 'status-dalam_peninjauan'],
        };
    }

    private function resolveItemImageUrl(string $fotoPath): string
    {
        $cleanPath = str_replace('\\', '/', trim($fotoPath, '/'));
        if ($cleanPath === '') {
            return asset('img/login-image.png');
        }

        if (Str::startsWith($cleanPath, ['http://', 'https://'])) {
            return $cleanPath;
        }

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (Str::startsWith($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');
        if (in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            return route('media.image', ['folder' => $folder, 'path' => $subPath]);
        }

        return asset('storage/' . $cleanPath);
    }
}
