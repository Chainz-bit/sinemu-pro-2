<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveClaimRequest;
use App\Http\Requests\Admin\ClaimVerificationIndexRequest;
use App\Http\Requests\Admin\RejectClaimRequest;
use App\Models\Admin;
use App\Models\Klaim;
use App\Services\Admin\Claims\ClaimVerificationDetailPageService;
use App\Services\Admin\Claims\ClaimVerificationListingService;
use App\Services\Admin\Claims\ClaimVerificationWorkflowService;
use App\Services\User\Claims\ClaimProofStorageService;
use App\Support\ManagerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimVerificationController extends Controller
{
    public function __construct(
        private readonly ClaimVerificationListingService $listingService,
        private readonly ClaimVerificationDetailPageService $detailPageService,
        private readonly ClaimVerificationWorkflowService $workflowService,
        private readonly ClaimProofStorageService $claimProofStorageService
    ) {
    }

    public function index(ClaimVerificationIndexRequest $request): View|StreamedResponse
    {
        /** @var Admin $admin */
        $admin = ManagerPortal::user();
        $indexState = $this->listingService->prepareIndexQuery($request);
        $query = $indexState['query'];
        $sort = $indexState['sort'];

        if ($request->shouldExport()) {
            return $this->listingService->exportCsv($query);
        }

        $claims = $query->paginate(12)->withQueryString();

        return view('manager::pages.claims.index', compact('claims', 'admin', 'sort'));
    }

    public function approve(ApproveClaimRequest $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        $adminId = (int) ManagerPortal::id();

        if (!$this->workflowService->canApprove($klaim)) {
            return redirect()->back()->with('error', 'Klaim tidak berada pada state yang dapat disetujui.');
        }

        $data = [
            'identitas_pelapor_valid' => $request->input('identitas_pelapor_valid', 0),
            'detail_barang_valid' => $request->input('detail_barang_valid', 0),
            'kronologi_valid' => $request->input('kronologi_valid', 0),
            'bukti_visual_valid' => $request->input('bukti_visual_valid', 0),
            'kecocokan_data_laporan' => $request->input('kecocokan_data_laporan', 0),
            'catatan_verifikasi_admin' => $request->input('catatan_verifikasi_admin'),
            'alasan_penolakan' => $request->input('alasan_penolakan'),
        ];

        if (!$this->workflowService->approve($klaim, $data, $adminId)) {
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
        $adminId = (int) ManagerPortal::id();

        if (!$this->workflowService->canReject($klaim)) {
            return redirect()->back()->with('error', 'Klaim tidak berada pada state yang dapat ditolak.');
        }

        $data = [
            'identitas_pelapor_valid' => $request->input('identitas_pelapor_valid', 0),
            'detail_barang_valid' => $request->input('detail_barang_valid', 0),
            'kronologi_valid' => $request->input('kronologi_valid', 0),
            'bukti_visual_valid' => $request->input('bukti_visual_valid', 0),
            'kecocokan_data_laporan' => $request->input('kecocokan_data_laporan', 0),
            'catatan_verifikasi_admin' => $request->input('catatan_verifikasi_admin'),
            'alasan_penolakan' => $request->input('alasan_penolakan'),
        ];

        if (!$this->workflowService->reject($klaim, $data, $adminId)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Klaim tidak dapat ditolak karena konteks klaim sudah tidak valid.');
        }

        return redirect()->back()->with('status', 'Klaim berhasil ditolak.');
    }

    public function complete(Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        abort_if(!$this->workflowService->canComplete($klaim), 422, 'Klaim harus disetujui sebelum ditandai selesai.');
        if (!$this->workflowService->complete($klaim, (int) ManagerPortal::id())) {
            return redirect()->back()->with('error', 'Klaim tidak dapat ditandai selesai karena konteks klaim sudah tidak valid.');
        }

        return redirect()->back()->with('status', 'Klaim ditandai selesai.');
    }

    public function show(Klaim $klaim): View
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        /** @var Admin|null $admin */
        $admin = ManagerPortal::user();

        $klaim->load([
            'barang.kategori:id,nama_kategori',
            'laporanHilang:id,nama_barang,lokasi_hilang,tanggal_hilang,keterangan,foto_barang,ciri_khusus,bukti_kepemilikan',
            'user:id,name,nama,email',
            'admin:id,nama,email',
        ]);

        return view('manager::pages.claims.show', [
            'admin' => $admin,
            'klaim' => $klaim,
            ...$this->detailPageService->build($klaim),
        ]);
    }

    public function destroy(Klaim $klaim): RedirectResponse
    {
        abort_if(!ManagerPortal::check(), 403);
        $this->ensureClaimOwnedByAdmin($klaim);

        if (! $klaim->canBeDeleted()) {
            return redirect()->back()->with('error', 'Klaim yang masih aktif tidak dapat dihapus.');
        }

        $proofs = $this->claimProofStorageService->collectProofs($klaim);
        DB::transaction(static function () use ($klaim): void {
            $klaim->delete();
        });
        $this->claimProofStorageService->deleteProofs($proofs);

        return redirect()->back()->with('status', 'Data klaim berhasil dihapus.');
    }

    private function ensureClaimOwnedByAdmin(Klaim $klaim): void
    {
        $adminId = ManagerPortal::id();
        if (is_null($klaim->admin_id)) {
            $admin = ManagerPortal::user();
            $klaim->loadMissing(['barang:id,region_id', 'laporanHilang:id,region_id']);

            $canAccessLegacyClaim = false;
            if ($admin instanceof Admin && $admin->region_id) {
                $barangRegionId    = $klaim->barang?->region_id;
                $laporanRegionId   = $klaim->laporanHilang?->region_id;
                $canAccessLegacyClaim = !is_null($barangRegionId)
                    && !is_null($laporanRegionId)
                    && (int) $barangRegionId  === (int) $admin->region_id
                    && (int) $laporanRegionId === (int) $admin->region_id;
            }

            abort_if(!$canAccessLegacyClaim, 403);
            return;
        }

        abort_if((int) $klaim->admin_id !== (int) $adminId, 403);
    }
}
