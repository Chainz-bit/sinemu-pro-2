<?php

namespace App\Actions\Claims;

use App\Models\Klaim;

class CompleteClaimAction
{
    public function __construct(
        private readonly HandoverClaimAction $handoverClaimAction = new HandoverClaimAction()
    ) {
    }

    /**
     * @param array<string,mixed> $handoverData
     */
    public function execute(Klaim $klaim, int $adminId, array $handoverData = []): void
    {
        $this->handoverClaimAction->execute($klaim, $adminId, $handoverData);
    }
}
