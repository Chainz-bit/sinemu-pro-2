<?php

namespace App\States\Claims;

use App\Models\Klaim;
use App\Support\WorkflowStatus;

class ClaimStateResolver
{
    public function resolve(Klaim $klaim): ClaimState
    {
        $verificationStatus = (string) ($klaim->status_verifikasi ?? '');

        return match ($verificationStatus) {
            WorkflowStatus::CLAIM_COMPLETED => new CompletedClaimState(),
            WorkflowStatus::CLAIM_APPROVED => new ApprovedClaimState(),
            WorkflowStatus::CLAIM_REJECTED => new RejectedClaimState(),
            default => match ((string) $klaim->status_klaim) {
                WorkflowStatus::CLAIM_LEGACY_APPROVED => new ApprovedClaimState(),
                WorkflowStatus::CLAIM_LEGACY_REJECTED => new RejectedClaimState(),
                default => new PendingClaimState(),
            },
        };
    }
}
