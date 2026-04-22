<?php

namespace App\Services\Admin\Claims;

use App\Actions\Claims\ApproveClaimAction;
use App\Actions\Claims\CompleteClaimAction;
use App\Actions\Claims\RejectClaimAction;
use App\Models\Klaim;
use App\States\Claims\ClaimStateResolver;

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
        if (!$this->canApprove($klaim)) {
            return false;
        }

        return $this->approveClaimAction->execute($klaim, $validated, $adminId);
    }

    /**
     * @param array<string,mixed> $validated
     */
    public function reject(Klaim $klaim, array $validated, int $adminId): void
    {
        $this->rejectClaimAction->execute($klaim, $validated, $adminId);
    }

    public function complete(Klaim $klaim, int $adminId): void
    {
        $this->completeClaimAction->execute($klaim, $adminId);
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
}
