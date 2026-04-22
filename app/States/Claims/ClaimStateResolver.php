<?php

namespace App\States\Claims;

use App\Models\Klaim;
use App\Support\WorkflowStatus;

class ClaimStateResolver
{
    public function resolve(Klaim $klaim): ClaimState
    {
        if ((string) ($klaim->status_verifikasi ?? '') === WorkflowStatus::CLAIM_COMPLETED) {
            return new CompletedClaimState();
        }

        return match ((string) $klaim->status_klaim) {
            'disetujui' => new ApprovedClaimState(),
            'ditolak' => new RejectedClaimState(),
            default => new PendingClaimState(),
        };
    }
}
