<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Services\Super\Dashboard\SuperDashboardQueryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SuperDashboardQueryService $dashboardQueryService
    ) {}

    public function index(): View
    {
        $superAdmin = Auth::guard('super_admin')->user();

        return view('super.pages.dashboard.index', [
            'superAdmin' => $superAdmin,
            ...$this->dashboardQueryService->buildDashboardData($superAdmin?->id),
        ]);
    }
}
