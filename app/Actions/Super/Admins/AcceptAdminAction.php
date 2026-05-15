<?php

namespace App\Actions\Super\Admins;

use App\Models\Admin;

class AcceptAdminAction
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

        if ($admin->status_verifikasi === 'active') {
            return [
                'key' => 'error',
                'message' => 'Akun ' . $managerRoleLabelLower . ' sudah aktif.',
            ];
        }

        if (empty($admin->region_id)) {
            return [
                'key' => 'error',
                'message' => ucfirst($managerRoleLabelLower) . ' harus memiliki wilayah sebelum diverifikasi.',
            ];
        }

        $admin->update([
            'super_admin_id' => $superAdminId ?? $admin->super_admin_id,
            'status_verifikasi' => 'active',
            'alasan_penolakan' => null,
            'verified_at' => now(),
        ]);

        return [
            'key' => 'status',
            'message' => ucfirst($managerRoleLabelLower) . ' berhasil diverifikasi dan diaktifkan.',
        ];
    }

    private function isOwnedBySuperAdmin(Admin $admin, int $superAdminId): bool
    {
        return $admin->super_admin_id === null || (int) $admin->super_admin_id === $superAdminId;
    }
}
