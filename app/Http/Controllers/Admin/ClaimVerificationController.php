<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveClaimRequest;
use App\Http\Requests\Admin\ClaimVerificationIndexRequest;
use App\Http\Requests\Admin\RejectClaimRequest;
use App\Models\Klaim;
use App\Services\Admin\Claims\ClaimVerificationDetailPageService;
use App\Services\Admin\Claims\ClaimVerificationListingService;
use App\Services\Admin\Claims\ClaimVerificationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimVerificationController extends Controller
{
    public function __construct(
        private readonly ClaimVerificationListingService $listingService,
        private readonly ClaimVerificationDetailPageService $detailPageService,
        private readonly ClaimVerificationWorkflowService $workflowService
    ) {
    }

    public function index(ClaimVerificationIndexRequest $request): View|StreamedResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $indexState = $this->listingService->prepareIndexQuery($request);
        $query = $indexState['query'];
        $sort = $indexState['sort'];

        if ($request->shouldExport()) {
            return $this->listingService->exportCsv($query);
        }

        $claims = $query->paginate(12)->withQueryString();

        return view('admin.pages.claim-verifications', compact('claims', 'admin', 'sort'));
    }

    public function approve(ApproveClaimRequest $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        $adminId = (int) Auth::guard('admin')->id();

        if (!$this->workflowService->canApprove($klaim)) {
            return redirect()->back()->with('error', 'Klaim tidak berada pada state yang dapat disetujui.');
        }

        if (!$this->workflowService->approve($klaim, $request->validated(), $adminId)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Klaim tidak dapat disetujui. Skor verifikasi minimal 75 dan semua poin kritikal harus lolos.');
        }

        return redirect()->back()->with('status', 'Klaim berhasil disetujui.');
    }

    public function reject(RejectClaimRequest $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        $adminId = (int) Auth::guard('admin')->id();

        if (!$this->workflowService->canReject($klaim)) {
            return redirect()->back()->with('error', 'Klaim tidak berada pada state yang dapat ditolak.');
        }

        $this->workflowService->reject($klaim, $request->validated(), $adminId);

        return redirect()->back()->with('status', 'Klaim berhasil ditolak.');
    }

    public function complete(Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        abort_if(!$this->workflowService->canComplete($klaim), 422, 'Klaim harus disetujui sebelum ditandai selesai.');
        $this->workflowService->complete($klaim, (int) Auth::guard('admin')->id());

        return redirect()->back()->with('status', 'Klaim ditandai selesai.');
    }

    public function show(Klaim $klaim): View
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $klaim->load([
            'barang.kategori:id,nama_kategori',
            'laporanHilang:id,nama_barang,lokasi_hilang,tanggal_hilang,keterangan,foto_barang,ciri_khusus,bukti_kepemilikan',
            'user:id,name,nama,email',
            'admin:id,nama,email',
        ]);

        return view('admin.pages.claim-verification-detail', [
            'admin' => $admin,
            'klaim' => $klaim,
            ...$this->detailPageService->build($klaim),
        ]);
    }

    public function destroy(Klaim $klaim): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);

        foreach ((array) ($klaim->bukti_foto ?? []) as $path) {
            if (is_string($path) && trim($path) !== '') {
                Storage::disk('public')->delete($path);
            }
        }

        $klaim->delete();

        return redirect()->back()->with('status', 'Data klaim berhasil dihapus.');
    }

    private function ensureClaimOwnedByAdmin(Klaim $klaim): void
    {
        $adminId = Auth::guard('admin')->id();
        if (is_null($klaim->admin_id)) {
            return;
        }

        abort_if((int) $klaim->admin_id !== (int) $adminId, 403);
    }
}
