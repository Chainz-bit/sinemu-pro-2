<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Http\Requests\Super\RejectAdminVerificationRequest;
use App\Models\Admin;
use App\Services\Super\Admins\AdminApprovalService;
use App\Services\Super\Admins\AdminVerificationQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminVerificationController extends Controller
{
    public function __construct(
        private readonly AdminVerificationQueryService $adminVerificationQueryService,
        private readonly AdminApprovalService $adminApprovalService
    ) {
    }

    public function index(Request $request): View
    {
        $superAdmin = Auth::guard('super_admin')->user();
        $search = trim((string) $request->query('search', ''));
        $statusFilter = trim((string) $request->query('status', 'pending'));
        $data = $this->adminVerificationQueryService->buildIndexData(
            search: $search,
            status: $statusFilter,
            page: (int) $request->query('page', 1),
            perPage: 10,
            superAdminId: $superAdmin?->id
        );

        return view('super.admin-verifications.index', [
            'superAdmin' => $superAdmin,
            'search' => $search,
            'statusFilter' => $statusFilter,
            ...$data,
        ]);
    }

    public function accept(Admin $admin): RedirectResponse
    {
        $flash = $this->adminApprovalService->accept(
            admin: $admin,
            superAdminId: Auth::guard('super_admin')->id()
        );

        return back()->with($flash['key'], $flash['message']);
    }

    public function reject(RejectAdminVerificationRequest $request, Admin $admin): RedirectResponse
    {
        $flash = $this->adminApprovalService->reject(
            admin: $admin,
            reason: $request->validated('alasan_penolakan'),
            superAdminId: Auth::guard('super_admin')->id()
        );

        return back()->with($flash['key'], $flash['message']);
    }
}
