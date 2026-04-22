<?php

namespace App\States\Claims;

class CompletedClaimState implements ClaimState
{
    public function key(): string
    {
        return 'completed';
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
