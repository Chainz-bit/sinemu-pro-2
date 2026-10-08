<?php

namespace App\Services\User\Claims;

use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClaimInstructionService
{
    /**
     * @return array<string, mixed>
     */
    public function buildInstructionData(Klaim $klaim): array
    {
        $klaim->loadMissing([
            'barang.kategori',
            'barang.admin.region',
            'barang.admin',
            'admin.region',
            'laporanHilang',
            'user',
        ]);

        $barang = $klaim->barang;
        $admin = $barang?->admin ?? $klaim->admin;
        $user = $klaim->user;

        $statusKey = ClaimStatusPresenter::key(
            statusKlaim: (string) $klaim->status_klaim,
            statusVerifikasi: (string) ($klaim->status_verifikasi ?? ''),
            statusBarang: (string) ($barang?->status_barang ?? '')
        );

        $isReadyForPickup = ($statusKey === 'disetujui' || (string) $klaim->status_verifikasi === WorkflowStatus::CLAIM_APPROVED);
        $isCompleted = ($statusKey === 'selesai' || (string) $klaim->status_verifikasi === WorkflowStatus::CLAIM_COMPLETED);

        $statusMeta = match ($statusKey) {
            'disetujui' => [
                'text' => 'Siap Diambil',
                'class' => 'status-diproses',
                'badge_class' => 'badge-ready-for-pickup',
                'description' => 'Klaim Anda telah diverifikasi dan disetujui. Silakan datangi posko layanan untuk mengambil barang fisik.',
            ],
            'selesai' => [
                'text' => 'Sudah Diambil (Selesai)',
                'class' => 'status-selesai',
                'badge_class' => 'badge-completed',
                'description' => 'Barang telah berhasil diserahkan kepada Anda dan proses klaim telah selesai.',
            ],
            'ditolak' => [
                'text' => 'Tidak Disetujui',
                'class' => 'status-ditolak',
                'badge_class' => 'badge-rejected',
                'description' => 'Pengajuan klaim tidak disetujui oleh pengelola. Silakan periksa alasan penolakan di bawah.',
            ],
            default => [
                'text' => 'Menunggu Tinjauan',
                'class' => 'status-dalam_peninjauan',
                'badge_class' => 'badge-pending',
                'description' => 'Klaim Anda sedang dalam proses pemeriksaan bukti kepemilikan oleh pengelola.',
            ],
        };

        // Resolve Posko info
        $poskoName = trim((string) ($barang?->lokasi_pengambilan ?? ''));
        if ($poskoName === '') {
            $poskoName = trim((string) ($admin?->instansi ?? 'Posko Layanan SiNemu'));
        }

        $poskoAddress = trim((string) ($barang?->alamat_pengambilan ?? ''));
        if ($poskoAddress === '') {
            $poskoAddress = trim((string) ($admin?->alamat_lengkap ?? $admin?->pickup_address ?? ''));
        }
        if ($poskoAddress === '') {
            $poskoAddress = 'Posko layanan pengelola wilayah ' . ($admin?->kecamatan ?? 'kampus/instansi');
        }

        $poskoWilayah = trim((string) ($barang?->region?->nama_wilayah ?? $admin?->kecamatan ?? $admin?->region?->nama_wilayah ?? '-'));
        $jamLayanan = trim((string) ($barang?->jam_layanan_pengambilan ?? ''));
        if ($jamLayanan === '') {
            $jamLayanan = 'Senin - Jumat, 08:30 - 15:30 WIB';
        }

        $penanggungJawab = trim((string) ($barang?->penanggung_jawab_pengambilan ?? ''));
        if ($penanggungJawab === '') {
            $penanggungJawab = trim((string) ($admin?->nama ?? 'Petugas Posko Layanan'));
        }

        // Contact Resolution
        $contactRaw = trim((string) ($barang?->kontak_pengambilan ?? ''));
        if ($contactRaw === '') {
            $contactRaw = trim((string) ($admin?->nomor_telepon ?? ''));
        }

        $waUrl = null;
        $telHref = null;
        $claimCode = '#KLM-' . str_pad((string) $klaim->id, 5, '0', STR_PAD_LEFT);
        $cleanPhone = preg_replace('/[^0-9]/', '', $contactRaw) ?? '';

        if ($cleanPhone !== '') {
            $telHref = 'tel:' . $contactRaw;
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            } elseif (!str_starts_with($cleanPhone, '62')) {
                $cleanPhone = '62' . $cleanPhone;
            }

            $waText = "Halo Petugas SiNemu, saya ingin konfirmasi pengambilan barang temuan terkait tiket klaim {$claimCode} (" . ($barang?->nama_barang ?? 'Barang Temuan') . ").";
            $waUrl = 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($waText);
        }

        $adminEmail = filter_var($admin?->email ?? '', FILTER_VALIDATE_EMAIL) ? $admin->email : null;

        // Image resolution
        $itemImageUrl = $this->resolveItemImageUrl(
            (string) ($barang?->foto_barang ?? $klaim->laporanHilang?->foto_barang ?? ''),
            !is_null($klaim->barang_id) ? 'barang-temuan' : 'barang-hilang'
        );

        // Bukti foto URLs for user view
        $buktiFotoList = collect((array) ($klaim->bukti_foto ?? []))
            ->map(function ($path, $index) use ($klaim) {
                return [
                    'index' => $index,
                    'url' => route('claims.evidence.show', ['klaim' => $klaim->id, 'index' => $index]),
                ];
            })
            ->values()
            ->all();

        return [
            'klaim' => $klaim,
            'barang' => $barang,
            'admin' => $admin,
            'user' => $user,
            'claimCode' => $claimCode,
            'statusKey' => $statusKey,
            'statusMeta' => $statusMeta,
            'isReadyForPickup' => $isReadyForPickup,
            'isCompleted' => $isCompleted,
            'poskoName' => $poskoName,
            'poskoAddress' => $poskoAddress,
            'poskoWilayah' => $poskoWilayah,
            'jamLayanan' => $jamLayanan,
            'penanggungJawab' => $penanggungJawab,
            'catatanPosko' => $barang?->catatan_pengambilan,
            'contactRaw' => $contactRaw,
            'waUrl' => $waUrl,
            'telHref' => $telHref,
            'adminEmail' => $adminEmail,
            'itemImageUrl' => $itemImageUrl,
            'buktiFotoList' => $buktiFotoList,
            'pageTitle' => 'Tiket Pengambilan Barang ' . $claimCode . ' - SiNemu',
        ];
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
