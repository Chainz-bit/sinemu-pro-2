<?php

namespace App\Actions\Super\Admins;

use App\Models\Admin;

class RejectAdminAction
{
    /**
     * @return array{key:string,message:string}
     */
    public function execute(Admin $admin, ?string $reason = null, ?int $superAdminId = null): array
    {
        if ($superAdminId !== null && !$this->isOwnedBySuperAdmin($admin, $superAdminId)) {
            return [
                'key' => 'error',
                'message' => 'Admin ini tidak berada dalam cakupan akun super admin Anda.',
            ];
        }

        $admin->update([
            'super_admin_id' => $superAdminId ?? $admin->super_admin_id,
            'status_verifikasi' => 'rejected',
            'alasan_penolakan' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'verified_at' => now(),
        ]);

        return [
            'key' => 'status',
            'message' => 'Pendaftaran admin ditolak.',
        ];
    }

    private function isOwnedBySuperAdmin(Admin $admin, int $superAdminId): bool
    {
        return $admin->super_admin_id === null || (int) $admin->super_admin_id === $superAdminId;
    }
}
