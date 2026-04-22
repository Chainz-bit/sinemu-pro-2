<?php

namespace App\Services\User;

use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Support\ClaimStatusPresenter;
use App\Support\WorkflowStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDashboardService
{
    private const DASHBOARD_CACHE_TTL_SECONDS = 30;

    public function __construct(
        private readonly UserDashboardStatsService $statsService
    ) {
    }

    /**
     * @return array{totalLaporHilang:int,totalPengajuanKlaim:int,menungguVerifikasi:int,latestActivities:LengthAwarePaginator}
     */
    public function buildDashboardData(int $userId, string $search, string $statusFilter, int $page): array
    {
        $hasSourceColumn = Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan');
        $hasFoundUserColumn = Schema::hasColumn('barangs', 'user_id');
        $hasReportStatusColumn = Schema::hasColumn('laporan_barang_hilangs', 'status_laporan');
        $hasFoundReportStatusColumn = Schema::hasColumn('barangs', 'status_laporan');
        $hasClaimVerificationColumn = Schema::hasColumn('klaims', 'status_verifikasi');

        $stats = Cache::remember(
            $this->statsCacheKey($userId),
            now()->addSeconds(self::DASHBOARD_CACHE_TTL_SECONDS),
            fn () => $this->statsService->build($userId, $hasSourceColumn, $hasClaimVerificationColumn)
        );

        $columnFlags = (object) [
            'hasSourceColumn' => $hasSourceColumn,
            'hasFoundUserColumn' => $hasFoundUserColumn,
            'hasReportStatusColumn' => $hasReportStatusColumn,
            'hasFoundReportStatusColumn' => $hasFoundReportStatusColumn,
            'hasClaimVerificationColumn' => $hasClaimVerificationColumn,
        ];
        $latestActivities = Cache::remember(
            $this->activitiesCacheKey($userId),
            now()->addSeconds(self::DASHBOARD_CACHE_TTL_SECONDS),
            fn () => $this->buildLatestActivities($userId, $columnFlags)
        );
        $latestActivities = $this->filterLatestActivities($latestActivities, $search, $statusFilter);
        $latestActivities = $this->paginateItems(
            $latestActivities,
            max($page, 1),
            8
        );

        return [
            'totalLaporHilang' => $stats['totalLaporHilang'],
            'totalPengajuanKlaim' => $stats['totalPengajuanKlaim'],
            'menungguVerifikasi' => $stats['menungguVerifikasi'],
            'latestActivities' => $latestActivities,
        ];
    }

    private function buildLatestActivities(int $userId, object $columnFlags): Collection
    {
        $lostReports = $this->buildLostReports($userId, $columnFlags);
        $foundReports = $this->buildFoundReports($userId, $columnFlags);
        $claims = $this->buildClaims($userId, $columnFlags);

        return $lostReports
            ->concat($foundReports)
            ->concat($claims)
            ->sortByDesc('activity_at')
            ->values();
    }

    private function buildLostReports(int $userId, object $columnFlags): Collection
    {
        $lostReportsQuery = LaporanBarangHilang::query()
            ->where('user_id', $userId)
            ->when($columnFlags->hasSourceColumn, function ($query) {
                $query->where('sumber_laporan', 'lapor_hilang');
            });

        $lostSelectColumns = ['id', 'nama_barang', 'lokasi_hilang', 'tanggal_hilang', 'foto_barang', 'created_at', 'updated_at'];
        if ($columnFlags->hasReportStatusColumn) {
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
                    'action_label' => $this->resolveActionLabel('lost_report', $statusPayload['status']),
                    'can_delete' => $this->canDeleteLostReport($statusPayload['status']),
                    'delete_url' => route('user.lost-reports.destroy', $report->id),
                ];
            });
    }

    private function buildFoundReports(int $userId, object $columnFlags): Collection
    {
        if (!$columnFlags->hasFoundUserColumn) {
            return collect();
        }

        $foundSelectColumns = ['id', 'nama_barang', 'lokasi_ditemukan', 'tanggal_ditemukan', 'status_barang', 'foto_barang', 'created_at', 'updated_at'];
        if ($columnFlags->hasFoundReportStatusColumn) {
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

    private function buildClaims(int $userId, object $columnFlags): Collection
    {
        return Klaim::query()
            ->where('user_id', $userId)
            ->with([
                'barang:id,nama_barang,lokasi_ditemukan,foto_barang',
                'laporanHilang:id,nama_barang,lokasi_hilang,foto_barang',
            ])
            ->select(array_values(array_filter([
                'id',
                'status_klaim',
                $columnFlags->hasClaimVerificationColumn ? 'status_verifikasi' : null,
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
                    'action_label' => $this->resolveActionLabel('claim', $statusPayload['status']),
                    'can_delete' => false,
                    'delete_url' => null,
                ];
            });
    }

    private function filterLatestActivities(Collection $items, string $search, string $statusFilter): Collection
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
                            (string) ($item->status_text ?? ''),
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

    private function paginateItems(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        $currentPageItems = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function resolveActionLabel(string $type, string $status): string
    {
        if ($type === 'claim') {
            return match ($status) {
                'tidak_disetujui' => 'Lihat Detail',
                'selesai' => 'Lihat Hasil',
                'menunggu_tinjauan' => 'Lihat Status',
                default => 'Lihat Detail',
            };
        }

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

    private function canDeleteLostReport(string $status): bool
    {
        return $status === 'menunggu_tinjauan';
    }

    private function statsCacheKey(int $userId): string
    {
        return 'user_dashboard:stats:' . $userId;
    }

    private function activitiesCacheKey(int $userId): string
    {
        return 'user_dashboard:activities:' . $userId;
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
