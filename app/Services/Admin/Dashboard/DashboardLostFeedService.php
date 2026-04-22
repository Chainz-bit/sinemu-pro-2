<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\ReportStatusPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardLostFeedService
{
    public function build(bool $lostHasHomeFlag): Collection
    {
        $lostSelectColumns = ['id', 'nama_barang', 'lokasi_hilang', 'tanggal_hilang', 'keterangan', 'foto_barang', 'created_at', 'updated_at'];
        if ($lostHasHomeFlag) {
            $lostSelectColumns[] = 'tampil_di_home';
        }
        $hasLostStatusColumn = Schema::hasColumn('laporan_barang_hilangs', 'status_laporan');
        if ($hasLostStatusColumn) {
            $lostSelectColumns[] = 'status_laporan';
        }

        $lostReportsQuery = LaporanBarangHilang::query()
            ->select($lostSelectColumns)
            ->with('user:id,name,nama');

        if (!$hasLostStatusColumn) {
            $lostReportsQuery
                ->selectSub(
                    Klaim::query()
                        ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                        ->orderByDesc('updated_at')
                        ->limit(1)
                        ->select('status_klaim'),
                    'latest_claim_status'
                )
                ->selectSub(
                    Klaim::query()
                        ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                        ->orderByDesc('updated_at')
                        ->limit(1)
                        ->select('updated_at'),
                    'latest_claim_activity_at'
                );
        }

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $lostReportsQuery->where('sumber_laporan', 'lapor_hilang');
        }

        return $lostReportsQuery
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($report) use ($hasLostStatusColumn) {
                if ($hasLostStatusColumn) {
                    $reportStatus = ReportStatusPresenter::key($report->status_laporan ?? null);
                    $statusPayload = [
                        'status' => ReportStatusPresenter::dashboardStatus($reportStatus),
                        'status_class' => ReportStatusPresenter::cssClass($reportStatus),
                        'status_text' => ReportStatusPresenter::label($reportStatus),
                    ];
                } else {
                    $statusPayload = match ($report->latest_claim_status) {
                        'disetujui' => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'DITEMUKAN'],
                        'ditolak' => ['status' => 'ditolak', 'status_class' => 'status-ditolak', 'status_text' => 'DITOLAK'],
                        'pending' => ['status' => 'diproses', 'status_class' => 'status-diproses', 'status_text' => 'DALAM PENINJAUAN'],
                        default => ['status' => 'dalam_peninjauan', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'BELUM DITEMUKAN'],
                    };
                }

                $pelapor = $report->user?->nama ?? $report->user?->name ?? 'Pengguna';

                $activityAt = max(
                    strtotime((string) $report->updated_at),
                    strtotime((string) ($report->latest_claim_activity_at ?? $report->created_at))
                );

                return (object) [
                    'id' => (int) $report->id,
                    'type' => 'hilang',
                    'item_name' => $report->nama_barang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Barang Hilang - ' . $report->lokasi_hilang,
                    'incident_date' => $report->tanggal_hilang,
                    'created_at' => $report->created_at,
                    'activity_at' => $activityAt,
                    'status' => $statusPayload['status'],
                    'status_class' => $statusPayload['status_class'],
                    'status_text' => $statusPayload['status_text'],
                    'status_label' => 'Laporan Hilang',
                    'avatar' => 'H',
                    'avatar_class' => 'avatar-sand',
                    'foto_barang' => $report->foto_barang,
                    'detail_url' => route('admin.lost-items.show', $report->id),
                    'edit_url' => route('admin.lost-items.edit', $report->id),
                    'edit_nama_barang' => $report->nama_barang,
                    'edit_lokasi_hilang' => $report->lokasi_hilang,
                    'edit_tanggal_hilang' => $report->tanggal_hilang,
                    'edit_keterangan' => $report->keterangan,
                    'update_url' => route('admin.dashboard.reports.update', ['type' => 'hilang', 'id' => $report->id]),
                    'upload_home_url' => route('admin.dashboard.reports.publish-home', ['type' => 'hilang', 'id' => $report->id]),
                    'home_published' => (bool) ($report->tampil_di_home ?? false),
                    'target_url' => route('admin.lost-items', ['search' => $report->nama_barang]),
                    'target_label' => 'Buka Barang Hilang',
                    'delete_url' => route('admin.lost-items.destroy', $report->id),
                ];
            });
    }
}
