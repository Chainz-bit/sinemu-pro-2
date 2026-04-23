<?php

namespace App\Services\Super\Admins;

use App\Models\Admin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminVerificationQueryService
{
    public function __construct(
        private readonly AdminVerificationListingService $listingService,
        private readonly AdminVerificationSummaryService $summaryService,
        private readonly AdminVerificationActivityService $activityService
    ) {
    }

    /**
     * @return array{
     *   summary: array<string,int>,
     *   pendingAdmins: Collection<int,Admin>,
     *   latestActivities: Collection<int,Admin>,
     *   admins: LengthAwarePaginator
     * }
     */
    public function buildIndexData(string $search = '', string $status = 'semua', int $page = 1, int $perPage = 10, ?int $superAdminId = null): array
    {
        return [
            'summary' => $this->summaryService->buildSummary($superAdminId),
            'pendingAdmins' => $this->activityService->buildPendingPreview(5, $superAdminId),
            'latestActivities' => $this->activityService->buildLatestActivities(6, $superAdminId),
            'admins' => $this->listingService->buildListingQuery($search, $status, $superAdminId)
                ->paginate($perPage, ['*'], 'page', max($page, 1))
                ->withQueryString(),
        ];
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
        return $this->summaryService->buildSummary($superAdminId);
    }

    /**
     * @return Collection<int,Admin>
     */
    public function buildPendingPreview(int $limit = 5, ?int $superAdminId = null): Collection
    {
        return $this->activityService->buildPendingPreview($limit, $superAdminId);
    }

    /**
     * @return Collection<int,Admin>
     */
    public function buildLatestActivities(int $limit = 6, ?int $superAdminId = null): Collection
    {
        return $this->activityService->buildLatestActivities($limit, $superAdminId);
    }
}
