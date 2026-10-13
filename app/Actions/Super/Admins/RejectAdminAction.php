<?php

namespace App\Actions\Super\Admins;

use App\Models\Admin;
use App\Support\RoleLabels;

class RejectAdminAction
{
    /**
     * @return array{key:string,message:string}
     */
    public function execute(Admin $admin, ?string $reason = null, ?int $superAdminId = null): array
    {
        $managerRoleLabelLower = RoleLabels::managerLower();

        if ($superAdminId !== null && ! $this->isOwnedBySuperAdmin($admin, $superAdminId)) {
            return [
                'key' => 'error',
                'message' => ucfirst($managerRoleLabelLower).' ini tidak berada dalam cakupan akun super admin Anda.',
            ];
        }

        if (! in_array($admin->status_verifikasi, [null, 'pending'], true)) {
            return [
                'key' => 'error',
                'message' => 'Hanya akun '.$managerRoleLabelLower.' yang masih menunggu verifikasi yang dapat ditolak.',
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
            'message' => 'Pendaftaran '.$managerRoleLabelLower.' ditolak.',
        ];
    }

    private function isOwnedBySuperAdmin(Admin $admin, int $superAdminId): bool
    {
        return $admin->super_admin_id === null || (int) $admin->super_admin_id === $superAdminId;
    }
}
