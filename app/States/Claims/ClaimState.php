<?php

namespace App\States\Claims;

interface ClaimState
{
    public function key(): string;

    public function canApprove(): bool;

    public function canReject(): bool;

    public function canComplete(): bool;
}
