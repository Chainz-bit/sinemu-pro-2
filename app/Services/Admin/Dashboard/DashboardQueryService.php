<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\ClaimStatusPresenter;
use App\Support\ReportStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardQueryService
{
    /**
     * @return array{totalHilang:int,totalTemuan:int,menungguVerifikasi:int,latestReports:LengthAwarePaginator}
     */
    public function buildDashboardData(string $search, string $statusFilter, int $page): array
    {
        $totalHilangQuery = LaporanBarangHilang::query();
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $totalHilangQuery->where('sumber_laporan', 'lapor_hilang');
        }
        $totalHilang = $totalHilangQuery->count();

        $totalTemuan = Barang::count();

        if (Schema::hasColumn('klaims', 'status_verifikasi')) {
            $menungguVerifikasi = Klaim::query()
                ->whereIn('status_verifikasi', [WorkflowStatus::CLAIM_SUBMITTED, WorkflowStatus::CLAIM_UNDER_REVIEW])
                ->count();
        } else {
            $menungguVerifikasi = Klaim::where('status_klaim', 'pending')->count();
        }

        $latestReportsCollection = $this->filterLatestReports(
            $this->buildLatestReports(),
            $search,
            $statusFilter
        );

        return [
            'totalHilang' => $totalHilang,
            'totalTemuan' => $totalTemuan,
            'menungguVerifikasi' => $menungguVerifikasi,
            'latestReports' => $this->paginateReports(
                $latestReportsCollection,
                $page,
                8
            ),
        ];
    }

    private function buildLatestReports(): Collection
    {
        $lostHasHomeFlag = Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home');
        $foundHasHomeFlag = Schema::hasColumn('barangs', 'tampil_di_home');

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

        $lostReports = $lostReportsQuery
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
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

        $foundReports = Barang::query()
            ->with('admin:id,nama')
            ->select($foundSelectColumns)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $statusPayload = match ($report->status_barang) {
                    'tersedia' => ['status' => 'dalam_peninjauan', 'status_class' => 'status-dalam_peninjauan', 'status_text' => 'TERSEDIA'],
                    'dalam_proses_klaim' => ['status' => 'diproses', 'status_class' => 'status-diproses', 'status_text' => 'DALAM PROSES KLAIM'],
                    'sudah_diklaim' => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'SUDAH DIKLAIM'],
                    'sudah_dikembalikan' => ['status' => 'selesai', 'status_class' => 'status-selesai', 'status_text' => 'SELESAI'],
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

        $claimReports = Klaim::query()
            ->with([
                'barang' => function ($query) use ($foundHasHomeFlag) {
                    $columns = ['id', 'nama_barang', 'lokasi_ditemukan', 'foto_barang'];
                    if ($foundHasHomeFlag) {
                        $columns[] = 'tampil_di_home';
                    }
                    $query->select($columns);
                },
                'laporanHilang' => function ($query) use ($lostHasHomeFlag) {
                    $columns = ['id', 'nama_barang', 'lokasi_hilang', 'foto_barang'];
                    if ($lostHasHomeFlag) {
                        $columns[] = 'tampil_di_home';
                    }
                    $query->select($columns);
                },
                'user:id,nama,name',
            ])
            ->select(array_values(array_filter([
                'id',
                'status_klaim',
                Schema::hasColumn('klaims', 'status_verifikasi') ? 'status_verifikasi' : null,
                'catatan',
                'created_at',
                'updated_at',
                'barang_id',
                'laporan_hilang_id',
            ])))
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($claim) {
                $claimStatusKey = ClaimStatusPresenter::key(
                    statusKlaim: (string) $claim->status_klaim,
                    statusVerifikasi: (string) ($claim->status_verifikasi ?? ''),
                    statusBarang: (string) ($claim->barang?->status_barang ?? '')
                );
                $statusPayload = [
                    'status' => match ($claimStatusKey) {
                        'disetujui' => 'diproses',
                        'selesai' => 'selesai',
                        'ditolak' => 'ditolak',
                        default => 'dalam_peninjauan',
                    },
                    'status_class' => ClaimStatusPresenter::cssClass($claimStatusKey),
                    'status_text' => ClaimStatusPresenter::label($claimStatusKey),
                ];

                $namaBarang = $claim->barang?->nama_barang
                    ?? $claim->laporanHilang?->nama_barang
                    ?? 'Klaim Barang';

                $detailUrl = match (true) {
                    !is_null($claim->barang_id) => route('admin.found-items.show', $claim->barang_id),
                    !is_null($claim->laporan_hilang_id) => route('admin.lost-items.show', $claim->laporan_hilang_id),
                    default => route('admin.claim-verifications.show', $claim->id),
                };
                $uploadHomeUrl = match (true) {
                    !is_null($claim->barang_id) => route('admin.dashboard.reports.publish-home', ['type' => 'temuan', 'id' => $claim->barang_id]),
                    !is_null($claim->laporan_hilang_id) => route('admin.dashboard.reports.publish-home', ['type' => 'hilang', 'id' => $claim->laporan_hilang_id]),
                    default => null,
                };
                $homePublished = match (true) {
                    !is_null($claim->barang?->tampil_di_home ?? null) => (bool) $claim->barang?->tampil_di_home,
                    !is_null($claim->laporanHilang?->tampil_di_home ?? null) => (bool) $claim->laporanHilang?->tampil_di_home,
                    default => false,
                };

                $lokasi = $claim->barang?->lokasi_ditemukan
                    ?? $claim->laporanHilang?->lokasi_hilang
                    ?? 'Lokasi tidak tersedia';
                $pelapor = $claim->user?->nama ?? $claim->user?->name ?? 'Pengguna';

                $activityAt = strtotime((string) ($claim->updated_at ?? $claim->created_at));

                return (object) [
                    'id' => (int) $claim->id,
                    'type' => 'klaim',
                    'item_name' => $namaBarang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Verifikasi Klaim - ' . $lokasi,
                    'incident_date' => $claim->created_at,
                    'created_at' => $claim->created_at,
                    'activity_at' => $activityAt,
                    'status' => $statusPayload['status'],
                    'status_class' => $statusPayload['status_class'],
                    'status_text' => $statusPayload['status_text'],
                    'status_label' => 'Verifikasi Klaim',
                    'avatar' => 'K',
                    'avatar_class' => 'avatar-claim',
                    'foto_barang' => $claim->barang?->foto_barang ?? $claim->laporanHilang?->foto_barang,
                    'detail_url' => $detailUrl,
                    'edit_url' => route('admin.claim-verifications.show', $claim->id),
                    'edit_status_klaim' => $claim->status_klaim,
                    'edit_catatan' => $claim->catatan,
                    'update_url' => route('admin.dashboard.reports.update', ['type' => 'klaim', 'id' => $claim->id]),
                    'upload_home_url' => $uploadHomeUrl,
                    'home_published' => $homePublished,
                    'target_url' => route('admin.claim-verifications', [
                        'search' => $namaBarang,
                        'status' => match ($claimStatusKey) {
                            'menunggu' => 'menunggu',
                            'selesai' => 'selesai',
                            default => $claimStatusKey,
                        },
                    ]),
                    'target_label' => 'Buka Verifikasi Klaim',
                    'delete_url' => route('admin.claim-verifications.destroy', $claim->id),
                ];
            });

        return $lostReports
            ->merge($foundReports)
            ->merge($claimReports)
            ->sortByDesc('activity_at')
            ->take(10)
            ->values();
    }

    private function paginateReports(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        $page = max($page, 1);
        $total = $items->count();
        $currentPageItems = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function filterLatestReports(Collection $items, string $search, string $statusFilter): Collection
    {
        if ($search !== '') {
            $keyword = mb_strtolower($search);
            $items = $items->filter(function ($item) use ($keyword) {
                $haystack = mb_strtolower(
                    trim(
                        implode(' ', [
                            (string) ($item->item_name ?? ''),
                            (string) ($item->item_detail ?? ''),
                            (string) ($item->status ?? ''),
                            (string) ($item->status_label ?? ''),
                        ])
                    )
                );

                return str_contains($haystack, $keyword);
            });
        }

        if ($statusFilter !== '' && $statusFilter !== 'semua') {
            $items = $items->filter(function ($item) use ($statusFilter) {
                return (string) ($item->status ?? '') === $statusFilter;
            });
        }

        return $items->values();
    }
}
