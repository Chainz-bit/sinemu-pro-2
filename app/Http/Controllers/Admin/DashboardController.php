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
        $admin = Auth::guard('admin')->user();
        $dashboardData = $this->reportFeedService->buildDashboardData(
            search: $request->search(),
            statusFilter: $request->statusFilter(),
            page: $request->pageNumber()
        );

        return view('admin.pages.dashboard', [
            'totalHilang' => $dashboardData['totalHilang'],
            'totalTemuan' => $dashboardData['totalTemuan'],
            'menungguVerifikasi' => $dashboardData['menungguVerifikasi'],
            'latestReports' => $dashboardData['latestReports'],
            'admin' => $admin,
            'search' => $request->search() ?? '',
            'statusFilter' => $request->statusFilter(),
        ]);
    }

    public function updateReport(DashboardUpdateReportRequest $request, int $id): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);
        $statusMessage = $this->reportCommandService->updateReport(
            type: $request->reportType(),
            id: $id,
            validated: $request->validated(),
            photo: $request->file('foto_barang'),
            imageUploader: $this->imageUploader,
            adminId: (int) Auth::guard('admin')->id()
        );

        return back()->with('status', $statusMessage);
    }

    public function publishToHome(DashboardReportTypeRequest $request, int $id): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);
        $result = $this->reportCommandService->publishToHome($request->reportType(), $id);
        $flashType = $result['status'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
