<?php

namespace App\States\Claims;

class ApprovedClaimState implements ClaimState
{
    public function key(): string
    {
        return 'approved';
    }

    public function canApprove(): bool
    {
        return false;
    }

    public function canReject(): bool
    {
        return false;
    }

    public function canComplete(): bool
    {
        return true;
    }
}
