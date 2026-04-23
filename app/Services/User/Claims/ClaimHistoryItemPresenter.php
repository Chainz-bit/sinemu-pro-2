<?php

namespace App\Services\User\Claims;

use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use Illuminate\Support\Str;

class ClaimHistoryItemPresenter
{
    public function present(Klaim $claim, bool $hasStatusVerifikasi): object
    {
        [$statusText, $statusClass, $statusKey] = $this->resolveStatus($claim, $hasStatusVerifikasi);

        return (object) [
            'id' => (int) $claim->id,
            'item_name' => (string) ($claim->barang?->nama_barang ?? $claim->laporanHilang?->nama_barang ?? 'Klaim Barang'),
            'item_type' => !is_null($claim->barang_id) ? 'Barang Temuan' : 'Laporan Hilang',
            'item_image' => $this->resolveItemImageUrl(
                (string) ($claim->barang?->foto_barang ?? $claim->laporanHilang?->foto_barang ?? ''),
                !is_null($claim->barang_id) ? 'barang-temuan' : 'barang-hilang'
            ),
            'submitted_at' => $claim->created_at,
            'status_text' => $statusText,
            'status_class' => $statusClass,
            'status_key' => $statusKey,
            'status_detail' => $this->resolveStatusDetail($claim, $statusKey),
            'pickup_location' => $this->resolvePickupLocation($claim, $statusKey),
            'detail_url' => $this->resolveDetailUrl($claim),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveStatus(Klaim $claim, bool $hasStatusVerifikasi): array
    {
        $key = ClaimStatusPresenter::key(
            statusKlaim: (string) $claim->status_klaim,
            statusVerifikasi: $hasStatusVerifikasi ? (string) ($claim->status_verifikasi ?? '') : null,
            statusBarang: (string) ($claim->barang?->status_barang ?? '')
        );

        return match ($key) {
            'ditolak' => ['Tidak Disetujui', 'status-ditolak', 'tidak_disetujui'],
            'disetujui' => ['Sedang Diproses', 'status-diproses', 'sedang_diproses'],
            'selesai' => ['Selesai', 'status-selesai', 'selesai'],
            default => ['Menunggu Tinjauan', 'status-dalam_peninjauan', 'menunggu_tinjauan'],
        };
    }

    private function resolvePickupLocation(Klaim $claim, string $statusKey): string
    {
        if ($statusKey === 'menunggu_tinjauan') {
            return 'Menunggu Tinjauan';
        }
        if ($statusKey === 'tidak_disetujui') {
            return '-';
        }
        if ($statusKey === 'selesai') {
            return 'Sudah Diambil';
        }

        $location = trim((string) ($claim->barang?->lokasi_pengambilan ?? ''));
        if ($location !== '') {
            return $location;
        }

        $address = trim((string) ($claim->barang?->alamat_pengambilan ?? ''));
        if ($address !== '') {
            return $address;
        }

        $kecamatan = trim((string) ($claim->admin?->kecamatan ?? ''));
        if ($kecamatan !== '') {
            return $kecamatan;
        }

        return trim((string) ($claim->admin?->instansi ?? 'Hubungi Admin'));
    }

    private function resolveStatusDetail(Klaim $claim, string $statusKey): string
    {
        if ($statusKey === 'menunggu_tinjauan') {
            return 'Klaim sedang diperiksa admin. Pastikan bukti kepemilikan sudah lengkap.';
        }
        if ($statusKey === 'tidak_disetujui') {
            return 'Klaim ditolak. Periksa notifikasi dan lengkapi bukti untuk pengajuan berikutnya.';
        }
        if ($statusKey === 'selesai') {
            return 'Barang sudah diserahkan dan proses klaim dinyatakan selesai.';
        }

        $pieces = array_values(array_filter([
            trim((string) ($claim->barang?->penanggung_jawab_pengambilan ?? '')) !== ''
                ? ('Petugas: ' . trim((string) $claim->barang?->penanggung_jawab_pengambilan))
                : null,
            trim((string) ($claim->barang?->kontak_pengambilan ?? '')) !== ''
                ? ('Kontak: ' . trim((string) $claim->barang?->kontak_pengambilan))
                : null,
            trim((string) ($claim->barang?->jam_layanan_pengambilan ?? '')) !== ''
                ? ('Jam: ' . trim((string) $claim->barang?->jam_layanan_pengambilan))
                : null,
        ]));

        if ($pieces === []) {
            return 'Klaim disetujui. Lihat detail barang untuk informasi pengambilan.';
        }

        return 'Klaim disetujui. ' . implode(' | ', $pieces);
    }

    private function resolveDetailUrl(Klaim $claim): string
    {
        if (!is_null($claim->barang_id)) {
            return route('home.found-detail', $claim->barang_id);
        }

        if (!is_null($claim->laporan_hilang_id)) {
            return route('home.lost-detail', $claim->laporan_hilang_id);
        }

        return route('home');
    }

    private function resolveItemImageUrl(string $fotoPath, string $defaultFolder): string
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

        if ($subPath !== '') {
            return route('media.image', ['folder' => $defaultFolder, 'path' => $cleanPath]);
        }

        return asset('storage/' . $cleanPath);
    }
}
