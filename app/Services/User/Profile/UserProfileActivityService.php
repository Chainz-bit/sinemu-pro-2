<?php

namespace App\Services\User\Profile;

use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\ClaimStatusPresenter;
use App\Support\ReportStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class UserProfileActivityService
{
    public function buildRecentActivities(int $userId): Collection
    {
        $lostReportActivities = LaporanBarangHilang::query()
            ->where('user_id', $userId)
            ->when(Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan'), function ($query) {
                $query->where('sumber_laporan', 'lapor_hilang');
            })
            ->select(array_values(array_filter([
                'id',
                'nama_barang',
                Schema::hasColumn('laporan_barang_hilangs', 'status_laporan') ? 'status_laporan' : null,
                'created_at',
                'updated_at',
            ])))
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (LaporanBarangHilang $report) {
                $reportStatus = ReportStatusPresenter::key($report->status_laporan ?? null);
                [$statusKey, $statusClass, $statusLabel] = match ($reportStatus) {
                    WorkflowStatus::REPORT_APPROVED => ['terverifikasi', 'dalam_peninjauan', 'Terverifikasi'],
                    WorkflowStatus::REPORT_REJECTED => ['tidak_disetujui', 'ditolak', 'Tidak Disetujui'],
                    WorkflowStatus::REPORT_MATCHED, WorkflowStatus::REPORT_CLAIMED => ['sedang_diproses', 'diproses', 'Sedang Diproses'],
                    WorkflowStatus::REPORT_COMPLETED => ['selesai', 'selesai', 'Selesai'],
                    default => ['menunggu_tinjauan', 'dalam_peninjauan', 'Menunggu Tinjauan'],
                };

                return (object) [
                    'activity_at' => strtotime((string) ($report->updated_at ?? $report->created_at)),
                    'title' => 'Anda mengirim laporan barang hilang ' . $report->nama_barang,
                    'timestamp' => $report->updated_at ?? $report->created_at,
                    'status_class' => $statusClass,
                    'status_label' => $statusLabel,
                    'detail_url' => $this->resolveLostReportDetailUrl((int) $report->id, $statusKey),
                ];
            });

        $claimActivities = Klaim::query()
            ->where('user_id', $userId)
            ->with(['barang:id,nama_barang', 'laporanHilang:id,nama_barang'])
            ->latest('updated_at')
            ->limit(8)
            ->get(array_values(array_filter([
                'id',
                'barang_id',
                'laporan_hilang_id',
                'status_klaim',
                Schema::hasColumn('klaims', 'status_verifikasi') ? 'status_verifikasi' : null,
                'created_at',
                'updated_at',
            ])))
            ->map(function (Klaim $claim) {
                $namaBarang = $claim->barang?->nama_barang
                    ?? $claim->laporanHilang?->nama_barang
                    ?? 'barang';

                $claimKey = ClaimStatusPresenter::key(
                    statusKlaim: (string) $claim->status_klaim,
                    statusVerifikasi: (string) ($claim->status_verifikasi ?? ''),
                    statusBarang: null
                );
                [$statusClass, $statusLabel] = match ($claimKey) {
                    'ditolak' => ['ditolak', 'Tidak Disetujui'],
                    'disetujui' => ['diproses', 'Sedang Diproses'],
                    'selesai' => ['selesai', 'Selesai'],
                    default => ['dalam_peninjauan', 'Menunggu Tinjauan'],
                };
                $kataKerja = match ($claimKey) {
                    'disetujui' => 'sedang diproses',
                    'ditolak' => 'tidak disetujui',
                    'selesai' => 'selesai',
                    default => 'menunggu tinjauan',
                };

                return (object) [
                    'activity_at' => strtotime((string) ($claim->updated_at ?? $claim->created_at)),
                    'title' => 'Klaim barang ' . $namaBarang . ' ' . $kataKerja,
                    'timestamp' => $claim->updated_at ?? $claim->created_at,
                    'status_class' => $statusClass,
                    'status_label' => $statusLabel,
                    'detail_url' => $this->resolveClaimDetailUrl($claim),
                ];
            });

        return $lostReportActivities
            ->merge($claimActivities)
            ->sortByDesc('activity_at')
            ->take(8)
            ->values();
    }

    private function resolveLostReportDetailUrl(int $reportId, string $status): string
    {
        if (in_array($status, ['menunggu_tinjauan', 'tidak_disetujui'], true)) {
            return route('user.lost-reports.create', ['edit' => $reportId]);
        }

        return route('home.lost-detail', $reportId);
    }

    private function resolveClaimDetailUrl(Klaim $claim): string
    {
        if (!empty($claim->barang_id)) {
            return route('home.found-detail', $claim->barang_id);
        }

        if (!empty($claim->laporan_hilang_id)) {
            return route('home.lost-detail', $claim->laporan_hilang_id);
        }

        return route('home');
    }
}
