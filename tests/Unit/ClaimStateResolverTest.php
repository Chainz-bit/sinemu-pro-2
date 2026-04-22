<?php

namespace Tests\Unit;

use App\Models\Klaim;
use App\States\Claims\ApprovedClaimState;
use App\States\Claims\ClaimStateResolver;
use App\States\Claims\CompletedClaimState;
use App\States\Claims\PendingClaimState;
use App\States\Claims\RejectedClaimState;
use App\Support\WorkflowStatus;
use Tests\TestCase;

class ClaimStateResolverTest extends TestCase
{
    public function test_resolves_pending_claim_state_from_pending_status(): void
    {
        $resolver = new ClaimStateResolver();
        $klaim = new Klaim([
            'status_klaim' => 'pending',
            'status_verifikasi' => WorkflowStatus::CLAIM_UNDER_REVIEW,
        ]);

        $state = $resolver->resolve($klaim);

        $this->assertInstanceOf(PendingClaimState::class, $state);
        $this->assertTrue($state->canApprove());
        $this->assertTrue($state->canReject());
        $this->assertFalse($state->canComplete());
    }

    public function test_resolves_approved_claim_state_from_approved_status(): void
    {
        $resolver = new ClaimStateResolver();
        $klaim = new Klaim([
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_APPROVED,
        ]);

        $state = $resolver->resolve($klaim);

        $this->assertInstanceOf(ApprovedClaimState::class, $state);
        $this->assertFalse($state->canApprove());
        $this->assertFalse($state->canReject());
        $this->assertTrue($state->canComplete());
    }

    public function test_resolves_rejected_claim_state_from_rejected_status(): void
    {
        $resolver = new ClaimStateResolver();
        $klaim = new Klaim([
            'status_klaim' => 'ditolak',
            'status_verifikasi' => WorkflowStatus::CLAIM_REJECTED,
        ]);

        $state = $resolver->resolve($klaim);

        $this->assertInstanceOf(RejectedClaimState::class, $state);
        $this->assertFalse($state->canApprove());
        $this->assertFalse($state->canReject());
        $this->assertFalse($state->canComplete());
    }

    public function test_resolves_completed_claim_state_from_completed_verification_status(): void
    {
        $resolver = new ClaimStateResolver();
        $klaim = new Klaim([
            'status_klaim' => 'disetujui',
            'status_verifikasi' => WorkflowStatus::CLAIM_COMPLETED,
        ]);

        $state = $resolver->resolve($klaim);

        $this->assertInstanceOf(CompletedClaimState::class, $state);
        $this->assertFalse($state->canApprove());
        $this->assertFalse($state->canReject());
        $this->assertFalse($state->canComplete());
    }
}
