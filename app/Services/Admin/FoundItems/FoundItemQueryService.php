<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoundItemQueryService
{
    /**
     * @return array{query:Builder,sort:string}
     */
    public function buildIndexQuery(Request $request): array
    {
        $query = Barang::query()
            ->with(['kategori', 'admin:id,nama'])
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where('nama_barang', 'like', '%' . $search . '%');
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
                $query->orderBy('updated_at');
                break;
            case 'nama_asc':
                $query->orderBy('nama_barang');
                break;
            case 'nama_desc':
                $query->orderByDesc('nama_barang');
                break;
            default:
                $query->orderByDesc('updated_at');
                break;
        }

        return [
            'query' => $query,
            'sort' => $sort,
        ];
    }

    public function exportCsv($items): StreamedResponse
    {
        return new StreamedResponse(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nama Barang', 'Kategori', 'Tanggal Ditemukan', 'Lokasi', 'Status']);

            foreach ($items as $item) {
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
}
