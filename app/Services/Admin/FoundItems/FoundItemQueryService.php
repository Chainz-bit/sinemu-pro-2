<?php

namespace App\Services\Admin\FoundItems;

use App\Http\Requests\Admin\FoundItemIndexRequest;
use App\Models\Barang;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FoundItemQueryService
{
    /**
     * @return array{query:Builder,sort:string}
     */
    public function buildIndexQuery(FoundItemIndexRequest $request): array
    {
        $query = Barang::query()
            ->with(['kategori', 'admin:id,nama']);

        $sort = $request->sort();

        $this->applySearch($query, $request->search());
        $this->applyStatus($query, $request->status());
        $this->applyDate($query, $request->filterDate());
        $this->applySort($query, $sort);

        return [
            'query' => $query,
            'sort' => $sort,
        ];
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search !== null) {
            $query->where('nama_barang', 'like', '%' . $search . '%');
        }
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status !== null) {
            $query->where('status_barang', $status);
        }
    }

    private function applyDate(Builder $query, ?string $date): void
    {
        if ($date !== null) {
            $query->whereDate('tanggal_ditemukan', $date);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            FoundItemIndexRequest::SORT_OLDEST => $query->orderBy('updated_at'),
            FoundItemIndexRequest::SORT_NAME_ASC => $query->orderBy('nama_barang'),
            FoundItemIndexRequest::SORT_NAME_DESC => $query->orderByDesc('nama_barang'),
            default => $query->orderByDesc('updated_at'),
        };
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
