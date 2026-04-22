<?php

namespace App\States\Matching;

class ConfirmedMatchState implements MatchState
{
    public function key(): string
    {
        return 'confirmed';
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
