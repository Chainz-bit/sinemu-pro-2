<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Dashboard\DashboardQueryService;
use App\Services\Admin\Dashboard\ReportCommandService;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OptimizedImageUploader $imageUploader,
        private readonly ReportCommandService $reportCommandService,
        private readonly DashboardQueryService $reportFeedService
    ) {
    }

    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $search = trim((string) $request->query('search', ''));
        $statusFilter = trim((string) $request->query('status', 'semua'));
        $dashboardData = $this->reportFeedService->buildDashboardData(
            search: $search,
            statusFilter: $statusFilter,
            page: (int) $request->query('page', 1)
        );

        return view('admin.pages.dashboard', [
            'totalHilang' => $dashboardData['totalHilang'],
            'totalTemuan' => $dashboardData['totalTemuan'],
            'menungguVerifikasi' => $dashboardData['menungguVerifikasi'],
            'latestReports' => $dashboardData['latestReports'],
            'admin' => $admin,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function updateReport(Request $request, string $type, int $id): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);
        $statusMessage = $this->reportCommandService->updateReport(
            request: $request,
            type: $type,
            id: $id,
            imageUploader: $this->imageUploader,
            adminId: (int) Auth::guard('admin')->id()
        );

        return back()->with('status', $statusMessage);
    }

    public function publishToHome(Request $request, string $type, int $id): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);
        $result = $this->reportCommandService->publishToHome($type, $id);
        $flashType = $result['status'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
