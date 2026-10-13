<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FoundItemIndexRequest;
use App\Http\Requests\Admin\UpdateFoundItemRequest;
use App\Http\Requests\Admin\UpdateFoundItemStatusRequest;
use App\Http\Requests\Admin\VerifyFoundItemReportRequest;
use App\Models\Admin;
use App\Models\Barang;
use App\Models\Kategori;
use App\Services\Admin\FoundItems\FoundItemCommandService;
use App\Services\Admin\FoundItems\FoundItemDeletionService;
use App\Services\Admin\FoundItems\FoundItemExportService;
use App\Services\Admin\FoundItems\FoundItemQueryService;
use App\Services\Admin\FoundItems\FoundItemStatusService;
use App\Services\Admin\FoundItems\FoundItemVerificationService;
use App\Services\Admin\Matching\MatchingService;
use App\Support\ManagerPortal;
use App\Support\Media\OptimizedImageUploader;
use App\Support\WorkflowStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoundItemController extends Controller
{
    public function __construct(
        private readonly FoundItemQueryService $queryService,
        private readonly FoundItemCommandService $commandService,
    ) {}

    public function index(FoundItemIndexRequest $request): View|StreamedResponse
    {
        /** @var Admin $admin */
        $admin = ManagerPortal::user();
        $state = $this->queryService->buildIndexQuery($request);
        $query = $state['query'];
        $sort = $state['sort'];

        if ($request->shouldExport()) {
            return $this->queryService->exportCsv($query->get());
        }

        $items = $query->paginate(12)->withQueryString();

        return view('manager::pages.found-items.index', compact('items', 'admin', 'sort'));
    }

    public function show(Barang $barang, MatchingService $matchingService): View
    {
        /** @var Admin $admin */
        $admin = ManagerPortal::user();

        $barang->loadMissing([
            'kategori:id,nama_kategori',
            'admin:id,nama,email',
            'statusHistories.admin:id,nama',
            'klaims' => function ($query) {
                $query->with(['user:id,nama,name,email', 'laporanHilang:id,nama_barang'])->latest('created_at');
            },
        ]);

        $matchingCandidates = collect();
        if ((string) ($barang->status_laporan ?? '') === WorkflowStatus::REPORT_APPROVED) {
            $matchingCandidates = $matchingService->findCandidatesForFoundItem($barang);
        }

        return view('manager::pages.found-items.show', compact('barang', 'admin', 'matchingCandidates'));
    }

    public function edit(Barang $barang): View|RedirectResponse
    {
        if (! $barang->canBeEditedByAdmin()) {
            return redirect()
                ->route(ManagerPortal::routeName('found-items.show'), $barang->id)
                ->with('error', 'Barang temuan ini tidak dapat diedit karena sudah diproses.');
        }

        /** @var Admin $admin */
        $admin = ManagerPortal::user();
        $kategoriOptions = Kategori::query()
            ->forForm()
            ->get(['id', 'nama_kategori']);

        return view('manager::pages.found-items.edit', compact('barang', 'admin', 'kategoriOptions'));
    }

    public function update(UpdateFoundItemRequest $request, Barang $barang, OptimizedImageUploader $imageUploader): RedirectResponse
    {
        $result = $this->commandService->update($barang, $request->validated(), $request->file('foto_barang'), $imageUploader);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()
            ->route(ManagerPortal::routeName('found-items.show'), $barang->id)
            ->with($flashType, $result['message']);
    }

    public function updateStatus(UpdateFoundItemStatusRequest $request, Barang $barang, FoundItemStatusService $statusService): RedirectResponse
    {
        $result = $statusService->updateStatus($barang, $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()
            ->route(ManagerPortal::routeName('found-items.show'), $barang->id)
            ->with($flashType, $result['message']);
    }

    public function verify(VerifyFoundItemReportRequest $request, Barang $barang, FoundItemVerificationService $verificationService): RedirectResponse
    {
        $result = $verificationService->verify($barang, $request->validated());
        $flashType = $result['ok'] ? 'status' : 'error';

        return back()->with($flashType, $result['message']);
    }

    public function export(Barang $barang, FoundItemExportService $exportService): Response
    {
        return $exportService->export($barang);
    }

    public function togglePublikasi(Barang $barang): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = ManagerPortal::user();

        $this->authorizeForUser($admin, 'togglePublish', $barang);

        $result = $this->commandService->togglePublikasi($barang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()->back()->with($flashType, $result['message']);
    }

    public function destroy(Barang $barang, FoundItemDeletionService $deletionService): RedirectResponse
    {
        $result = $deletionService->destroy($barang);
        $flashType = $result['ok'] ? 'status' : 'error';

        return redirect()->back()->with($flashType, $result['message']);
    }
}
