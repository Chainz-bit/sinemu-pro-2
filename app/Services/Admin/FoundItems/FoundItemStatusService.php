<?php

namespace App\Services\Admin\FoundItems;

use App\Models\Barang;
use App\Models\BarangStatusHistory;
use App\Services\UserNotificationService;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class FoundItemStatusService
{
    /**
     * @return array{ok:bool,message:string}
     */
    public function updateStatus(Barang $barang, array $validated): array
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $oldStatus = (string) $barang->status_barang;
        $newStatus = (string) $validated['status_barang'];
        $latestClaim = $barang->klaims()->latest('updated_at')->first();
        $latestClaimVerificationStatus = Schema::hasColumn('klaims', 'status_verifikasi')
            ? (string) ($latestClaim?->status_verifikasi ?? '')
            : '';
        $latestClaimLegacyStatus = (string) ($latestClaim?->status_klaim ?? '');

        if ($newStatus === WorkflowStatus::FOUND_CLAIMED && !$this->canMarkClaimed($latestClaimVerificationStatus, $latestClaimLegacyStatus)) {
            return ['ok' => false, 'message' => 'Status "Sudah Diklaim" hanya bisa dipilih setelah klaim disetujui.'];
        }

        if ($newStatus === WorkflowStatus::FOUND_RETURNED && !$this->canMarkReturned($latestClaimVerificationStatus, $latestClaimLegacyStatus, $oldStatus)) {
            return ['ok' => false, 'message' => 'Status "Selesai" hanya bisa dipilih setelah klaim ditandai selesai pada Verifikasi Klaim.'];
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

    private function canMarkClaimed(string $latestClaimVerificationStatus, string $latestClaimLegacyStatus): bool
    {
        return Schema::hasColumn('klaims', 'status_verifikasi')
            ? in_array($latestClaimVerificationStatus, [WorkflowStatus::CLAIM_APPROVED, WorkflowStatus::CLAIM_COMPLETED], true)
            : $latestClaimLegacyStatus === WorkflowStatus::CLAIM_LEGACY_APPROVED;
    }

    private function canMarkReturned(string $latestClaimVerificationStatus, string $latestClaimLegacyStatus, string $oldStatus): bool
    {
        return Schema::hasColumn('klaims', 'status_verifikasi')
            ? $latestClaimVerificationStatus === WorkflowStatus::CLAIM_COMPLETED
            : ($latestClaimLegacyStatus === WorkflowStatus::CLAIM_LEGACY_APPROVED && $oldStatus === WorkflowStatus::FOUND_CLAIMED);
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
                    message: 'Admin memperbarui status ' . $barang->nama_barang . ' menjadi ' . $statusLabel . '.',
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
