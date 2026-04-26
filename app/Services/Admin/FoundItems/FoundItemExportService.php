<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Support\WorkflowStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FoundItemExportService
{
    public function export(Barang $barang): Response
    {
        $barang->loadMissing(['kategori:id,nama_kategori', 'admin:id,nama,email']);

        $pdf = Pdf::loadView('admin.pdf.found-item-report', [
            'barang' => $barang,
            'statusLabel' => $this->resolveStatusLabel((string) $barang->status_barang),
            'photoDataUri' => $this->resolvePhotoDataUri((string) ($barang->foto_barang ?? '')),
        ])->setPaper('a4');

        return $pdf->download('laporan-barang-temuan-' . $barang->id . '.pdf');
    }

    private function resolveStatusLabel(string $status): string
    {
        return match ($status) {
            WorkflowStatus::FOUND_AVAILABLE => 'Tersedia',
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => 'Dalam Proses Klaim',
            WorkflowStatus::FOUND_CLAIMED => 'Sudah Diklaim',
            WorkflowStatus::FOUND_RETURNED => 'Sudah Dikembalikan',
            default => 'Tidak Diketahui',
        };
    }

    private function resolvePhotoDataUri(string $photoPath): ?string
    {
        if ($photoPath === '' || !Storage::disk('public')->exists($photoPath)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($photoPath);
        $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';

        return 'data:' . $mimeType . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
    }
}
