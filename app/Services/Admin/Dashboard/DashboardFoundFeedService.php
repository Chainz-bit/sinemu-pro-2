<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
            'status_laporan',
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
        if (Schema::hasColumn('barangs', 'region_id')) {
            $foundSelectColumns[] = 'region_id';
        }

        $query = Barang::query()
            ->with('admin:id,nama')
            ->select($foundSelectColumns)
            ->withCount(['klaims', 'pencocokans'])
            ->orderByDesc('updated_at');

        $admin = \App\Support\ManagerPortal::user();
        if ($admin && $admin->region_id && Schema::hasColumn('barangs', 'region_id')) {
            $query->where('region_id', $admin->region_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->limit(10)
            ->get()
            ->map(fn ($report) => $this->presentReport($report));
    }

    private function presentReport(Barang $report): object
    {
        $statusPayload = $this->buildStatusPayload((string) $report->status_barang);
        $pelapor = $report->admin?->nama ?? \App\Support\RoleLabels::manager();
        $activityAt = strtotime((string) ($report->updated_at ?? $report->created_at));
        $canPublishHome = $report->canBePublishedToHomeByAdmin();

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
            'detail_url' => route(\App\Support\ManagerPortal::routeName('found-items.show'), $report->id),
            'edit_url' => route(\App\Support\ManagerPortal::routeName('found-items.edit'), $report->id),
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
            'update_url' => route(\App\Support\ManagerPortal::routeName('dashboard.reports.update'), ['type' => 'temuan', 'id' => $report->id]),
            'upload_home_url' => $canPublishHome
                ? route(\App\Support\ManagerPortal::routeName('dashboard.reports.publish-home'), ['type' => 'temuan', 'id' => $report->id])
                : null,
            'home_published' => (bool) ($report->tampil_di_home ?? false),
            'target_url' => route(\App\Support\ManagerPortal::routeName('found-items.show'), $report->id),
            'target_label' => 'Buka Barang Temuan',
            'delete_url' => route(\App\Support\ManagerPortal::routeName('found-items.destroy'), $report->id),
        ];
    }

    /**
     * @return array{status:string,status_class:string,status_text:string}
     */
    private function buildStatusPayload(string $statusBarang): array
    {
        return match ($statusBarang) {
            WorkflowStatus::FOUND_AVAILABLE => ['status' => 'dalam_peninjauan', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'TERSEDIA'],
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => ['status' => 'diproses', 'status_class' => 'status-diproses', 'status_text' => 'DALAM PROSES KLAIM'],
            WorkflowStatus::FOUND_CLAIMED => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'SUDAH DIKLAIM'],
            WorkflowStatus::FOUND_RETURNED => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'SELESAI'],
            default => ['status' => 'diproses', 'status_class' => 'status-diproses', 'status_text' => 'UNKNOWN'],
        };
    }
}
