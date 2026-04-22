<?php

namespace App\States\Matching;

class ClaimInProgressMatchState implements MatchState
{
    public function key(): string
    {
        return 'claim_in_progress';
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
