<?php

namespace App\States\Claims;

class PendingClaimState implements ClaimState
{
    public function key(): string
    {
        return 'pending';
    }

    public function canApprove(): bool
    {
        return true;
    }

    public function canReject(): bool
    {
        return true;
    }

    public function canComplete(): bool
    {
        return false;
    }
}
