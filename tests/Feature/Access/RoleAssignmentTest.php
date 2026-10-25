<?php

namespace Tests\Feature\Access;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\ApprovalService;
use App\Support\Access\RoleAssignmentService;
use App\Support\Access\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private User $admin;
    private Role $viewer;
    private Role $privileged;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->admin = $this->makeStaff('STF-R0');
        $this->grant($this->admin, $this->makeRole('role-admin', ['roles.assign']));
        $this->viewer = $this->makeRole('viewer', ['audit.view']);
        $this->privileged = $this->makeRole('safeguarding-lead', ['welfare.cases.view_all_authorised'], true);
    }

    private function service(): RoleAssignmentService
    {
        return app(RoleAssignmentService::class);
    }

    private function insertSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => 'synthetic', 'last_activity' => time(),
        ]);
    }

    #[Test]
    public function gsk_033_assignment_is_audited_and_changes_access_and_revokes_sessions(): void
    {
        $target = $this->makeStaff('STF-R1');
        $this->insertSession($target);

        $assignment = $this->service()->assign($this->admin, $target, $this->viewer, null, null, 'synthetic onboarding');

        $this->assertTrue(app(AccessService::class)->can($target, 'audit.view'));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count(), 'GSK-089: sessions must be revoked on role change.');
        $event = AuditEvent::query()->where('action', 'role.assigned')->first();
        $this->assertNotNull($event);
        $this->assertSame($this->admin->id, $event->actor_user_id);
        $this->assertSame((string) $assignment->id, $event->subject_id);
    }

    #[Test]
    public function sec_07_actor_without_roles_assign_is_refused_and_the_attempt_is_audited(): void
    {
        $nobody = $this->makeStaff('STF-R2');
        $target = $this->makeStaff('STF-R3');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->assign($nobody, $target, $this->viewer, null, null, 'attempt'));
        $this->assertTrue(AuditEvent::query()->where('action', 'role.assign_denied')->where('outcome', 'denied')->exists());
    }

    #[Test]
    public function sec_07_nobody_can_assign_a_role_to_themselves_or_to_their_own_second_account(): void
    {
        $this->assertDeniedWith('self_assignment', fn () => $this->service()->assign($this->admin, $this->admin, $this->viewer, null, null, 'self'));

        $second = $this->makeSecondAccountFor($this->admin, 'stf-r0-second@gsk.example.test');
        $this->assertDeniedWith('self_assignment', fn () => $this->service()->assign($this->admin, $second, $this->viewer, null, null, 'second account'));
    }

    #[Test]
    public function gsk_084_staff_role_cannot_go_to_an_account_without_an_active_staff_profile(): void
    {
        $candidate = User::factory()->create();
        $invited = $this->makeStaff('STF-R4', true, 'invited');

        $this->assertDeniedWith('target_not_active_staff', fn () => $this->service()->assign($this->admin, $candidate, $this->viewer, null, null, 'x'));
        $this->assertDeniedWith('target_not_active_staff', fn () => $this->service()->assign($this->admin, $invited, $this->viewer, null, null, 'x'));
    }

    #[Test]
    public function a_reason_is_required(): void
    {
        $target = $this->makeStaff('STF-R5');

        $this->assertDeniedWith('reason_required', fn () => $this->service()->assign($this->admin, $target, $this->viewer, null, null, '   '));
    }

    #[Test]
    public function gsk_097_privileged_role_needs_an_approved_unused_matching_request(): void
    {
        $this->enableApprovals();
        $target = $this->makeStaff('STF-R6');
        $approver = $this->makeStaff('STF-R7');
        $this->grant($approver, $this->makeRole('priv-approver', ['roles.approve_privileged_grant']));
        $approvals = app(ApprovalService::class);

        $this->assertDeniedWith('approval_required', fn () => $this->service()->assign($this->admin, $target, $this->privileged, null, null, 'no approval'));

        $payload = ['target_user_id' => $target->id, 'role_id' => $this->privileged->id];
        $request = $approvals->request($this->admin, 'role.privileged_grant', $target, 'synthetic grant', $payload);
        $this->assertDeniedWith('approval_not_valid', fn () => $this->service()->assign($this->admin, $target, $this->privileged, null, null, 'still pending', $request));

        $approvals->approve($approver, $request, 'approved');
        $request->refresh();
        $assignment = $this->service()->assign($this->admin, $target, $this->privileged, Scope::global(), null, 'approved grant', $request);
        $this->assertNotNull($assignment->id);

        $this->assertDeniedWith('approval_already_used', fn () => $this->service()->assign($this->admin, $target, $this->privileged, null, null, 'reuse', $request->refresh()));
    }

    #[Test]
    public function gsk_097_approval_must_match_the_target_and_the_role(): void
    {
        $this->enableApprovals();
        $target = $this->makeStaff('STF-R8');
        $someoneElse = $this->makeStaff('STF-R9');
        $approver = $this->makeStaff('STF-R10');
        $this->grant($approver, $this->makeRole('priv-approver', ['roles.approve_privileged_grant']));
        $approvals = app(ApprovalService::class);

        $request = $approvals->request($this->admin, 'role.privileged_grant', $target, 'synthetic grant', [
            'target_user_id' => $target->id, 'role_id' => $this->privileged->id,
        ]);
        $approvals->approve($approver, $request, 'approved');
        $request->refresh();

        $this->assertDeniedWith('approval_does_not_match_grant', fn () => $this->service()->assign($this->admin, $someoneElse, $this->privileged, null, null, 'wrong target', $request));
    }

    #[Test]
    public function gsk_096_revocation_removes_access_immediately_and_is_audited(): void
    {
        $target = $this->makeStaff('STF-R11');
        $assignment = $this->service()->assign($this->admin, $target, $this->viewer, null, null, 'synthetic');
        $this->insertSession($target);
        $this->assertTrue(app(AccessService::class)->can($target, 'audit.view'));

        $this->service()->revoke($this->admin, $assignment, 'synthetic end of duty');

        $this->assertFalse(app(AccessService::class)->can($target->refresh(), 'audit.view'));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertTrue(AuditEvent::query()->where('action', 'role.revoked')->exists());
    }

    #[Test]
    public function revoke_requires_the_permission(): void
    {
        $target = $this->makeStaff('STF-R12');
        $assignment = $this->service()->assign($this->admin, $target, $this->viewer, null, null, 'synthetic');
        $nobody = $this->makeStaff('STF-R13');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->revoke($nobody, $assignment, 'attempt'));
    }
}
