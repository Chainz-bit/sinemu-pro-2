<?php

namespace App\Services\Super\Admins;

use App\Actions\Super\Admins\AcceptAdminAction;
use App\Actions\Super\Admins\DeactivateAdminAction;
use App\Actions\Super\Admins\RejectAdminAction;
use App\Models\Admin;

class AdminApprovalService
{
    public function __construct(
        private readonly AcceptAdminAction $acceptAdminAction,
        private readonly RejectAdminAction $rejectAdminAction,
        private readonly DeactivateAdminAction $deactivateAdminAction
    ) {
    }

    /**
     * @return array{key:string,message:string}
     */
    public function accept(Admin $admin, ?int $superAdminId = null): array
    {
        return $this->acceptAdminAction->execute($admin, $superAdminId);
    }

    /**
     * @return array{key:string,message:string}
     */
    public function reject(Admin $admin, ?string $reason = null, ?int $superAdminId = null): array
    {
        return $this->rejectAdminAction->execute($admin, $reason, $superAdminId);
    }

    /**
     * @return array{key:string,message:string}
     */
    public function deactivate(Admin $admin, ?int $superAdminId = null): array
    {
        return $this->deactivateAdminAction->execute($admin, $superAdminId);
    }
}
