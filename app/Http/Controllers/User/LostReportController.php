<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SubmitLostReportRequest;
use App\Models\Admin;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Models\Wilayah;
use App\Services\User\LostReports\LostReportCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LostReportController extends Controller
{
    public function __construct(private readonly LostReportCommandService $commandService)
    {
    }

    public function create(Request $request)
    {
        $userId = (int) Auth::id();
        $editId = (int) $request->query('edit', 0);
        $editingReport = $this->commandService->resolveEditableReport($userId, $editId);

        return view('user.pages.reports.lost-create', [
            'user' => Auth::user(),
            'lostCategoryOptions' => Cache::remember('lost-report:category-options', 600, static fn () => Kategori::query()
                ->forForm()
                ->pluck('nama_kategori')
                ->filter()
                ->values()),
            'wilayahOptions' => Wilayah::query()
                ->whereHas('admins', static fn ($query) => $query
                    ->where('status_verifikasi', Admin::STATUS_ACTIVE))
                ->orderBy('nama_wilayah')
                ->get(['id', 'nama_wilayah']),
            'editingReport' => $editingReport,
        ]);
    }

    public function store(SubmitLostReportRequest $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $lockKey = 'submit_laporan_hilang_' . $userId . '_' . md5(
            trim((string) $request->input('nama_barang')) . '_' .
            trim((string) $request->input('kategori_barang')) . '_' .
            trim((string) $request->input('tanggal_hilang'))
        );
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return back()->with('warning', 'Laporan Anda sedang diproses, harap tunggu sebentar.');
        }

        try {
            $result = $this->commandService->store($request, $request->validated());
            $flashType = $result['ok'] ? 'status' : 'error';

            return back()->with($flashType, $result['message']);
        } finally {
            $lock->release();
        }
    }

    public function destroy(LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $result = $this->commandService->destroy($laporanBarangHilang, (int) Auth::id());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
