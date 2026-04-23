<?php

namespace App\Services\Super\Admins;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminVerificationActivityService
{
    public function __construct(private readonly AdminVerificationScopeQueryService $scopeQueryService)
    {
    }

    /**
     * @return Collection<int,Admin>
     */
    public function buildPendingPreview(int $limit = 5, ?int $superAdminId = null): Collection
    {
        return $this->scopeQueryService->baseQuery($superAdminId)
            ->where(function (Builder $query) {
                $query->whereNull('status_verifikasi')->orWhere('status_verifikasi', 'pending');
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int,Admin>
     */
    public function buildLatestActivities(int $limit = 6, ?int $superAdminId = null): Collection
    {
        return $this->scopeQueryService->baseQuery($superAdminId)
            ->orderByDesc('verified_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }
}
