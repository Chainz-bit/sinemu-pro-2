<?php

namespace App\Services\Super\Admins;

use Illuminate\Database\Eloquent\Builder;

class AdminVerificationSummaryService
{
    public function __construct(private readonly AdminVerificationScopeQueryService $scopeQueryService)
    {
    }

    /**
     * @return array{
     *   total:int,
     *   pending:int,
     *   active:int,
     *   rejected:int,
     *   newThisWeek:int
     * }
     */
    public function buildSummary(?int $superAdminId = null): array
    {
        $baseQuery = $this->scopeQueryService->baseQuery($superAdminId);

        return [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where(function (Builder $query) {
                $query->whereNull('status_verifikasi')->orWhere('status_verifikasi', 'pending');
            })->count(),
            'active' => (clone $baseQuery)->where('status_verifikasi', 'active')->count(),
            'rejected' => (clone $baseQuery)->where('status_verifikasi', 'rejected')->count(),
            'newThisWeek' => (clone $baseQuery)->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
