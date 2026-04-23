<?php

namespace App\Services\User;

use App\Models\LaporanBarangHilang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDashboardLostFeedService
{
    public function build(int $userId, bool $hasSourceColumn, bool $hasReportStatusColumn): Collection
    {
        $lostReportsQuery = LaporanBarangHilang::query()
            ->where('user_id', $userId)
            ->when($hasSourceColumn, function ($query) {
                $query->where('sumber_laporan', 'lapor_hilang');
            });

        $lostSelectColumns = ['id', 'nama_barang', 'lokasi_hilang', 'tanggal_hilang', 'foto_barang', 'created_at', 'updated_at'];
        if ($hasReportStatusColumn) {
            $lostSelectColumns[] = 'status_laporan';
        }

        return $lostReportsQuery
            ->select($lostSelectColumns)
            ->latest('updated_at')
            ->limit(16)
            ->get()
            ->map(function (LaporanBarangHilang $report) {
                $statusPayload = match ((string) ($report->status_laporan ?? WorkflowStatus::REPORT_SUBMITTED)) {
                    WorkflowStatus::REPORT_APPROVED => ['status' => 'terverifikasi', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'Terverifikasi'],
                    WorkflowStatus::REPORT_REJECTED => ['status' => 'tidak_disetujui', 'status_class' => 'status-ditolak', 'status_text' => 'Tidak Disetujui'],
                    WorkflowStatus::REPORT_MATCHED, WorkflowStatus::REPORT_CLAIMED => ['status' => 'sedang_diproses', 'status_class' => 'status-diproses', 'status_text' => 'Sedang Diproses'],
                    WorkflowStatus::REPORT_COMPLETED => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'Selesai'],
                    default => ['status' => 'menunggu_tinjauan', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'Menunggu Tinjauan'],
                };

                $activityAt = strtotime((string) ($report->updated_at ?? $report->created_at));

                return (object) [
                    'type' => 'lost_report',
                    'report_id' => (int) $report->id,
                    'item_name' => (string) $report->nama_barang,
                    'item_detail' => 'Laporan Hilang - ' . (string) $report->lokasi_hilang,
                    'incident_date' => (string) $report->tanggal_hilang,
                    'created_at' => $report->created_at,
                    'activity_at' => $activityAt,
                    'status' => $statusPayload['status'],
                    'status_class' => $statusPayload['status_class'],
                    'status_text' => $statusPayload['status_text'],
                    'avatar' => 'H',
                    'avatar_class' => 'avatar-sand',
                    'image_url' => $this->resolveItemImageUrl((string) ($report->foto_barang ?? ''), 'barang-hilang'),
                    'detail_url' => $this->resolveLostReportActionUrl((int) $report->id, $statusPayload['status']),
                    'action_label' => $this->resolveActionLabel($statusPayload['status']),
                    'can_delete' => $this->canDelete($statusPayload['status']),
                    'delete_url' => route('user.lost-reports.destroy', $report->id),
                ];
            });
    }

    private function resolveActionLabel(string $status): string
    {
        return match ($status) {
            'menunggu_tinjauan' => 'Edit Laporan',
            'terverifikasi' => 'Lihat Laporan',
            'sedang_diproses' => 'Lihat Status',
            'tidak_disetujui' => 'Perbaiki Data',
            'selesai' => 'Lihat Hasil',
            default => 'Lihat Detail',
        };
    }

    private function resolveLostReportActionUrl(int $reportId, string $status): string
    {
        return match ($status) {
            'menunggu_tinjauan', 'tidak_disetujui' => route('user.lost-reports.create', ['edit' => $reportId]),
            default => route('home.lost-detail', $reportId),
        };
    }

    private function canDelete(string $status): bool
    {
        return $status === 'menunggu_tinjauan';
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
