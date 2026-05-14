<?php

namespace App\Actions\Super\Admins;

use App\Models\Admin;

class DeactivateAdminAction
{
    /**
     * @return array{key:string,message:string}
     */
    public function execute(Admin $admin, ?int $superAdminId = null): array
    {
        $managerRoleLabelLower = \App\Support\RoleLabels::managerLower();

        if ($superAdminId !== null && !$this->isOwnedBySuperAdmin($admin, $superAdminId)) {
            return [
                'key' => 'error',
                'message' => ucfirst($managerRoleLabelLower) . ' ini tidak berada dalam cakupan akun super admin Anda.',
            ];
        }

        if ($admin->status_verifikasi !== 'active') {
            return [
                'key' => 'error',
                'message' => 'Hanya akun ' . $managerRoleLabelLower . ' aktif yang dapat dinonaktifkan.',
            ];
        }

        $admin->update([
            'super_admin_id' => $superAdminId ?? $admin->super_admin_id,
            'status_verifikasi' => 'inactive',
            'alasan_penolakan' => null,
        ]);

        return [
            'key' => 'status',
            'message' => 'Akun ' . $managerRoleLabelLower . ' berhasil dinonaktifkan.',
        ];
    }

    private function isOwnedBySuperAdmin(Admin $admin, int $superAdminId): bool
    {
        return $admin->super_admin_id === null || (int) $admin->super_admin_id === $superAdminId;
    }
}
