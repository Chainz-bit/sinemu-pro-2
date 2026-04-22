<?php

namespace App\States\Matching;

class CompletedMatchState implements MatchState
{
    public function key(): string
    {
        return 'completed';
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
