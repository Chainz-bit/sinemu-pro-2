<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Services\ReportImageCleaner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FoundItemDeletionService
{
    /**
     * @return array{ok:bool,message:string}
     */
    public function destroy(Barang $barang): array
    {
        if (!$barang->hasAdminDeletableReportStatus()) {
            return ['ok' => false, 'message' => 'Barang temuan yang masih aktif tidak dapat dihapus.'];
        }

        if ($barang->hasAdminDeletionWorkflowBlocker()) {
            return ['ok' => false, 'message' => 'Barang ini tidak dapat dihapus karena sudah terhubung dengan proses klaim atau pencocokan.'];
        }

        $photoPath = $barang->foto_barang;

        DB::transaction(static function () use ($barang): void {
            $barang->delete();
        });

        $this->purgeFoundItemPhotoAfterCommit($photoPath);

        return ['ok' => true, 'message' => 'Laporan barang temuan berhasil dihapus.'];
    }

    private function purgeFoundItemPhotoAfterCommit(?string $photoPath): void
    {
        try {
            ReportImageCleaner::purgeIfOrphaned($photoPath);
        } catch (Throwable $exception) {
            Log::error('Foto barang temuan gagal dihapus setelah record barang dihapus.', [
                'path' => $photoPath,
                'exception' => $exception,
            ]);
        }
    }
}
