<?php

namespace App\States\Matching;

class UnmatchedPairState implements MatchState
{
    public function key(): string
    {
        return 'unmatched';
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
