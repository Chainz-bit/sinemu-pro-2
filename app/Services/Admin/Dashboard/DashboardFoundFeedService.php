<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;

class DashboardFoundFeedService
{
    public function build(bool $foundHasHomeFlag): Collection
    {
        $foundSelectColumns = [
            'id',
            'nama_barang',
            'kategori_id',
            'deskripsi',
            'lokasi_ditemukan',
            'tanggal_ditemukan',
            'status_barang',
            'lokasi_pengambilan',
            'alamat_pengambilan',
            'penanggung_jawab_pengambilan',
            'kontak_pengambilan',
            'jam_layanan_pengambilan',
            'catatan_pengambilan',
            'foto_barang',
            'created_at',
            'updated_at',
        ];
        if ($foundHasHomeFlag) {
            $foundSelectColumns[] = 'tampil_di_home';
        }

        return Barang::query()
            ->with('admin:id,nama')
            ->select($foundSelectColumns)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $statusPayload = match ($report->status_barang) {
                    WorkflowStatus::FOUND_AVAILABLE => ['status' => 'dalam_peninjauan', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'TERSEDIA'],
                    WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => ['status' => 'diproses', 'status_class' => 'status-diproses', 'status_text' => 'DALAM PROSES KLAIM'],
                    WorkflowStatus::FOUND_CLAIMED => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'SUDAH DIKLAIM'],
                    WorkflowStatus::FOUND_RETURNED => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'SELESAI'],
                    default => ['status' => 'diproses', 'status_class' => 'status-diproses', 'status_text' => 'UNKNOWN'],
                };

                $pelapor = $report->admin?->nama ?? 'Admin';
                $activityAt = strtotime((string) ($report->updated_at ?? $report->created_at));

                return (object) [
                    'id' => (int) $report->id,
                    'type' => 'temuan',
                    'item_name' => $report->nama_barang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Barang Temuan - ' . $report->lokasi_ditemukan,
                    'incident_date' => $report->tanggal_ditemukan,
                    'created_at' => $report->created_at,
                    'activity_at' => $activityAt,
                    'status' => $statusPayload['status'],
                    'status_class' => $statusPayload['status_class'],
                    'status_text' => $statusPayload['status_text'],
                    'status_label' => 'Barang Temuan',
                    'avatar' => 'T',
                    'avatar_class' => 'avatar-mint',
                    'foto_barang' => $report->foto_barang,
                    'detail_url' => route('admin.found-items.show', $report->id),
                    'edit_url' => route('admin.found-items.edit', $report->id),
                    'edit_nama_barang' => $report->nama_barang,
                    'edit_kategori_id' => $report->kategori_id,
                    'edit_deskripsi' => $report->deskripsi,
                    'edit_lokasi_ditemukan' => $report->lokasi_ditemukan,
                    'edit_tanggal_ditemukan' => $report->tanggal_ditemukan,
                    'edit_status_barang' => $report->status_barang,
                    'edit_lokasi_pengambilan' => $report->lokasi_pengambilan,
                    'edit_alamat_pengambilan' => $report->alamat_pengambilan,
                    'edit_penanggung_jawab_pengambilan' => $report->penanggung_jawab_pengambilan,
                    'edit_kontak_pengambilan' => $report->kontak_pengambilan,
                    'edit_jam_layanan_pengambilan' => $report->jam_layanan_pengambilan,
                    'edit_catatan_pengambilan' => $report->catatan_pengambilan,
                    'update_url' => route('admin.dashboard.reports.update', ['type' => 'temuan', 'id' => $report->id]),
                    'upload_home_url' => route('admin.dashboard.reports.publish-home', ['type' => 'temuan', 'id' => $report->id]),
                    'home_published' => (bool) ($report->tampil_di_home ?? false),
                    'target_url' => route('admin.found-items.show', $report->id),
                    'target_label' => 'Buka Barang Temuan',
                    'delete_url' => route('admin.found-items.destroy', $report->id),
                ];
            });
    }
}
