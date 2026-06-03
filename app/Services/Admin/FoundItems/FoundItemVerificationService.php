<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;

class FoundItemVerificationService
{
    /**
     * @return array{ok:bool,message:string}
     */
    public function verify(Barang $barang, array $validated): array
    {
        $newStatus = $validated['status_laporan'] === 'approved'
            ? WorkflowStatus::REPORT_APPROVED
            : WorkflowStatus::REPORT_REJECTED;
        $oldStatus = (string) ($barang->status_laporan ?? '');

        if (!$barang->canBeVerifiedByAdmin()) {
            return ['ok' => false, 'message' => 'Barang temuan ini tidak dapat diverifikasi ulang karena sudah diproses.'];
        }

        if ($oldStatus === $newStatus) {
            return ['ok' => true, 'message' => 'Status laporan tidak berubah.'];
        }

        $barang->update([
            'status_laporan' => $newStatus,
            'verified_by_admin_id' => (int) \App\Support\ManagerPortal::id(),
            'verified_at' => now(),
            'tampil_di_home' => $newStatus === WorkflowStatus::REPORT_APPROVED,
        ]);

        if (!is_null($barang->user_id)) {
            $label = $newStatus === WorkflowStatus::REPORT_APPROVED ? 'disetujui' : 'ditolak';
            UserNotificationService::notifyUser(
                userId: (int) $barang->user_id,
                type: 'verifikasi_laporan_temuan',
                title: 'Verifikasi Laporan Temuan',
                message: 'Laporan barang temuan "' . $barang->nama_barang . '" ' . $label . ' ' . \App\Support\RoleLabels::managerLower() . '.',
                actionUrl: route('user.dashboard'),
                meta: ['barang_id' => $barang->id]
            );
        }

        return ['ok' => true, 'message' => 'Verifikasi laporan barang temuan berhasil diperbarui.'];
    }
}
