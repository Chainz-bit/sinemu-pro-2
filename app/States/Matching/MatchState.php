<?php

namespace App\States\Matching;

interface MatchState
{
    public function key(): string;

    public function canConfirm(): bool;

    public function canDismiss(): bool;
}
