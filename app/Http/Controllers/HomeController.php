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

    public function index(): View|RedirectResponse
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
