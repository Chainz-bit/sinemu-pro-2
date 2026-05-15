<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LaporanBarangHilang;
use App\Services\Home\HomePageViewService;
use App\Support\ManagerPortal;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct(private readonly HomePageViewService $homePageService)
    {
    }

    public function index(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $databaseResponsive = $this->homePageService->isDatabaseResponsive();
        if ($databaseResponsive && Auth::guard('super_admin')->check()) {
            return redirect()->route('super.dashboard');
        }

        if ($databaseResponsive && ManagerPortal::check()) {
            return redirect()->route(ManagerPortal::dashboardRoute());
        }

        $viewData = $this->homePageService->buildHomeViewData(
            currentUser: $databaseResponsive ? Auth::guard('web')->user() : null,
            includeClaimableReports: false
        );

        return view('home.pages.index', $viewData);
    }

    public function showLostDetail(LaporanBarangHilang $laporanBarangHilang)
    {
        return view('home.pages.detail', $this->homePageService->buildLostDetailViewData($laporanBarangHilang));
    }

    public function showFoundDetail(Barang $barang)
    {
        return view('home.pages.detail', $this->homePageService->buildFoundDetailViewData($barang));
    }
}
