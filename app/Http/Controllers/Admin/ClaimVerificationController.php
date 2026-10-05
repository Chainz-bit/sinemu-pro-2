<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveClaimRequest;
use App\Http\Requests\Admin\ClaimVerificationIndexRequest;
use App\Http\Requests\Admin\RejectClaimRequest;
use App\Jobs\RunAiMatchingJob;
use App\Models\Admin;
use App\Models\Klaim;
use App\Models\Pencocokan;
use App\Services\GeminiMatchingService;
use App\Services\Admin\Claims\ClaimVerificationDetailPageService;
use App\Services\Admin\Claims\ClaimVerificationListingService;
use App\Services\Admin\Claims\ClaimVerificationWorkflowService;
use App\Services\User\Claims\ClaimProofStorageService;
use App\Support\ManagerPortal;
use Illuminate\Http\JsonResponse;
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
            return redirect()->back()->with('error', 'Status klaim saat ini tidak memenuhi syarat untuk disetujui.');
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
            return redirect()->back()->with('error', 'Status klaim saat ini tidak memenuhi syarat untuk ditolak.');
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

    /**
     * Jalankan analisis AI langsung secara on-demand untuk klaim ini.
     * Endpoint: POST verifikasi-klaim/{klaim}/run-ai-analysis
     *           POST verifikasi-klaim/{klaim}/run-ai-match
     */
    public function runAiAnalysis(Klaim $klaim, GeminiMatchingService $matchingService): JsonResponse|RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);

        if (is_null($klaim->barang_id)) {
            $message = 'Klaim tidak memiliki data barang temuan yang valid untuk dianalisis.';
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return redirect()->back()->with('error', $message);
        }

        $klaim->loadMissing([
            'barang.kategori:id,nama_kategori',
            'laporanHilang.kategori:id,nama_kategori',
            'pencocokan',
        ]);

        $barang = $klaim->barang;
        if (!$barang) {
            $message = 'Barang temuan tidak ditemukan.';
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 404);
            }
            return redirect()->back()->with('error', $message);
        }

        // Cari atau buat record pencocokan jika belum terhubung
        $pencocokan = $klaim->pencocokan;
        if (!$pencocokan) {
            if ($klaim->laporan_hilang_id) {
                $pencocokan = Pencocokan::firstOrCreate(
                    [
                        'laporan_hilang_id' => (int) $klaim->laporan_hilang_id,
                        'barang_id' => (int) $barang->id,
                    ],
                    [
                        'status_pencocokan' => 'pending',
                        'admin_id' => $klaim->admin_id,
                    ]
                );
            } else {
                $pencocokan = Pencocokan::create([
                    'laporan_hilang_id' => null,
                    'barang_id' => (int) $barang->id,
                    'status_pencocokan' => 'pending',
                    'admin_id' => $klaim->admin_id,
                ]);
            }

            $klaim->forceFill(['pencocokan_id' => $pencocokan->id])->save();
        }

        // Siapkan data barang temuan
        $barangData = array_merge(
            $barang->toArray(),
            ['nama_kategori' => $barang->kategori?->nama_kategori]
        );

        // Siapkan data laporan hilang atau bukti klaim mandiri
        if ($klaim->laporanHilang) {
            $laporan = $klaim->laporanHilang;
            $laporanData = array_merge(
                $laporan->toArray(),
                ['nama_kategori' => $laporan->kategori?->nama_kategori]
            );
        } else {
            $fotoBukti = null;
            if (!empty($klaim->bukti_foto)) {
                $fotoBukti = is_array($klaim->bukti_foto) ? ($klaim->bukti_foto[0] ?? null) : $klaim->bukti_foto;
            }

            $laporanData = [
                'nama_barang' => 'Klaim Mandiri: ' . ($barang->nama_barang ?? 'Barang'),
                'nama_kategori' => $barang->kategori?->nama_kategori ?? '-',
                'lokasi_hilang' => $klaim->bukti_lokasi_spesifik ?: ($barang->lokasi_ditemukan ?: '-'),
                'tanggal_hilang' => $klaim->bukti_waktu_hilang ?: ($klaim->created_at?->format('Y-m-d') ?: '-'),
                'keterangan' => 'Bukti Kepemilikan: ' . ($klaim->bukti_kepemilikan ?: '-') .
                    ($klaim->bukti_detail_isi ? "\nDetail Isi: " . $klaim->bukti_detail_isi : '') .
                    ($klaim->catatan ? "\nCatatan Pelapor: " . $klaim->catatan : ''),
                'ciri_khusus' => $klaim->bukti_ciri_khusus ?: '-',
                'foto_barang' => $fotoBukti,
            ];
        }

        // Eksekusi GeminiMatchingService secara on-demand
        $result = $matchingService->match($laporanData, $barangData);

        if (!$result['success']) {
            $errorMessage = $result['error'] ?? 'Gagal menjalankan analisis AI.';
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        // Simpan hasil ke tabel pencocokans
        $pencocokan->update([
            'ai_similarity_score' => $result['similarity_score'],
            'ai_recommendation'   => $result['recommendation'],
            'ai_reasoning'        => $result['reasoning'],
            'ai_matched_at'       => now(),
            'ai_model_used'       => $result['model_used'],
        ]);

        $score = (int) $result['similarity_score'];
        $gaugeColor = match (true) {
            $score >= 75 => '#16a34a',
            $score >= 50 => '#d97706',
            default      => '#64748b',
        };

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Analisis AI berhasil dijalankan.',
                'data' => [
                    'similarity_score' => $score,
                    'recommendation' => $result['recommendation'],
                    'recommendation_label' => $pencocokan->aiRecommendationLabel(),
                    'recommendation_class' => $pencocokan->aiRecommendationClass(),
                    'reasoning' => $result['reasoning'],
                    'model_used' => $result['model_used'] ?? '',
                    'matched_at' => $pencocokan->ai_matched_at?->translatedFormat('d M Y, H:i') . ' WIB',
                    'gauge_color' => $gaugeColor,
                    'gauge_offset' => (int) round(157 - ($score / 100) * 157),
                ],
            ]);
        }

        return redirect()->back()->with('status', 'Analisis AI berhasil dijalankan.');
    }

    /**
     * Wrapper alias untuk kompatibilitas route run-ai-match.
     */
    public function runAiMatch(Klaim $klaim, GeminiMatchingService $matchingService): JsonResponse|RedirectResponse
    {
        return $this->runAiAnalysis($klaim, $matchingService);
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
