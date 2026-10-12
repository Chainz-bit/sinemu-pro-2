<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\UserDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly UserDashboardService $dashboardService) {}

    public function index(Request $request)
    {
        abort_unless(Auth::check(), 403);
        $user = Auth::user();
        $userId = (int) $user->id;

        $search = trim((string) $request->query('search', ''));
        $statusFilter = trim((string) $request->query('status', 'semua'));
        $dashboardData = $this->dashboardService->buildDashboardData(
            userId: $userId,
            search: $search,
            statusFilter: $statusFilter,
            page: max((int) $request->query('page', 1), 1)
        );

        return view('user.pages.dashboard.index', [
            'user' => $user,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'totalLaporHilang' => $dashboardData['totalLaporHilang'],
            'totalPengajuanKlaim' => $dashboardData['totalPengajuanKlaim'],
            'menungguVerifikasi' => $dashboardData['menungguVerifikasi'],
            'latestActivities' => $dashboardData['latestActivities'],
        ]);
    }
}
