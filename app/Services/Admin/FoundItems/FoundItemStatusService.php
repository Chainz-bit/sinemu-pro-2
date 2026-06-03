<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Models\BarangStatusHistory;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;

class FoundItemStatusService
{
    private const LOCKED_STATUS_MESSAGE = 'Status barang tidak dapat diubah karena sudah masuk proses klaim atau belum siap diproses.';

    /**
     * @return array{ok:bool,message:string}
     */
    public function updateStatus(Barang $barang, array $validated): array
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = \App\Support\ManagerPortal::user();

        $oldStatus = strtolower(trim((string) $barang->status_barang));
        $newStatus = strtolower(trim((string) $validated['status_barang']));

        if (!$barang->canHaveStatusUpdatedByAdmin() || !$barang->isAllowedManualStatusTarget($newStatus)) {
            return ['ok' => false, 'message' => self::LOCKED_STATUS_MESSAGE];
        }

        if ($oldStatus === $newStatus) {
            return ['ok' => true, 'message' => 'Tidak ada perubahan status yang disimpan.'];
        }

        $barang->update(['status_barang' => $newStatus]);

        BarangStatusHistory::create([
            'barang_id' => $barang->id,
            'admin_id' => $admin?->id,
            'status_lama' => $oldStatus,
            'status_baru' => $newStatus,
            'catatan' => $validated['catatan_status'] ?? null,
        ]);

        $this->notifyClaimParticipants($barang, $this->resolveStatusLabel($newStatus));

        return ['ok' => true, 'message' => 'Perubahan status berhasil disimpan.'];
    }

    private function notifyClaimParticipants(Barang $barang, string $statusLabel): void
    {
        $barang->klaims()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(function ($userId) use ($barang, $statusLabel) {
                UserNotificationService::notifyUser(
                    userId: (int) $userId,
                    type: 'status_barang_temuan',
                    title: 'Status Barang Temuan Diperbarui',
                    message: ucfirst(\App\Support\RoleLabels::managerLower()) . ' memperbarui status ' . $barang->nama_barang . ' menjadi ' . $statusLabel . '.',
                    actionUrl: route('user.dashboard'),
                    meta: ['barang_id' => $barang->id]
                );
            });
    }

    private function resolveStatusLabel(string $status): string
    {
        return match ($status) {
            WorkflowStatus::FOUND_AVAILABLE => 'Tersedia',
            WorkflowStatus::FOUND_CLAIM_IN_PROGRESS => 'Dalam Proses Klaim',
            WorkflowStatus::FOUND_CLAIMED => 'Sudah Diklaim',
            WorkflowStatus::FOUND_RETURNED => 'Sudah Dikembalikan',
            default => $status,
        };
    }
}
