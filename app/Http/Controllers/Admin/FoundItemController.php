<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\BarangStatusHistory;
use App\Services\ReportImageCleaner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoundItemController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        $query = Barang::query()
            ->with(['kategori', 'admin:id,nama'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('nama_barang', 'like', '%'.$search.'%');
        }

        if ($request->filled('status')) {
            $allowedStatus = ['tersedia', 'dalam_proses_klaim', 'sudah_diklaim', 'sudah_dikembalikan'];
            $status = (string) $request->query('status');
            if (in_array($status, $allowedStatus, true)) {
                $query->where('status_barang', $status);
            }
        }

        if ($request->filled('date')) {
            $query->whereDate('tanggal_ditemukan', $request->query('date'));
        }

        $sort = (string) $request->query('sort', 'terbaru');
        switch ($sort) {
            case 'terlama':
                $query->orderBy('tanggal_ditemukan');
                break;
            case 'nama_asc':
                $query->orderBy('nama_barang');
                break;
            case 'nama_desc':
                $query->orderByDesc('nama_barang');
                break;
            default:
                $query->orderByDesc('tanggal_ditemukan');
                break;
        }

        if ($request->boolean('export')) {
            $exportItems = $query->get();

            return new StreamedResponse(function () use ($exportItems) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Nama Barang', 'Kategori', 'Tanggal Ditemukan', 'Lokasi', 'Status']);

                foreach ($exportItems as $item) {
                    fputcsv($handle, [
                        $item->nama_barang,
                        $item->kategori?->nama_kategori ?? 'Tanpa Kategori',
                        $item->tanggal_ditemukan,
                        $item->lokasi_ditemukan,
                        $item->status_barang,
                    ]);
                }

                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="barang-temuan.csv"',
            ]);
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

        return view('admin.pages.found-item-detail', compact('barang', 'admin'));
    }

    public function updateStatus(Request $request, Barang $barang): RedirectResponse
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'status_barang' => ['required', 'in:tersedia,dalam_proses_klaim,sudah_diklaim,sudah_dikembalikan'],
            'catatan_status' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = (string) $barang->status_barang;
        $newStatus = (string) $validated['status_barang'];

        if ($oldStatus !== $newStatus) {
            $barang->update(['status_barang' => $newStatus]);

            BarangStatusHistory::create([
                'barang_id' => $barang->id,
                'admin_id' => $admin?->id,
                'status_lama' => $oldStatus,
                'status_baru' => $newStatus,
                'catatan' => $validated['catatan_status'] ?? null,
            ]);

            return redirect()
                ->route('admin.found-items.show', $barang->id)
                ->with('status', 'Perubahan status berhasil disimpan.');
        }

        return redirect()
            ->route('admin.found-items.show', $barang->id)
            ->with('status', 'Tidak ada perubahan status yang disimpan.');
    }

    public function export(Barang $barang): Response
    {
        $barang->loadMissing(['kategori:id,nama_kategori', 'admin:id,nama,email']);

        $statusLabel = match ($barang->status_barang) {
            'tersedia' => 'Tersedia',
            'dalam_proses_klaim' => 'Dalam Proses Klaim',
            'sudah_diklaim' => 'Sudah Diklaim',
            'sudah_dikembalikan' => 'Sudah Dikembalikan',
            default => 'Tidak Diketahui',
        };

        $photoDataUri = null;
        if (!empty($barang->foto_barang) && Storage::disk('public')->exists($barang->foto_barang)) {
            $absolutePath = Storage::disk('public')->path($barang->foto_barang);
            $mimeType = Storage::disk('public')->mimeType($barang->foto_barang) ?: 'image/jpeg';
            $photoDataUri = 'data:' . $mimeType . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
        }

        $pdf = Pdf::loadView('admin.pdf.found-item-report', [
            'barang' => $barang,
            'statusLabel' => $statusLabel,
            'photoDataUri' => $photoDataUri,
        ])->setPaper('a4');

        return $pdf->download('laporan-barang-temuan-' . $barang->id . '.pdf');
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $photoPath = $barang->foto_barang;

        $barang->delete();
        ReportImageCleaner::purgeIfOrphaned($photoPath);

        return redirect()->back()->with('status', 'Laporan barang temuan berhasil dihapus.');
    }
}
