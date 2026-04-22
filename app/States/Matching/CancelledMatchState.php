<?php

namespace App\States\Matching;

class CancelledMatchState implements MatchState
{
    public function key(): string
    {
        return 'cancelled';
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
