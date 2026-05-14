<?php

namespace App\Services\Super\Dashboard;

use App\Models\Admin;
use App\Services\Super\Admins\AdminVerificationQueryService;
use Illuminate\Support\Collection;

class SuperDashboardQueryService
{
    public function __construct(
        private readonly AdminVerificationQueryService $adminVerificationQueryService,
        private readonly SuperDashboardNewestAdminService $newestAdminService
    ) {
    }

    /**
     * @return array{
     *   summary: array<string,int>,
     *   priorityAdmins: Collection<int,Admin>,
     *   newestAdmins: Collection<int,Admin>,
     *   latestActivities: Collection<int,Admin>
     * }
     */
    public function buildDashboardData(?int $superAdminId = null): array
    {
        return [
            'summary' => $this->adminVerificationQueryService->buildSummary($superAdminId),
            'priorityAdmins' => $this->adminVerificationQueryService->buildPendingPreview(3, $superAdminId),
            'newestAdmins' => $this->newestAdminService->build(4, $superAdminId),
            'latestActivities' => $this->adminVerificationQueryService->buildLatestActivities(4, $superAdminId),
        ];
    }
}
