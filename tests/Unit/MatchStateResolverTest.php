<?php

namespace Tests\Unit;

use App\Models\Pencocokan;
use App\States\Matching\CancelledMatchState;
use App\States\Matching\ClaimApprovedMatchState;
use App\States\Matching\ClaimInProgressMatchState;
use App\States\Matching\ClaimRejectedMatchState;
use App\States\Matching\CompletedMatchState;
use App\States\Matching\ConfirmedMatchState;
use App\States\Matching\MatchStateResolver;
use App\States\Matching\UnmatchedPairState;
use App\Support\WorkflowStatus;
use Tests\TestCase;

class MatchStateResolverTest extends TestCase
{
    public function test_resolves_unmatched_state_for_missing_pair(): void
    {
        $state = (new MatchStateResolver())->resolve(null);

        $this->assertInstanceOf(UnmatchedPairState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertTrue($state->canDismiss());
    }

    public function test_resolves_confirmed_match_state(): void
    {
        $state = (new MatchStateResolver())->resolve(new Pencocokan([
            'status_pencocokan' => WorkflowStatus::MATCH_CONFIRMED,
        ]));

        $this->assertInstanceOf(ConfirmedMatchState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertFalse($state->canDismiss());
    }

    public function test_resolves_claim_in_progress_match_state(): void
    {
        $state = (new MatchStateResolver())->resolve(new Pencocokan([
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_IN_PROGRESS,
        ]));

        $this->assertInstanceOf(ClaimInProgressMatchState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertFalse($state->canDismiss());
    }

    public function test_resolves_claim_approved_match_state(): void
    {
        $state = (new MatchStateResolver())->resolve(new Pencocokan([
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_APPROVED,
        ]));

        $this->assertInstanceOf(ClaimApprovedMatchState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertFalse($state->canDismiss());
    }

    public function test_resolves_claim_rejected_match_state(): void
    {
        $state = (new MatchStateResolver())->resolve(new Pencocokan([
            'status_pencocokan' => WorkflowStatus::MATCH_CLAIM_REJECTED,
        ]));

        $this->assertInstanceOf(ClaimRejectedMatchState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertTrue($state->canDismiss());
    }

    public function test_resolves_completed_match_state(): void
    {
        $state = (new MatchStateResolver())->resolve(new Pencocokan([
            'status_pencocokan' => WorkflowStatus::MATCH_COMPLETED,
        ]));

        $this->assertInstanceOf(CompletedMatchState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertTrue($state->canDismiss());
    }

    public function test_resolves_cancelled_match_state(): void
    {
        $state = (new MatchStateResolver())->resolve(new Pencocokan([
            'status_pencocokan' => WorkflowStatus::MATCH_CANCELLED,
        ]));

        $this->assertInstanceOf(CancelledMatchState::class, $state);
        $this->assertTrue($state->canConfirm());
        $this->assertTrue($state->canDismiss());
    }
}
