<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Klaim;
use App\Models\LaporanBarangHilang;
use App\Services\ReportImageCleaner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LostItemController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $query = LaporanBarangHilang::query()
            ->with('user')
            ->select([
                'laporan_barang_hilangs.id',
                'laporan_barang_hilangs.user_id',
                'laporan_barang_hilangs.nama_barang',
                'laporan_barang_hilangs.lokasi_hilang',
                'laporan_barang_hilangs.tanggal_hilang',
                'laporan_barang_hilangs.keterangan',
                'laporan_barang_hilangs.foto_barang',
                'laporan_barang_hilangs.created_at',
            ])
            ->selectSub(
                Klaim::query()
                    ->whereColumn('laporan_hilang_id', 'laporan_barang_hilangs.id')
                    ->latest('created_at')
                    ->limit(1)
                    ->select('status_klaim'),
                'latest_claim_status'
            );

        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            $query->where('laporan_barang_hilangs.sumber_laporan', 'lapor_hilang');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $hasNamaColumn = Schema::hasColumn('users', 'nama');
            $hasNameColumn = Schema::hasColumn('users', 'name');

            $query->where(function ($q) use ($search, $hasNamaColumn, $hasNameColumn) {
                $q->where('nama_barang', 'like', '%'.$search.'%')
                    ->orWhere('lokasi_hilang', 'like', '%'.$search.'%');

                if ($hasNamaColumn || $hasNameColumn) {
                    $q->orWhereHas('user', function ($userQuery) use ($search, $hasNamaColumn, $hasNameColumn) {
                        if ($hasNamaColumn) {
                            $userQuery->where('users.nama', 'like', '%'.$search.'%');
                        }

                        if ($hasNameColumn) {
                            if ($hasNamaColumn) {
                                $userQuery->orWhere('users.name', 'like', '%'.$search.'%');
                            } else {
                                $userQuery->where('users.name', 'like', '%'.$search.'%');
                            }
                        }
                    });
                }
            });
        }

        if ($request->filled('status')) {
            $allowedStatus = ['pending', 'disetujui', 'ditolak'];
            $status = (string) $request->query('status');
            if (in_array($status, $allowedStatus, true)) {
                $query->whereExists(function ($klaimQuery) use ($status) {
                    $klaimQuery->selectRaw('1')
                        ->from('klaims')
                        ->whereColumn('klaims.laporan_hilang_id', 'laporan_barang_hilangs.id')
                        ->where('klaims.status_klaim', $status);
                });
            }
        }

        if ($request->filled('date')) {
            $query->whereDate('tanggal_hilang', $request->query('date'));
        }

        $sort = (string) $request->query('sort', 'terbaru');
        switch ($sort) {
            case 'terlama':
                $query->orderBy('tanggal_hilang');
                break;
            case 'nama_asc':
                $query->orderBy('nama_barang');
                break;
            case 'nama_desc':
                $query->orderByDesc('nama_barang');
                break;
            default:
                $query->orderByDesc('tanggal_hilang');
                break;
        }

        if ($request->boolean('export')) {
            $exportItems = $query->get();

            return new StreamedResponse(function () use ($exportItems) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Nama Barang', 'Pelapor', 'Tanggal Hilang', 'Lokasi Hilang', 'Status']);

                foreach ($exportItems as $item) {
                    $status = $item->latest_claim_status ?? 'belum_diklaim';
                    fputcsv($handle, [
                        $item->nama_barang,
                        $item->user?->nama ?? $item->user?->name ?? 'Pengguna',
                        $item->tanggal_hilang,
                        $item->lokasi_hilang,
                        $status,
                    ]);
                }

                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="laporan-barang-hilang.csv"',
            ]);
        }

        $items = $query->paginate(12)->withQueryString();

        return view('admin.pages.lost-items', compact('items', 'admin', 'sort'));
    }

    public function show(LaporanBarangHilang $laporanBarangHilang): View|RedirectResponse
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $laporanBarangHilang->sumber_laporan !== 'lapor_hilang') {
            return redirect()->route('admin.lost-items');
        }

        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $laporanBarangHilang->loadMissing(['user:id,nama,name,email', 'klaims' => function ($query) {
            $query->latest('created_at');
        }]);

        $latestKlaim = $laporanBarangHilang->klaims->first();

        return view('admin.pages.lost-item-detail', compact('laporanBarangHilang', 'latestKlaim', 'admin'));
    }

    public function destroy(LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan')) {
            abort_if($laporanBarangHilang->sumber_laporan !== 'lapor_hilang', 404);
        }

        $photoPath = $laporanBarangHilang->foto_barang;

        $laporanBarangHilang->delete();
        ReportImageCleaner::purgeIfOrphaned($photoPath);

        return redirect()->back()->with('status', 'Laporan barang hilang berhasil dihapus.');
    }
}
