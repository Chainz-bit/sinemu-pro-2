<?php

namespace App\States\Matching;

class ClaimRejectedMatchState implements MatchState
{
    public function key(): string
    {
        return 'claim_rejected';
    }

    public function canConfirm(): bool
    {
        return true;
    }

    public function canDismiss(): bool
    {
        return true;
    }
}
