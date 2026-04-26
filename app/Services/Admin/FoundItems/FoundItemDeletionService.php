<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Services\ReportImageCleaner;

class FoundItemDeletionService
{
    public function destroy(Barang $barang): void
    {
        $photoPath = $barang->foto_barang;
        $barang->delete();

        ReportImageCleaner::purgeIfOrphaned($photoPath);
    }
}
