<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FoundItemIndexRequest;
use App\Http\Requests\Admin\UpdateFoundItemRequest;
use App\Http\Requests\Admin\UpdateFoundItemStatusRequest;
use App\Http\Requests\Admin\VerifyFoundItemReportRequest;
use App\Models\Barang;
use App\Models\Kategori;
use App\Services\Admin\FoundItems\FoundItemCommandService;
use App\Services\Admin\FoundItems\FoundItemDeletionService;
use App\Services\Admin\FoundItems\FoundItemExportService;
use App\Services\Admin\FoundItems\FoundItemQueryService;
use App\Services\Admin\FoundItems\FoundItemStatusService;
use App\Services\Admin\FoundItems\FoundItemVerificationService;
use App\Services\Admin\Matching\MatchingService;
use App\Support\WorkflowStatus;
use App\Support\Media\OptimizedImageUploader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoundItemController extends Controller
{
    public function __construct(
        private readonly OptimizedImageUploader $imageUploader,
        private readonly FoundItemQueryService $queryService,
        private readonly FoundItemCommandService $commandService,
        private readonly FoundItemExportService $exportService,
        private readonly FoundItemDeletionService $deletionService,
        private readonly FoundItemStatusService $statusService,
        private readonly FoundItemVerificationService $verificationService,
        private readonly MatchingService $matchingService,
    )
    {
    }

    public function index(FoundItemIndexRequest $request): View|StreamedResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();
        $state = $this->queryService->buildIndexQuery($request);
        $query = $state['query'];
        $sort = $state['sort'];

        if ($request->shouldExport()) {
            return $this->queryService->exportCsv($query->get());
        }

        $items = $query->paginate(12)->withQueryString();

        return view('manager::pages.found-items.index', compact('items', 'admin', 'sort'));
    }

    public function show(Barang $barang): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();

        $barang->loadMissing([
            'kategori:id,nama_kategori',
            'admin:id,nama,email',
            'statusHistories.admin:id,nama',
        ]);

        $matchingCandidates = collect();
        if ((string) ($barang->status_laporan ?? '') === WorkflowStatus::REPORT_APPROVED) {
            $matchingCandidates = $this->matchingService->findCandidatesForFoundItem($barang);
        }

        return view('manager::pages.found-items.show', compact('barang', 'admin', 'matchingCandidates'));
    }

    public function edit(Barang $barang): View|RedirectResponse
    {
        if (!$barang->canBeEditedByAdmin()) {
            return redirect()
                ->route(\App\Support\ManagerPortal::routeName('found-items.show'), $barang->id)
                ->with('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');
        }

        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();
        $kategoriOptions = Kategori::query()
            ->forForm()
            ->get(['id', 'nama_kategori']);

        return view('manager::pages.found-items.edit', compact('barang', 'admin', 'kategoriOptions'));
    }

    public function update(UpdateFoundItemRequest $request, Barang $barang): RedirectResponse
    {
        $result = $this->commandService->update($barang, $request->validated(), $request->file('foto_barang'), $this->imageUploader);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()
            ->route(\App\Support\ManagerPortal::routeName('found-items.show'), $barang->id)
            ->with($flashType, $result['message']);
    }

    public function updateStatus(UpdateFoundItemStatusRequest $request, Barang $barang): RedirectResponse
    {
        $result = $this->statusService->updateStatus($barang, $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()
            ->route(\App\Support\ManagerPortal::routeName('found-items.show'), $barang->id)
            ->with($flashType, $result['message']);
    }

    public function verify(VerifyFoundItemReportRequest $request, Barang $barang): RedirectResponse
    {
        $result = $this->verificationService->verify($barang, $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }

    public function export(Barang $barang): Response
    {
        return $this->exportService->export($barang);
    }

    public function togglePublikasi(Barang $barang): RedirectResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = \App\Support\ManagerPortal::user();

        // Authorization: hanya pengelola dengan region yang sama yang boleh toggle
        if (!is_null($barang->region_id) && (int) $barang->region_id !== (int) $admin->region_id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah publikasi laporan ini.');
        }

        $result = $this->commandService->togglePublikasi($barang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()->back()->with($flashType, $result['message']);
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $result = $this->deletionService->destroy($barang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()->back()->with($flashType, $result['message']);
    }
}
