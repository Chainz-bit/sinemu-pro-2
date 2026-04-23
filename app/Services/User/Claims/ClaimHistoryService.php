<?php

namespace App\Services\User\Claims;

use App\Models\Klaim;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ClaimHistoryService
{
    public function __construct(
        private readonly ClaimHistoryQueryService $queryService,
        private readonly ClaimHistoryItemPresenter $itemPresenter
    ) {
    }

    /**
     * @return array{claims:LengthAwarePaginator,search:string,statusFilter:string,typeFilter:string}
     */
    public function buildHistoryData(int $userId, array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? 'semua'));
        $type = trim((string) ($query['type'] ?? 'semua'));
        $hasStatusVerifikasi = Schema::hasColumn('klaims', 'status_verifikasi');

        $claimsQuery = $this->queryService->build($userId, $search, $status, $type, $hasStatusVerifikasi);

        $claims = $claimsQuery->paginate(8)->withQueryString();
        $claims->setCollection(
            $claims->getCollection()->map(function (Klaim $claim) use ($hasStatusVerifikasi) {
                return $this->itemPresenter->present($claim, $hasStatusVerifikasi);
            })
        );

        return [
            'claims' => $claims,
            'search' => $search,
            'statusFilter' => $status,
            'typeFilter' => $type,
        ];
    }
}
