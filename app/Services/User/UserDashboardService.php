<?php

namespace App\Services\User;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class UserDashboardService
{
    private const DASHBOARD_CACHE_TTL_SECONDS = 30;

    public function __construct(
        private readonly UserDashboardStatsService $statsService,
        private readonly UserDashboardClaimFeedService $claimFeedService,
        private readonly UserDashboardFoundFeedService $foundFeedService,
        private readonly UserDashboardLostFeedService $lostFeedService
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
        $lostReports = $this->lostFeedService->build(
            $userId,
            $columnFlags->hasSourceColumn,
            $columnFlags->hasReportStatusColumn
        );
        $foundReports = $this->foundFeedService->build(
            $userId,
            $columnFlags->hasFoundUserColumn,
            $columnFlags->hasFoundReportStatusColumn
        );
        $claims = $this->claimFeedService->build($userId, $columnFlags->hasClaimVerificationColumn);

        return $lostReports
            ->concat($foundReports)
            ->concat($claims)
            ->sortByDesc('activity_at')
            ->values();
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

    private function statsCacheKey(int $userId): string
    {
        return 'user_dashboard:stats:' . $userId;
    }

    private function activitiesCacheKey(int $userId): string
    {
        return 'user_dashboard:activities:' . $userId;
    }
}
