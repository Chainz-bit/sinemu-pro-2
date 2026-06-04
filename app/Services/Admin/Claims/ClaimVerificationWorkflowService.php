<?php

namespace App\Services\Admin\Claims;

use App\Actions\Claims\ApproveClaimAction;
use App\Actions\Claims\CompleteClaimAction;
use App\Actions\Claims\RejectClaimAction;
use App\Models\Admin;
use App\Models\Klaim;
use App\States\Claims\ClaimStateResolver;
use App\Support\WorkflowStatus;
use Illuminate\Support\Facades\DB;

class ClaimVerificationWorkflowService
{
    public function __construct(
        private readonly ApproveClaimAction $approveClaimAction,
        private readonly RejectClaimAction $rejectClaimAction,
        private readonly CompleteClaimAction $completeClaimAction,
        private readonly ClaimStateResolver $claimStateResolver
    ) {
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function verificationRules(bool $withRejectionReason = false): array
    {
        return [
            'identitas_pelapor_valid' => ['required', 'in:0,1'],
            'detail_barang_valid' => ['required', 'in:0,1'],
            'kronologi_valid' => ['required', 'in:0,1'],
            'bukti_visual_valid' => ['required', 'in:0,1'],
            'kecocokan_data_laporan' => ['required', 'in:0,1'],
            'catatan_verifikasi_admin' => ['nullable', 'string', 'max:2000'],
            'alasan_penolakan' => $withRejectionReason
                ? ['required', 'string', 'max:2000']
                : ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param array<string,mixed> $validated
     */
    public function approve(Klaim $klaim, array $validated, int $adminId): bool
    {
        $klaim = $this->freshClaim($klaim);
        if (!$klaim || !$this->canApprove($klaim) || !$this->canApproveContext($klaim, $adminId)) {
            return false;
        }

        return DB::transaction(fn (): bool => $this->approveClaimAction->execute($klaim, $validated, $adminId));
    }

    /**
     * @param array<string,mixed> $validated
     */
    public function reject(Klaim $klaim, array $validated, int $adminId): bool
    {
        $klaim = $this->freshClaim($klaim);
        if (!$klaim || !$this->canReject($klaim) || !$this->canRejectContext($klaim, $adminId)) {
            return false;
        }

        DB::transaction(function () use ($klaim, $validated, $adminId): void {
            $this->rejectClaimAction->execute($klaim, $validated, $adminId);
        });

        return true;
    }

    public function complete(Klaim $klaim, int $adminId): bool
    {
        $klaim = $this->freshClaim($klaim);
        if (!$klaim || !$this->canComplete($klaim) || !$this->canCompleteContext($klaim, $adminId)) {
            return false;
        }

        DB::transaction(function () use ($klaim, $adminId): void {
            $this->completeClaimAction->execute($klaim, $adminId);
        });

        return true;
    }

    public function canApprove(Klaim $klaim): bool
    {
        return $this->claimStateResolver->resolve($klaim)->canApprove();
    }

    public function canReject(Klaim $klaim): bool
    {
        return $this->claimStateResolver->resolve($klaim)->canReject();
    }

    public function canComplete(Klaim $klaim): bool
    {
        return $this->claimStateResolver->resolve($klaim)->canComplete();
    }

    private function freshClaim(Klaim $klaim): ?Klaim
    {
        return $klaim->fresh(['barang', 'laporanHilang', 'pencocokan']);
    }

    private function canApproveContext(Klaim $klaim, int $adminId): bool
    {
        return $this->hasManageableContext($klaim, $adminId)
            && $this->hasPendingClaimContext($klaim)
            && !$this->hasCompletedClaimForSameItem($klaim);
    }

    private function canRejectContext(Klaim $klaim, int $adminId): bool
    {
        return $this->hasManageableContext($klaim, $adminId)
            && $this->hasPendingClaimContext($klaim)
            && !$this->hasCompletedClaimForSameItem($klaim);
    }

    private function canCompleteContext(Klaim $klaim, int $adminId): bool
    {
        return $this->hasManageableContext($klaim, $adminId)
            && $klaim->barang?->status_barang === WorkflowStatus::FOUND_CLAIMED
            && $klaim->pencocokan?->status_pencocokan === WorkflowStatus::MATCH_CLAIM_APPROVED
            && !$this->hasCompletedClaimForSameItem($klaim);
    }

    private function hasPendingClaimContext(Klaim $klaim): bool
    {
        return $klaim->barang?->status_barang === WorkflowStatus::FOUND_CLAIM_IN_PROGRESS
            && $klaim->barang?->status_laporan === WorkflowStatus::REPORT_MATCHED
            && $klaim->laporanHilang?->status_laporan === WorkflowStatus::REPORT_CLAIMED
            && $klaim->pencocokan?->status_pencocokan === WorkflowStatus::MATCH_CLAIM_IN_PROGRESS;
    }

    private function hasManageableContext(Klaim $klaim, int $adminId): bool
    {
        $admin = Admin::query()->find($adminId);
        if (!$admin?->isActive() || is_null($admin->region_id)) {
            return false;
        }

        if (!is_null($klaim->admin_id) && (int) $klaim->admin_id !== (int) $admin->id) {
            return false;
        }

        $regionIds = [
            $klaim->barang?->region_id,
            $klaim->laporanHilang?->region_id,
        ];

        foreach ($regionIds as $regionId) {
            if (is_null($regionId) || (int) $regionId !== (int) $admin->region_id) {
                return false;
            }
        }

        return $klaim->barang !== null
            && $klaim->laporanHilang !== null
            && $klaim->pencocokan !== null
            && (int) $klaim->pencocokan->barang_id === (int) $klaim->barang->id
            && (int) $klaim->pencocokan->laporan_hilang_id === (int) $klaim->laporanHilang->id
            && (is_null($klaim->pencocokan->admin_id) || (int) $klaim->pencocokan->admin_id === (int) $admin->id);
    }

    private function hasCompletedClaimForSameItem(Klaim $klaim): bool
    {
        if (is_null($klaim->barang_id)) {
            return false;
        }

        return Klaim::query()
            ->whereKeyNot($klaim->id)
            ->where('barang_id', $klaim->barang_id)
            ->where('status_verifikasi', WorkflowStatus::CLAIM_COMPLETED)
            ->exists();
    }
}
