<?php

namespace App\Services\User;

use App\Models\Klaim;
use App\Support\ClaimStatusPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDashboardClaimFeedService
{
    public function build(int $userId, bool $hasClaimVerificationColumn): Collection
    {
        return Klaim::query()
            ->where('user_id', $userId)
            ->with([
                'barang:id,nama_barang,lokasi_ditemukan,foto_barang,status_barang',
                'laporanHilang:id,nama_barang,lokasi_hilang,foto_barang',
            ])
            ->select(array_values(array_filter([
                'id',
                'status_klaim',
                $hasClaimVerificationColumn ? 'status_verifikasi' : null,
                'created_at',
                'updated_at',
                'barang_id',
                'laporan_hilang_id',
            ])))
            ->latest('updated_at')
            ->limit(16)
            ->get()
            ->map(function (Klaim $claim) {
                $claimKey = ClaimStatusPresenter::key(
                    statusKlaim: (string) $claim->status_klaim,
                    statusVerifikasi: (string) ($claim->status_verifikasi ?? ''),
                    statusBarang: (string) ($claim->barang?->status_barang ?? '')
                );
                $statusPayload = [
                    'status' => match ($claimKey) {
                        'menunggu' => 'menunggu_tinjauan',
                        'disetujui' => 'sedang_diproses',
                        'ditolak' => 'tidak_disetujui',
                        default => 'selesai',
                    },
                    'status_class' => match ($claimKey) {
                        'ditolak' => 'status-ditolak',
                        'selesai' => 'status-selesai',
                        'disetujui' => 'status-diproses',
                        default => 'status-dalam_peninjauan',
                    },
                    'status_text' => match ($claimKey) {
                        'ditolak' => 'Tidak Disetujui',
                        'selesai' => 'Selesai',
                        'disetujui' => 'Sedang Diproses',
                        default => 'Menunggu Tinjauan',
                    },
                ];

                $itemName = (string) ($claim->barang?->nama_barang ?? $claim->laporanHilang?->nama_barang ?? 'Klaim Barang');
                $location = (string) ($claim->barang?->lokasi_ditemukan ?? $claim->laporanHilang?->lokasi_hilang ?? 'Lokasi tidak tersedia');
                $activityAt = strtotime((string) ($claim->updated_at ?? $claim->created_at));

                return (object) [
                    'type' => 'claim',
                    'report_id' => null,
                    'item_name' => $itemName,
                    'item_detail' => 'Klaim Barang - ' . $location,
                    'incident_date' => (string) optional($claim->created_at)->toDateString(),
                    'created_at' => $claim->created_at,
                    'activity_at' => $activityAt,
                    'status' => $statusPayload['status'],
                    'status_class' => $statusPayload['status_class'],
                    'status_text' => $statusPayload['status_text'],
                    'avatar' => 'K',
                    'avatar_class' => 'avatar-claim',
                    'image_url' => $this->resolveItemImageUrl(
                        (string) ($claim->barang?->foto_barang ?? $claim->laporanHilang?->foto_barang ?? ''),
                        $claim->barang ? 'barang-temuan' : 'barang-hilang'
                    ),
                    'detail_url' => $this->resolveClaimActionUrl($claim),
                    'action_label' => $this->resolveActionLabel($statusPayload['status']),
                    'can_delete' => false,
                    'delete_url' => null,
                ];
            });
    }

    private function resolveActionLabel(string $status): string
    {
        return match ($status) {
            'tidak_disetujui' => 'Lihat Detail',
            'selesai' => 'Lihat Hasil',
            'menunggu_tinjauan' => 'Lihat Status',
            default => 'Lihat Detail',
        };
    }

    private function resolveClaimActionUrl(Klaim $claim): string
    {
        if (!is_null($claim->barang_id)) {
            return route('home.found-detail', $claim->barang_id);
        }

        if (!is_null($claim->laporan_hilang_id)) {
            return route('home.lost-detail', $claim->laporan_hilang_id);
        }

        return route('user.claim-history');
    }

    private function resolveItemImageUrl(string $fotoPath, string $defaultFolder): string
    {
        $cleanPath = str_replace('\\', '/', trim($fotoPath, '/'));
        if ($cleanPath === '') {
            return '';
        }

        if (Str::startsWith($cleanPath, ['http://', 'https://', 'data:'])) {
            return $cleanPath;
        }

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (Str::startsWith($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');
        if (in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            $relative = $folder . '/' . $subPath;

            return Storage::disk('public')->exists($relative)
                ? asset('storage/' . $relative)
                : route('media.image', ['folder' => $folder, 'path' => $subPath]);
        }

        $relative = $defaultFolder . '/' . ltrim($cleanPath, '/');
        if (Storage::disk('public')->exists($relative)) {
            return asset('storage/' . $relative);
        }

        return '';
    }
}
