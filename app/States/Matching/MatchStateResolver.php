<?php

namespace App\States\Matching;

use App\Models\Pencocokan;
use App\Support\WorkflowStatus;

class MatchStateResolver
{
    public function resolve(?Pencocokan $pencocokan): MatchState
    {
        if ($pencocokan === null) {
            return new UnmatchedPairState();
        }

        return match ((string) $pencocokan->status_pencocokan) {
            WorkflowStatus::MATCH_CONFIRMED => new ConfirmedMatchState(),
            WorkflowStatus::MATCH_CLAIM_IN_PROGRESS => new ClaimInProgressMatchState(),
            WorkflowStatus::MATCH_CLAIM_APPROVED => new ClaimApprovedMatchState(),
            WorkflowStatus::MATCH_CLAIM_REJECTED => new ClaimRejectedMatchState(),
            WorkflowStatus::MATCH_COMPLETED => new CompletedMatchState(),
            WorkflowStatus::MATCH_CANCELLED => new CancelledMatchState(),
            default => new UnmatchedPairState(),
        };
    }
}
