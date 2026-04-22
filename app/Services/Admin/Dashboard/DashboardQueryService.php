<?php

namespace App\Services\Admin\Dashboard;

use App\Models\Barang;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardQueryService
{
    public function __construct(
        private readonly DashboardStatsService $statsService,
        private readonly DashboardClaimFeedService $claimFeedService,
        private readonly DashboardFoundFeedService $foundFeedService,
        private readonly DashboardLostFeedService $lostFeedService
    ) {
    }

    /**
     * @return array{totalHilang:int,totalTemuan:int,menungguVerifikasi:int,latestReports:LengthAwarePaginator}
     */
    public function buildDashboardData(string $search, string $statusFilter, int $page): array
    {
        $stats = $this->statsService->build();

        $latestReportsCollection = $this->filterLatestReports(
            $this->buildLatestReports(),
            $search,
            $statusFilter
        );

        return [
            'totalHilang' => $stats['totalHilang'],
            'totalTemuan' => $stats['totalTemuan'],
            'menungguVerifikasi' => $stats['menungguVerifikasi'],
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

        $lostReports = $this->lostFeedService->build($lostHasHomeFlag);

        $foundReports = $this->foundFeedService->build($foundHasHomeFlag);

        $claimReports = $this->claimFeedService->build($foundHasHomeFlag, $lostHasHomeFlag);

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
