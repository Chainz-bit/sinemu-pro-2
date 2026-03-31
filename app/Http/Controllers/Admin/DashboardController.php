<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaporanBarangHilang;
use App\Models\Barang;
use App\Models\Klaim;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Total laporan hilang dari user
        $totalHilang = LaporanBarangHilang::count();

        // Total barang temuan yang diinput admin
        $totalTemuan = Barang::count();

        // Total klaim yang menunggu verifikasi
        $menungguVerifikasi = Klaim::where('status_klaim', 'pending')->count();

        // Gabungkan data terbaru dari laporan hilang dan barang temuan
        $hilang = LaporanBarangHilang::with('user')
            ->select('id', 'user_id', 'nama_barang as item_name', 'lokasi_hilang as location', 'created_at', DB::raw("'hilang' as type"), 'tanggal_hilang as incident_date')
            ->get();

        $temuan = Barang::with('admin')
            ->select('id', 'admin_id as user_id', 'nama_barang as item_name', 'lokasi_ditemukan as location', 'created_at', DB::raw("'temuan' as type"), 'tanggal_ditemukan as incident_date')
            ->get();

        $latestReports = $hilang->merge($temuan)->sortByDesc('created_at')->take(5);

        return view('admin.dashboard', compact('totalHilang', 'totalTemuan', 'menungguVerifikasi', 'latestReports'));
    }
}