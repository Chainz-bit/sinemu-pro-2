<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Klaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimVerificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();
        $hasNamaColumn = Schema::hasColumn('users', 'nama');
        $hasNameColumn = Schema::hasColumn('users', 'name');
        $hasLostHomeFlag = Schema::hasColumn('laporan_barang_hilangs', 'tampil_di_home');
        $hasFoundHomeFlag = Schema::hasColumn('barangs', 'tampil_di_home');

        $pelaporSelect = '"Pengguna"';
        if ($hasNamaColumn && $hasNameColumn) {
            $pelaporSelect = 'COALESCE(users.nama, users.name, "Pengguna")';
        } elseif ($hasNamaColumn) {
            $pelaporSelect = 'COALESCE(users.nama, "Pengguna")';
        } elseif ($hasNameColumn) {
            $pelaporSelect = 'COALESCE(users.name, "Pengguna")';
        }

        $query = Klaim::query()
            ->leftJoin('laporan_barang_hilangs', 'laporan_barang_hilangs.id', '=', 'klaims.laporan_hilang_id')
            ->leftJoin('barangs', 'barangs.id', '=', 'klaims.barang_id')
            ->leftJoin('users', 'users.id', '=', 'klaims.user_id')
            ->leftJoin('admins', 'admins.id', '=', 'klaims.admin_id')
            ->select([
                'klaims.id',
                'klaims.admin_id',
                'klaims.barang_id',
                'klaims.laporan_hilang_id',
                'klaims.status_klaim',
                'klaims.catatan',
                'klaims.created_at',
                'klaims.updated_at',
                DB::raw($pelaporSelect.' as pelapor_nama'),
                DB::raw('COALESCE(laporan_barang_hilangs.nama_barang, "-") as barang_hilang'),
                DB::raw('COALESCE(barangs.nama_barang, "-") as barang_temuan'),
                DB::raw('COALESCE(barangs.lokasi_ditemukan, "-") as lokasi'),
                DB::raw('COALESCE(barangs.status_barang, "-") as status_barang_temuan'),
                DB::raw('COALESCE(admins.nama, "-") as admin_nama'),
                DB::raw(($hasLostHomeFlag ? 'COALESCE(laporan_barang_hilangs.tampil_di_home, 0)' : '0').' as laporan_hilang_tampil_di_home'),
                DB::raw(($hasFoundHomeFlag ? 'COALESCE(barangs.tampil_di_home, 0)' : '0').' as barang_tampil_di_home'),
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search, $hasNamaColumn, $hasNameColumn) {
                $q->where('laporan_barang_hilangs.nama_barang', 'like', '%'.$search.'%')
                    ->orWhere('barangs.nama_barang', 'like', '%'.$search.'%');

                if ($hasNamaColumn) {
                    $q->orWhere('users.nama', 'like', '%'.$search.'%');
                }

                if ($hasNameColumn) {
                    $q->orWhere('users.name', 'like', '%'.$search.'%');
                }
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if (in_array($status, ['pending', 'disetujui', 'ditolak'], true)) {
                $query->where('klaims.status_klaim', $status);
            }
        }

        if ($request->filled('date')) {
            $query->whereDate('klaims.created_at', $request->query('date'));
        }

        $sort = (string) $request->query('sort', 'terbaru');
        if ($sort === 'terlama') {
            $query->orderBy('klaims.updated_at');
        } else {
            $query->orderByDesc('klaims.updated_at');
        }

        if ($request->boolean('export')) {
            $exportClaims = $query->get();

            return new StreamedResponse(function () use ($exportClaims) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Pelapor', 'Barang Temuan', 'Barang Hilang', 'Lokasi', 'Status Klaim', 'Tanggal Klaim']);

                foreach ($exportClaims as $claim) {
                    fputcsv($handle, [
                        $claim->pelapor_nama,
                        $claim->barang_temuan,
                        $claim->barang_hilang,
                        $claim->lokasi,
                        $claim->status_klaim,
                        $claim->created_at,
                    ]);
                }

                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="verifikasi-klaim.csv"',
            ]);
        }

        $claims = $query->paginate(12)->withQueryString();

        return view('admin.pages.claim-verifications', compact('claims', 'admin', 'sort'));
    }

    public function approve(Request $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);

        if ($klaim->status_klaim === 'pending') {
            $klaim->update(['status_klaim' => 'disetujui']);
            if ($klaim->barang) {
                $klaim->barang->update(['status_barang' => 'sudah_diklaim']);
            }
        }

        return redirect()->back()->with('status', 'Klaim berhasil disetujui.');
    }

    public function reject(Request $request, Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);

        if ($klaim->status_klaim === 'pending') {
            $klaim->update(['status_klaim' => 'ditolak']);
            if ($klaim->barang && $klaim->barang->status_barang === 'dalam_proses_klaim') {
                $klaim->barang->update(['status_barang' => 'tersedia']);
            }
        }

        return redirect()->back()->with('status', 'Klaim berhasil ditolak.');
    }

    public function show(Klaim $klaim): RedirectResponse
    {
        $this->ensureClaimOwnedByAdmin($klaim);
        $keyword = $klaim->barang?->nama_barang
            ?? $klaim->laporanHilang?->nama_barang
            ?? '';

        return redirect()->route('admin.claim-verifications', [
            'search' => $keyword,
            'status' => $klaim->status_klaim,
        ]);
    }

    public function destroy(Klaim $klaim): RedirectResponse
    {
        abort_if(!Auth::guard('admin')->check(), 403);
        $klaim->delete();

        return redirect()->back()->with('status', 'Data klaim berhasil dihapus.');
    }

    private function ensureClaimOwnedByAdmin(Klaim $klaim): void
    {
        $adminId = Auth::guard('admin')->id();
        abort_if((int) $klaim->admin_id !== (int) $adminId, 403);
    }
}
