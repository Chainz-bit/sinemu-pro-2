<?php

namespace App\Services\Admin\Matching;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Models\Pencocokan;
use App\Support\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MatchingCandidateQueryService
{
    /**
     * @var array<int,string>
     */
    private const REVIEWED_MATCH_STATUSES = [
        WorkflowStatus::MATCH_CONFIRMED,
        WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
        WorkflowStatus::MATCH_CLAIM_APPROVED,
        WorkflowStatus::MATCH_COMPLETED,
        WorkflowStatus::MATCH_CANCELLED,
    ];

    public function __construct(
        private readonly MatchingScoreService $scoreService
    ) {
    }

    public function findCandidatesForLostReport(LaporanBarangHilang $laporan, int $limit = 8): Collection
    {
        $isLostReportBlocked = Pencocokan::query()
            ->where('laporan_hilang_id', (int) $laporan->id)
            ->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses())
            ->exists();

        if ($isLostReportBlocked) {
            return collect();
        }

        $query = Barang::query()->with('kategori:id,nama_kategori');

        if (Schema::hasColumn('barangs', 'status_laporan')) {
            $query->where('status_laporan', WorkflowStatus::REPORT_APPROVED);
        }

        $query->whereIn('status_barang', [
            WorkflowStatus::FOUND_AVAILABLE,
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS,
        ]);

        $query->whereDoesntHave('pencocokans', function ($matchQuery) use ($laporan): void {
            $matchQuery
                ->where('laporan_hilang_id', (int) $laporan->id)
                ->whereIn('status_pencocokan', self::REVIEWED_MATCH_STATUSES);
        });

        $query->whereDoesntHave('pencocokans', function ($matchQuery): void {
            $matchQuery->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses());
        });

        return $query
            ->latest('updated_at')
            ->get()
            ->map(function (Barang $barang) use ($laporan) {
                $score = $this->scoreService->scoreForPair($laporan, $barang);

                return (object) [
                    'barang' => $barang,
                    'score' => $score['score'],
                    'reasons' => $score['reasons'],
                    'meta' => $score['meta'],
                ];
            })
            ->filter(fn ($row) => $row->score >= 35)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    public function findCandidatesForFoundItem(Barang $barang, int $limit = 8): Collection
    {
        $isFoundItemBlocked = Pencocokan::query()
            ->where('barang_id', (int) $barang->id)
            ->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses())
            ->exists();

        if ($isFoundItemBlocked) {
            return collect();
        }

        $query = LaporanBarangHilang::query();

        if (Schema::hasColumn('laporan_barang_hilangs', 'status_laporan')) {
            $query->where('status_laporan', WorkflowStatus::REPORT_APPROVED);
        }

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $query->where('sumber_laporan', 'lapor_hilang');
        }

        $query->whereDoesntHave('pencocokans', function ($matchQuery) use ($barang): void {
            $matchQuery
                ->where('barang_id', (int) $barang->id)
                ->whereIn('status_pencocokan', self::REVIEWED_MATCH_STATUSES);
        });

        $query->whereDoesntHave('pencocokans', function ($matchQuery): void {
            $matchQuery->whereIn('status_pencocokan', WorkflowStatus::blockingMatchStatuses());
        });

        return $query
            ->latest('updated_at')
            ->get()
            ->map(function (LaporanBarangHilang $laporan) use ($barang) {
                $score = $this->scoreService->scoreForPair($laporan, $barang);

                return (object) [
                    'laporan' => $laporan,
                    'score' => $score['score'],
                    'reasons' => $score['reasons'],
                    'meta' => $score['meta'],
                ];
            })
            ->filter(fn ($row) => $row->score >= 35)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
