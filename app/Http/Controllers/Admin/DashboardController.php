<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardIndexRequest;
use App\Http\Requests\Admin\DashboardReportTypeRequest;
use App\Http\Requests\Admin\DashboardUpdateReportRequest;
use App\Services\Admin\Dashboard\DashboardQueryService;
use App\Services\Admin\Dashboard\ReportCommandService;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OptimizedImageUploader $imageUploader,
        private readonly ReportCommandService $reportCommandService,
        private readonly DashboardQueryService $reportFeedService
    ) {
    }

    public function index(DashboardIndexRequest $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();
        $dashboardData = $this->reportFeedService->buildDashboardData(
            search: $request->search(),
            statusFilter: $request->statusFilter(),
            page: $request->pageNumber()
        );

        return view('manager::pages.dashboard.index', [
            'totalHilang' => $dashboardData['totalHilang'],
            'totalTemuan' => $dashboardData['totalTemuan'],
            'menungguVerifikasi' => $dashboardData['menungguVerifikasi'],
            'latestReports' => $dashboardData['latestReports'],
            'admin' => $admin,
            'search' => $request->search() ?? '',
            'statusFilter' => $request->statusFilter(),
        ]);
    }

    public function updateReport(DashboardUpdateReportRequest $request, string $type, string|int $id): RedirectResponse
    {
        abort_if(!\App\Support\ManagerPortal::check(), 403);
        $statusMessage = $this->reportCommandService->updateReport(
            type: $request->reportType(),
            id: (int) $id,
            validated: $request->validated(),
            photo: $request->file('foto_barang'),
            imageUploader: $this->imageUploader,
            adminId: (int) \App\Support\ManagerPortal::id()
        );

        return back()->with('status', $statusMessage);
    }

    public function publishToHome(DashboardReportTypeRequest $request, string $type, string|int $id): RedirectResponse
    {
        abort_if(!\App\Support\ManagerPortal::check(), 403);
        $result = $this->reportCommandService->publishToHome($request->reportType(), (int) $id);
        $flashType = $result['status'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
