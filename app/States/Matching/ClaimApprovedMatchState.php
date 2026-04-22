<?php

namespace App\States\Matching;

class ClaimApprovedMatchState implements MatchState
{
    public function key(): string
    {
        return 'claim_approved';
    }

    public function canConfirm(): bool
    {
        return true;
    }

    public function canDismiss(): bool
    {
        return false;
    }
}
