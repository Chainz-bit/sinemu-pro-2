<?php

namespace App\States\Claims;

class RejectedClaimState implements ClaimState
{
    public function key(): string
    {
        return 'rejected';
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
        return false;
    }
}
