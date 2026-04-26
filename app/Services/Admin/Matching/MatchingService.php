<?php

namespace App\Services\Admin\Matching;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use Illuminate\Support\Collection;

class MatchingService
{
    public function __construct(
        private readonly MatchingCandidateQueryService $candidateQueryService,
        private readonly MatchingScoreService $scoreService
    ) {
    }

    public function findCandidatesForLostReport(LaporanBarangHilang $laporan, int $limit = 8): Collection
    {
        return $this->candidateQueryService->findCandidatesForLostReport($laporan, $limit);
    }

    public function findCandidatesForFoundItem(Barang $barang, int $limit = 8): Collection
    {
        return $this->candidateQueryService->findCandidatesForFoundItem($barang, $limit);
    }

    /**
     * @return array{score:int,reasons:array<int,string>,meta:array<string,int>}
     */
    public function scoreForPair(LaporanBarangHilang $laporan, Barang $barang): array
    {
        return $this->scoreService->scoreForPair($laporan, $barang);
    }
}
