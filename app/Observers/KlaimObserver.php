<?php

namespace App\Observers;

use App\Models\Klaim;
use App\Services\AdminNotificationService;

class KlaimObserver
{
    public function created(Klaim $klaim): void
    {
        $adminId = $klaim->admin_id ?? $klaim->barang?->admin_id;
        if (!empty($adminId)) {
            AdminNotificationService::notifyAdmin(
                adminId: (int) $adminId,
                type: 'klaim_baru',
                title: 'Klaim baru',
                message: 'Ada pengajuan klaim baru untuk diverifikasi.',
                actionUrl: route(\App\Support\ManagerPortal::routeName('claim-verifications')),
                meta: ['klaim_id' => $klaim->id]
            );
        }
    }
}
