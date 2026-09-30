<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LostItemIndexRequest;
use App\Http\Requests\Admin\UpdateLostItemStatusRequest;
use App\Http\Requests\Admin\UpdateLostItemRequest;
use App\Http\Requests\Admin\VerifyLostItemReportRequest;
use App\Models\Kategori;
use App\Models\LaporanBarangHilang;
use App\Services\Admin\Matching\MatchingService;
use App\Services\Admin\LostItems\LostItemCommandService;
use App\Services\Admin\LostItems\LostItemExportService;
use App\Services\Admin\LostItems\LostItemQueryService;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
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
        private readonly LostItemCommandService $commandService,
        private readonly OptimizedImageUploader $imageUploader,
        private readonly MatchingService $matchingService,
    ) {
    }

    public function index(LostItemIndexRequest $request): View|StreamedResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();

        $query = $this->queryService->buildIndexQuery($request);
        $sort = $request->sort();

        if ($request->shouldExport()) {
            return $this->exportService->exportCsv($query->get());
        }

        $items = $query->paginate(12)->withQueryString();

        return view('manager::pages.lost-items.index', compact('items', 'admin', 'sort'));
    }

    public function show(LaporanBarangHilang $laporanBarangHilang): View|RedirectResponse
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $laporanBarangHilang->sumber_laporan !== 'lapor_hilang') {
            return redirect()->route(\App\Support\ManagerPortal::routeName('lost-items'));
        }

        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();

        $laporanBarangHilang->loadMissing(['user:id,nama,name,email', 'klaims' => function ($query) {
            $query->latest('created_at');
        }]);

        $latestKlaim = $laporanBarangHilang->klaims->first();
        $matchingCandidates = collect();
        if ((string) ($laporanBarangHilang->status_laporan ?? '') === WorkflowStatus::REPORT_APPROVED) {
            $matchingCandidates = $this->matchingService->findCandidatesForLostReport($laporanBarangHilang);
        }

        return view('manager::pages.lost-items.show', compact('laporanBarangHilang', 'latestKlaim', 'admin', 'matchingCandidates'));
    }

    public function edit(LaporanBarangHilang $laporanBarangHilang): View|RedirectResponse
    {
        if (Schema::hasColumn('laporan_barang_hilangs', 'sumber_laporan') && $laporanBarangHilang->sumber_laporan !== 'lapor_hilang') {
            return redirect()->route(\App\Support\ManagerPortal::routeName('lost-items'));
        }

        if (!$laporanBarangHilang->canBeEditedByAdmin()) {
            return redirect()
                ->route(\App\Support\ManagerPortal::routeName('lost-items.show'), $laporanBarangHilang->id)
                ->with('error', 'Laporan ini tidak dapat diedit karena sudah diproses.');
        }

        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();

        $lostCategoryOptions = Kategori::query()
            ->forForm()
            ->pluck('nama_kategori')
            ->filter()
            ->values();

        return view('manager::pages.lost-items.edit', compact('laporanBarangHilang', 'admin', 'lostCategoryOptions'));
    }

    public function update(UpdateLostItemRequest $request, LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $result = $this->commandService->update($laporanBarangHilang, $request->validated(), $request->file('foto_barang'), $this->imageUploader);

        if (!$result['ok']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()
            ->route(\App\Support\ManagerPortal::routeName('lost-items.show'), $laporanBarangHilang->id)
            ->with('status', $result['message']);
    }

    public function togglePublikasi(LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();

        // Authorization: hanya pengelola dengan region yang sama yang boleh toggle
        if (!is_null($laporanBarangHilang->region_id) && (int) $laporanBarangHilang->region_id !== (int) $admin->region_id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah publikasi laporan ini.');
        }

        $result = $this->commandService->togglePublikasi($laporanBarangHilang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()->back()->with($flashType, $result['message']);
    }

    public function destroy(LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $result = $this->commandService->destroy($laporanBarangHilang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()->back()->with($flashType, $result['message']);
    }

    public function updateStatus(UpdateLostItemStatusRequest $request, LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $latestKlaim = $laporanBarangHilang->klaims()->latest('created_at')->first();
        if (!$latestKlaim) {
            return back()->with('error', 'Belum ada klaim aktif untuk laporan ini.');
        }
        return redirect()
            ->route(\App\Support\ManagerPortal::routeName('claim-verifications.show'), $latestKlaim->id)
            ->with('error', 'Perbarui status klaim dari halaman Verifikasi Klaim agar checklist keamanan tetap diterapkan.');
    }

    public function verify(VerifyLostItemReportRequest $request, LaporanBarangHilang $laporanBarangHilang): RedirectResponse
    {
        $result = $this->commandService->verify($laporanBarangHilang, $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }
}
