<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Klaim;
use App\Services\Admin\Claims\ClaimVerificationListingService;
use App\Services\Admin\Claims\ClaimVerificationWorkflowService;
use App\Support\ClaimStatusPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

class ClaimVerificationController extends Controller
{
    public function __construct(
        private readonly ClaimVerificationListingService $listingService,
        private readonly ClaimVerificationWorkflowService $workflowService
    ) {
    }

    public function index(Request $request): View|StreamedResponse
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $indexState = $this->listingService->prepareIndexQuery($request);
        $query = $indexState['query'];
        $sort = $indexState['sort'];

        if ($request->boolean('export')) {
            return $this->listingService->exportCsv($query);
        }

        $claims = $query->paginate(12)->withQueryString();

        return view('admin.pages.claim-verifications', compact('claims', 'admin', 'sort'));
    }

    public function approve(Request $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        $adminId = (int) Auth::guard('admin')->id();

        if ($klaim->status_klaim === 'pending') {
            $validated = $request->validate($this->workflowService->verificationRules());
            if (!$this->workflowService->approve($klaim, $validated, $adminId)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Klaim tidak dapat disetujui. Skor verifikasi minimal 75 dan semua poin kritikal harus lolos.');
            }
        }

        return redirect()->back()->with('status', 'Klaim berhasil disetujui.');
    }

    public function reject(Request $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        $adminId = (int) Auth::guard('admin')->id();

        if ($klaim->status_klaim === 'pending') {
            $validated = $request->validate($this->workflowService->verificationRules(true));
            $this->workflowService->reject($klaim, $validated, $adminId);
        }

        return redirect()->back()->with('status', 'Klaim berhasil ditolak.');
    }

    public function complete(Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        abort_if($klaim->status_klaim !== 'disetujui', 422, 'Klaim harus disetujui sebelum ditandai selesai.');
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

        $statusKey = ClaimStatusPresenter::key(
            statusKlaim: (string) $klaim->status_klaim,
            statusVerifikasi: Schema::hasColumn('klaims', 'status_verifikasi') ? (string) ($klaim->status_verifikasi ?? '') : null,
            statusBarang: (string) ($klaim->barang?->status_barang ?? '')
        );
        $statusLabel = match ($statusKey) {
            'menunggu' => 'Menunggu Verifikasi',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            default => 'Selesai',
        };
        $statusClass = ClaimStatusPresenter::cssClass($statusKey);

        $barang = $klaim->barang;
        $laporanHilang = $klaim->laporanHilang;
        $namaBarang = $barang?->nama_barang ?? $laporanHilang?->nama_barang ?? 'Barang tidak ditemukan';
        $kategoriNama = $barang?->kategori?->nama_kategori ?? 'Tidak tersedia';
        $lokasi = $barang?->lokasi_ditemukan ?? $laporanHilang?->lokasi_hilang ?? '-';
        $tanggalLaporan = $barang?->tanggal_ditemukan ?? $laporanHilang?->tanggal_hilang ?? $klaim->created_at;
        $deskripsi = $barang?->deskripsi ?? $laporanHilang?->keterangan ?? 'Belum ada deskripsi.';
        $fotoUrl = $this->resolveItemImageUrl((string) ($barang?->foto_barang ?? $laporanHilang?->foto_barang ?? ''));
        $statusBarangMap = [
            'tersedia' => ['Tersedia', 'status-dalam_peninjauan'],
            'dalam_proses_klaim' => ['Dalam Proses Klaim', 'status-diproses'],
            'sudah_diklaim' => ['Sudah Diklaim', 'status-selesai'],
            'sudah_dikembalikan' => ['Selesai', 'status-selesai'],
        ];
        [$statusBarangLabel, $statusBarangClass] = $statusBarangMap[(string) ($barang?->status_barang ?? '')] ?? ['Tidak tersedia', 'status-dalam_peninjauan'];

        $pelapor = $klaim->user;
        $pelaporNama = $pelapor?->nama ?? $pelapor?->name ?? 'Pengguna';
        $pelaporEmail = $pelapor?->email ?? '-';

        return view('admin.pages.claim-verification-detail', compact(
            'admin',
            'klaim',
            'statusLabel',
            'statusClass',
            'namaBarang',
            'kategoriNama',
            'lokasi',
            'tanggalLaporan',
            'deskripsi',
            'fotoUrl',
            'statusBarangLabel',
            'statusBarangClass',
            'pelaporNama',
            'pelaporEmail',
            'statusKey'
        ));
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

    private function resolveItemImageUrl(string $fotoPath): string
    {
        $cleanPath = str_replace('\\', '/', trim($fotoPath, '/'));
        if ($cleanPath === '') {
            return asset('img/login-image.png');
        }

        if (Str::startsWith($cleanPath, ['http://', 'https://'])) {
            return $cleanPath;
        }

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (Str::startsWith($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        [$folder, $subPath] = array_pad(explode('/', $cleanPath, 2), 2, '');
        if (in_array($folder, ['barang-hilang', 'barang-temuan', 'verifikasi-klaim'], true) && $subPath !== '') {
            return route('media.image', ['folder' => $folder, 'path' => $subPath]);
        }

        return asset('storage/' . $cleanPath);
    }

}
