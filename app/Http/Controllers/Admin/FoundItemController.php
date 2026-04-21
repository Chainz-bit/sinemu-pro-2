<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Kategori;
use App\Services\Admin\FoundItems\FoundItemCommandService;
use App\Services\Admin\FoundItems\FoundItemQueryService;
use App\Services\Admin\Matching\MatchingService;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FoundItemController extends Controller
{
    public function __construct(
        private readonly OptimizedImageUploader $imageUploader,
        private readonly FoundItemQueryService $queryService,
        private readonly FoundItemCommandService $commandService,
        private readonly MatchingService $matchingService,
    )
    {
    }

    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $state = $this->queryService->buildIndexQuery($request);
        $query = $state['query'];
        $sort = $state['sort'];

        if ($request->boolean('export')) {
            return $this->queryService->exportCsv($query->get());
        }

        $items = $query->paginate(12)->withQueryString();

        return view('admin.pages.found-items', compact('items', 'admin', 'sort'));
    }

    public function show(Barang $barang): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $barang->loadMissing([
            'kategori:id,nama_kategori',
            'admin:id,nama,email',
            'statusHistories.admin:id,nama',
        ]);

        $matchingCandidates = collect();
        if ((string) ($barang->status_laporan ?? '') === WorkflowStatus::REPORT_APPROVED) {
            $matchingCandidates = $this->matchingService->findCandidatesForFoundItem($barang);
        }

        return view('admin.pages.found-item-detail', compact('barang', 'admin', 'matchingCandidates'));
    }

    public function edit(Barang $barang): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $kategoriOptions = Kategori::query()
            ->forForm()
            ->get(['id', 'nama_kategori']);

        return view('admin.pages.found-item-edit', compact('barang', 'admin', 'kategoriOptions'));
    }

    public function update(Request $request, Barang $barang): RedirectResponse
    {
        $this->commandService->update($request, $barang, $this->imageUploader);

        return redirect()
            ->route('admin.found-items.show', $barang->id)
            ->with('status', 'Data barang temuan berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Barang $barang): RedirectResponse
    {
        $result = $this->commandService->updateStatus($request, $barang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()
            ->route('admin.found-items.show', $barang->id)
            ->with($flashType, $result['message']);
    }

    public function verify(Request $request, Barang $barang): RedirectResponse
    {
        $this->commandService->verify($request, $barang);

        return back()->with('status', 'Verifikasi laporan barang temuan berhasil diperbarui.');
    }

    public function export(Barang $barang): Response
    {
        return $this->commandService->export($barang);
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $this->commandService->destroy($barang);

        return redirect()->back()->with('status', 'Laporan barang temuan berhasil dihapus.');
    }
}
