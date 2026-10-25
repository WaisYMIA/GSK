<?php

namespace Tests\Feature\Access;

use App\Models\ApprovalDecision;
use App\Models\AuditEvent;
use App\Support\Access\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private const ACTION = 'role.privileged_grant';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function approvals(): ApprovalService
    {
        return app(ApprovalService::class);
    }

    private function approverRole()
    {
        return $this->makeRole('privileged-approver', ['roles.approve_privileged_grant']);
    }

    #[Test]
    public function gsk_091_request_is_blocked_while_approvals_are_disabled(): void
    {
        config(['gsk.approvals.enabled' => false]);
        $requester = $this->makeStaff('STF-P1');
        $approver = $this->makeStaff('STF-P2');
        $this->grant($approver, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');

        $this->assertSame('blocked', $request->status);
        $this->assertDeniedWith('approvals_disabled', fn () => $this->approvals()->approve($approver, $request, 'ok'));
    }

    #[Test]
    public function gsk_091_request_is_blocked_when_no_independent_approver_exists(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P3');
        $this->grant($requester, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');

        $this->assertSame('blocked', $request->status, 'The requester alone must not make an approval possible.');
        $this->assertDeniedWith('request_not_pending', fn () => $this->approvals()->approve($requester, $request, 'self'));
    }

    #[Test]
    public function gsk_091_request_is_blocked_when_the_requester_has_no_staff_profile(): void
    {
        $this->enableApprovals();
        $requester = \App\Models\User::factory()->staff()->create();
        $approver = $this->makeStaff('STF-P4');
        $this->grant($approver, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');

        $this->assertSame('blocked', $request->status);
    }

    #[Test]
    public function gsk_090_requester_cannot_approve_their_own_request(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P5');
        $this->grant($requester, $this->approverRole());
        $other = $this->makeStaff('STF-P6');
        $this->grant($other, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');
        $this->assertSame('pending', $request->status);

        $this->assertDeniedWith('same_person', fn () => $this->approvals()->approve($requester, $request, 'self'));
        $this->assertSame(0, ApprovalDecision::query()->count());
    }

    #[Test]
    public function gsk_092_a_second_account_of_the_same_person_cannot_approve(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P7');
        $secondAccount = $this->makeSecondAccountFor($requester, 'stf-p7-second@gsk.example.test');
        $this->grant($secondAccount, $this->approverRole());
        $independent = $this->makeStaff('STF-P8');
        $this->grant($independent, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');
        $this->assertSame('pending', $request->status);

        $this->assertDeniedWith('same_person', fn () => $this->approvals()->approve($secondAccount, $request, 'second account'));
    }

    #[Test]
    public function gsk_092_a_second_account_does_not_count_as_an_independent_approver(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P9');
        $secondAccount = $this->makeSecondAccountFor($requester, 'stf-p9-second@gsk.example.test');
        $this->grant($secondAccount, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');

        $this->assertSame('blocked', $request->status, 'Only the same person could approve, so the request must be blocked.');
    }

    #[Test]
    public function gsk_097_independent_approver_can_approve_and_the_decision_is_recorded(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P10');
        $approver = $this->makeStaff('STF-P11');
        $this->grant($approver, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');
        $approved = $this->approvals()->approve($approver, $request, 'checked evidence');

        $this->assertSame('approved', $approved->status);
        $this->assertSame(1, ApprovalDecision::query()->where('approval_request_id', $request->id)->count());
        $this->assertTrue(AuditEvent::query()->where('action', 'approval.approved')->exists());
    }

    #[Test]
    public function gsk_097_approver_without_the_permission_is_refused(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P12');
        $approver = $this->makeStaff('STF-P13');
        $this->grant($approver, $this->approverRole());
        $nobody = $this->makeStaff('STF-P14');

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');

        $this->assertDeniedWith('approver_not_authorised', fn () => $this->approvals()->approve($nobody, $request, 'no permission'));
        $this->assertTrue(AuditEvent::query()->where('action', 'approval.decision_denied')->where('outcome', 'denied')->exists());
    }

    #[Test]
    public function gsk_097_an_approver_without_a_staff_profile_cannot_establish_independence(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P15');
        $approver = $this->makeStaff('STF-P16');
        $this->grant($approver, $this->approverRole());
        $profileless = \App\Models\User::factory()->staff()->create();
        $this->grant($profileless, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');

        $this->assertDeniedWith('independence_not_established', fn () => $this->approvals()->approve($profileless, $request, 'no profile'));
    }

    #[Test]
    public function gsk_097_expired_request_cannot_be_approved(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P17');
        $approver = $this->makeStaff('STF-P18');
        $this->grant($approver, $this->approverRole());

        $request = $this->approvals()->request($requester, self::ACTION, null, 'synthetic reason');
        $request->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertDeniedWith('request_expired', fn () => $this->approvals()->approve($approver, $request, 'late'));
        $this->assertSame('expired', $request->refresh()->status);
    }

    #[Test]
    public function an_unknown_action_type_is_rejected(): void
    {
        $requester = $this->makeStaff('STF-P19');

        $this->expectException(\InvalidArgumentException::class);
        $this->approvals()->request($requester, 'made.up.action', null, 'synthetic reason');
    }

    #[Test]
    public function every_configured_action_type_resolves_to_a_permission_key(): void
    {
        $this->enableApprovals();
        $requester = $this->makeStaff('STF-P20');
        $actions = (array) config('gsk.approvals.actions');
        $this->assertNotEmpty($actions);

        foreach (array_keys($actions) as $type) {
            $request = $this->approvals()->request($requester, $type, null, 'synthetic reason');
            $this->assertSame($type, $request->action_type);
        }
    }
}
