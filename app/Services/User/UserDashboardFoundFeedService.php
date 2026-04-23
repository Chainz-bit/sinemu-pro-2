<?php

namespace App\Services\User;

use App\Models\Barang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDashboardFoundFeedService
{
    public function build(int $userId, bool $hasFoundUserColumn, bool $hasFoundReportStatusColumn): Collection
    {
        if (!$hasFoundUserColumn) {
            return collect();
        }

        $foundSelectColumns = ['id', 'nama_barang', 'lokasi_ditemukan', 'tanggal_ditemukan', 'status_barang', 'foto_barang', 'created_at', 'updated_at'];
        if ($hasFoundReportStatusColumn) {
            $foundSelectColumns[] = 'status_laporan';
        }

        return Barang::query()
            ->where('user_id', $userId)
            ->select($foundSelectColumns)
            ->latest('updated_at')
            ->limit(16)
            ->get()
            ->map(function (Barang $item) {
                $reportStatus = (string) ($item->status_laporan ?? WorkflowStatus::REPORT_SUBMITTED);
                $statusPayload = match (true) {
                    $reportStatus === WorkflowStatus::REPORT_REJECTED => ['status' => 'tidak_disetujui', 'status_class' => 'status-ditolak', 'status_text' => 'Tidak Disetujui'],
                    $reportStatus === WorkflowStatus::REPORT_SUBMITTED => ['status' => 'menunggu_tinjauan', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'Menunggu Tinjauan'],
                    (string) $item->status_barang === 'sudah_dikembalikan' => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'Selesai'],
                    in_array((string) $item->status_barang, ['dalam_proses_klaim', 'sudah_diklaim'], true) => ['status' => 'sedang_diproses', 'status_class' => 'status-diproses', 'status_text' => 'Sedang Diproses'],
                    $reportStatus === WorkflowStatus::REPORT_APPROVED => ['status' => 'terverifikasi', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'Terverifikasi'],
                    default => ['status' => 'sedang_diproses', 'status_class' => 'status-diproses', 'status_text' => 'Sedang Diproses'],
                };

                $activityAt = strtotime((string) ($item->updated_at ?? $item->created_at));

                return (object) [
                    'type' => 'found_report',
                    'report_id' => (int) $item->id,
                    'item_name' => (string) $item->nama_barang,
                    'item_detail' => 'Laporan Temuan - ' . (string) $item->lokasi_ditemukan,
                    'incident_date' => (string) $item->tanggal_ditemukan,
                    'created_at' => $item->created_at,
                    'activity_at' => $activityAt,
                    'status' => $statusPayload['status'],
                    'status_class' => $statusPayload['status_class'],
                    'status_text' => $statusPayload['status_text'],
                    'avatar' => 'T',
                    'avatar_class' => 'avatar-mint',
                    'image_url' => $this->resolveItemImageUrl((string) ($item->foto_barang ?? ''), 'barang-temuan'),
                    'detail_url' => route('home.found-detail', $item->id),
                    'action_label' => 'Lihat Laporan',
                    'can_delete' => false,
                    'delete_url' => null,
                ];
            });
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
