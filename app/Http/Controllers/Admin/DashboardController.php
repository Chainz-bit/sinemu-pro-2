<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $totalHilangQuery = LaporanBarangHilang::query();
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $totalHilangQuery->where('sumber_laporan', 'lapor_hilang');
        }
        $totalHilang = $totalHilangQuery->count();

        $totalTemuan = Barang::count();

        $menungguVerifikasi = Klaim::where('status_klaim', 'pending')->count();

        $latestReports = $this->paginateReports(
            $this->buildLatestReports(),
            (int) $request->query('page', 1),
            8
        );

        return view('admin.pages.dashboard', compact(
            'totalHilang',
            'totalTemuan',
            'menungguVerifikasi',
            'latestReports',
            'admin'
        ));
    }

    private function buildLatestReports(): Collection
    {
        $lostReportsQuery = LaporanBarangHilang::query()
            ->select('id', 'nama_barang', 'lokasi_hilang', 'tanggal_hilang', 'foto_barang', 'created_at')
            ->with('user:id,name,nama')
            ->selectSub(
                Klaim::query()
                    ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                    ->latest('created_at')
                    ->limit(1)
                    ->select('status_klaim'),
                'latest_claim_status'
            );

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $lostReportsQuery->where('sumber_laporan', 'lapor_hilang');
        }

        $lostReports = $lostReportsQuery
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $status = match ($report->latest_claim_status) {
                    'disetujui' => 'selesai',
                    'ditolak' => 'ditolak',
                    'pending' => 'dalam_peninjauan',
                    default => 'diproses',
                };

                $pelapor = $report->user?->nama ?? $report->user?->name ?? 'Pengguna';

                return (object) [
                    'id' => (int) $report->id,
                    'type' => 'hilang',
                    'item_name' => $report->nama_barang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Barang Hilang - ' . $report->lokasi_hilang,
                    'incident_date' => $report->tanggal_hilang,
                    'created_at' => $report->created_at,
                    'status' => $status,
                    'status_label' => 'Laporan Hilang',
                    'avatar' => 'H',
                    'avatar_class' => 'avatar-sand',
                    'foto_barang' => $report->foto_barang,
                    'target_url' => route('admin.lost-items', ['search' => $report->nama_barang]),
                    'target_label' => 'Buka Barang Hilang',
                    'delete_url' => route('admin.lost-items.destroy', $report->id),
                ];
            });

        $foundReports = Barang::query()
            ->with('admin:id,nama')
            ->select('id', 'nama_barang', 'lokasi_ditemukan', 'tanggal_ditemukan', 'status_barang', 'foto_barang', 'created_at')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $status = match ($report->status_barang) {
                    'dalam_proses_klaim' => 'dalam_peninjauan',
                    'sudah_diklaim', 'sudah_dikembalikan' => 'selesai',
                    default => 'diproses',
                };

                $pelapor = $report->admin?->nama ?? 'Admin';

                return (object) [
                    'id' => (int) $report->id,
                    'type' => 'temuan',
                    'item_name' => $report->nama_barang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Barang Temuan - ' . $report->lokasi_ditemukan,
                    'incident_date' => $report->tanggal_ditemukan,
                    'created_at' => $report->created_at,
                    'status' => $status,
                    'status_label' => 'Barang Temuan',
                    'avatar' => 'T',
                    'avatar_class' => 'avatar-mint',
                    'foto_barang' => $report->foto_barang,
                    'target_url' => route('admin.found-items.show', $report->id),
                    'target_label' => 'Buka Barang Temuan',
                    'delete_url' => route('admin.found-items.destroy', $report->id),
                ];
            });

        $claimReports = Klaim::query()
            ->with([
                'barang:id,nama_barang,lokasi_ditemukan,foto_barang',
                'laporanHilang:id,nama_barang,lokasi_hilang,foto_barang',
                'user:id,nama,name',
            ])
            ->select('id', 'status_klaim', 'created_at', 'barang_id', 'laporan_hilang_id')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($claim) {
                $status = match ($claim->status_klaim) {
                    'disetujui' => 'selesai',
                    'ditolak' => 'ditolak',
                    default => 'dalam_peninjauan',
                };

                $namaBarang = $claim->barang?->nama_barang
                    ?? $claim->laporanHilang?->nama_barang
                    ?? 'Klaim Barang';

                $lokasi = $claim->barang?->lokasi_ditemukan
                    ?? $claim->laporanHilang?->lokasi_hilang
                    ?? 'Lokasi tidak tersedia';
                $pelapor = $claim->user?->nama ?? $claim->user?->name ?? 'Pengguna';

                return (object) [
                    'id' => (int) $claim->id,
                    'type' => 'klaim',
                    'item_name' => $namaBarang,
                    'item_detail' => 'Pelapor: ' . $pelapor . ' - Layanan: Verifikasi Klaim - ' . $lokasi,
                    'incident_date' => $claim->created_at,
                    'created_at' => $claim->created_at,
                    'status' => $status,
                    'status_label' => 'Verifikasi Klaim',
                    'avatar' => 'K',
                    'avatar_class' => 'avatar-claim',
                    'foto_barang' => $claim->barang?->foto_barang ?? $claim->laporanHilang?->foto_barang,
                    'target_url' => route('admin.claim-verifications', [
                        'search' => $namaBarang,
                        'status' => $claim->status_klaim,
                    ]),
                    'target_label' => 'Buka Verifikasi Klaim',
                    'delete_url' => null,
                ];
            });

        return $lostReports
            ->merge($foundReports)
            ->merge($claimReports)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }

    private function paginateReports(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        $page = max($page, 1);
        $total = $items->count();
        $currentPageItems = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
