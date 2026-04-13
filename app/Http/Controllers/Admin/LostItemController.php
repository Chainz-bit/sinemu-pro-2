<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LostItemIndexRequest;
use App\Models\LaporanBarangHilang;
use App\Services\Admin\LostItems\LostItemExportService;
use App\Services\Admin\LostItems\LostItemQueryService;
use App\Services\ReportImageCleaner;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LostItemController extends Controller
{
    public function __construct(
        private readonly LostItemQueryService $queryService,
        private readonly LostItemExportService $exportService,
    ) {
    }

    public function index(LostItemIndexRequest $request): View|StreamedResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $query = $this->queryService->buildIndexQuery($request);
        $sort = $request->sort();

        if ($request->shouldExport()) {
            return $this->exportService->exportCsv($query->get());
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

    public function updateStatus(Request $request, LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $validated = $request->validate([
            'status_klaim' => ['required', 'in:pending,disetujui,ditolak'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $latestKlaim = $laporanBarangHilang->klaims()->latest('created_at')->first();
        if (!$latestKlaim) {
            return back()->with('error', 'Status tidak bisa diperbarui karena data klaim belum tersedia.');
        }

        $latestKlaim->update([
            'status_klaim' => $validated['status_klaim'],
            'catatan' => $validated['catatan'] ?? null,
            'admin_id' => (int) Auth::guard('admin')->id(),
        ]);

        if ($latestKlaim->barang) {
            if ($validated['status_klaim'] === 'disetujui') {
                $latestKlaim->barang->update(['status_barang' => 'sudah_diklaim']);
            } elseif ($validated['status_klaim'] === 'ditolak' && $latestKlaim->barang->status_barang === 'dalam_proses_klaim') {
                $latestKlaim->barang->update(['status_barang' => 'tersedia']);
            }
        }

        return back()->with('status', 'Status klaim barang hilang berhasil diperbarui.');
    }
}
