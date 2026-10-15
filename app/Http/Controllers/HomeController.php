<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Services\Home\HomeFoundDetailService;
use App\Services\Home\HomeLostDetailService;
use App\Services\Home\HomePageViewService;
use App\Support\ManagerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly HomePageViewService $homePageService) {}

    public function index(): View
    {
        $databaseResponsive = $this->homePageService->isDatabaseResponsive();

        $authenticatedUser = null;
        $authGuard = null;
        $dashboardUrl = route('home');
        $logoutUrl = route('logout');
        $userSubtitle = 'Pengguna';

        if ($databaseResponsive) {
            if (Auth::guard('super_admin')->check()) {
                $authenticatedUser = Auth::guard('super_admin')->user();
                $authGuard = 'super_admin';
                $dashboardUrl = route('super.dashboard');
                $logoutUrl = route('super.logout');
                $userSubtitle = 'Super Admin';
            } elseif (ManagerPortal::check()) {
                $authenticatedUser = ManagerPortal::user();
                $authGuard = 'admin';
                $dashboardUrl = manager_route('dashboard');
                $logoutUrl = manager_route('logout');
                $userSubtitle = $authenticatedUser?->instansi ?: 'Pengelola Barang';
            } elseif (Auth::guard('web')->check()) {
                $authenticatedUser = Auth::guard('web')->user();
                $authGuard = 'web';
                $dashboardUrl = route('user.dashboard');
                $logoutUrl = route('logout');
                $userSubtitle = $authenticatedUser?->location ?: 'Pengguna SiNemu';
            }
        }

        $viewData = $this->homePageService->buildHomeViewData(
            currentUser: $authGuard === 'web' ? $authenticatedUser : null,
            includeClaimableReports: false,
            authenticatedUser: $authenticatedUser,
            authGuard: $authGuard,
            dashboardUrl: $dashboardUrl,
            logoutUrl: $logoutUrl,
            userSubtitle: $userSubtitle
        );

        return view('home.pages.index', $viewData);
    }

    public function showLostDetail(LaporanBarangHilang $laporanBarangHilang, HomeLostDetailService $detailService): View
    {
        $laporanBarangHilang->loadMissing('user:id,nama,name');

        return view('home.pages.detail', $detailService->build($laporanBarangHilang));
    }

    public function showFoundDetail(Barang $barang, HomeFoundDetailService $detailService): View
    {
        $barang->loadMissing(['admin:id,nama,instansi', 'kategori:id,nama_kategori']);

        return view('home.pages.detail', $detailService->build($barang));
    }
}
