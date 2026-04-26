<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Auth;

class FoundItemVerificationService
{
    public function verify(Barang $barang, array $validated): void
    {
        $newStatus = $validated['status_laporan'] === 'approved'
            ? WorkflowStatus::REPORT_APPROVED
            : WorkflowStatus::REPORT_REJECTED;

        $barang->update([
            'status_laporan' => $newStatus,
            'verified_by_admin_id' => (int) Auth::guard('admin')->id(),
            'verified_at' => now(),
            'tampil_di_home' => $newStatus === WorkflowStatus::REPORT_APPROVED,
        ]);

        if (!is_null($barang->user_id)) {
            $label = $newStatus === WorkflowStatus::REPORT_APPROVED ? 'disetujui' : 'ditolak';
            UserNotificationService::notifyUser(
                userId: (int) $barang->user_id,
                type: 'verifikasi_laporan_temuan',
                title: 'Verifikasi Laporan Temuan',
                message: 'Laporan barang temuan "' . $barang->nama_barang . '" ' . $label . ' admin.',
                actionUrl: route('user.dashboard'),
                meta: ['barang_id' => $barang->id]
            );
        }
    }
}
