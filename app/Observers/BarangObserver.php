<?php

namespace App\Observers;

use App\Models\Barang;
use App\Services\AdminNotificationService;
use App\Support\ManagerPortal;
use App\Support\WorkflowStatus;

class BarangObserver
{
    public function created(Barang $barang): void
    {
        if ($this->isUserSubmittedFoundReport($barang)) {
            if (! $barang->region_id) {
                return;
            }

            AdminNotificationService::notifyAdminsByRegion(
                regionId: (int) $barang->region_id,
                type: 'barang_temuan_baru',
                title: 'Barang temuan baru',
                message: 'Data barang temuan ditambahkan: '.$barang->nama_barang,
                actionUrl: route(ManagerPortal::routeName('found-items')),
                meta: ['barang_id' => $barang->id]
            );

            return;
        }

        if (! $barang->admin_id) {
            return;
        }

        AdminNotificationService::notifyAdmin(
            adminId: (int) $barang->admin_id,
            type: 'barang_temuan_baru',
            title: 'Barang temuan baru',
            message: 'Data barang temuan ditambahkan: '.$barang->nama_barang,
            actionUrl: route(ManagerPortal::routeName('found-items')),
            meta: ['barang_id' => $barang->id]
        );
    }

    private function isUserSubmittedFoundReport(Barang $barang): bool
    {
        return (bool) $barang->user_id
            && $barang->status_laporan === WorkflowStatus::REPORT_SUBMITTED;
    }
}
