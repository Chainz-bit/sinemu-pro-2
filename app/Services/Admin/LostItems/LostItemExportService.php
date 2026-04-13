<?php

namespace App\Services\Admin\LostItems;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LostItemExportService
{
    public function exportCsv(Collection $items): StreamedResponse
    {
        return new StreamedResponse(function () use ($items): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nama Barang', 'Pelapor', 'Tanggal Hilang', 'Lokasi Hilang', 'Status']);

            foreach ($items as $item) {
                $status = $item->latest_claim_status ?? 'belum_diklaim';

                fputcsv($handle, [
                    $item->nama_barang,
                    $item->user?->nama ?? $item->user?->name ?? 'Pengguna',
                    $item->tanggal_hilang,
                    $item->lokasi_hilang,
                    $status,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="laporan-barang-hilang.csv"',
        ]);
    }
}
