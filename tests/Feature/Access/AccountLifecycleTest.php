<?php

namespace Tests\Feature\Access;

use App\Models\AuditEvent;
use App\Models\BreakGlassGrant;
use App\Models\StaffInvitation;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\AccountLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->hr = $this->makeStaff('STF-L0');
        $this->grant($this->hr, $this->makeRole('account-admin', ['users.invite', 'users.manage', 'users.suspend', 'users.revoke_sessions']));
    }

    private function service(): AccountLifecycleService
    {
        return app(AccountLifecycleService::class);
    }

    private function insertSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => 'synthetic', 'last_activity' => time(),
        ]);
    }

    #[Test]
    public function gsk_084_inviting_staff_needs_the_permission(): void
    {
        $nobody = $this->makeStaff('STF-L1');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->inviteStaff($nobody, 'New Person', 'new@gsk.example.test', 'STF-N1'));
        $this->assertSame(0, StaffProfile::query()->where('person_ref', 'STF-N1')->count());
    }

    #[Test]
    public function gsk_084_duplicate_staff_identity_is_refused(): void
    {
        $this->service()->inviteStaff($this->hr, 'New Person', 'new@gsk.example.test', 'STF-N2');

        $this->assertDeniedWith('duplicate_identity', fn () => $this->service()->inviteStaff($this->hr, 'Same Email', 'NEW@gsk.example.test', 'STF-N3'));
        $this->assertDeniedWith('duplicate_identity', fn () => $this->service()->inviteStaff($this->hr, 'Same Ref', 'other@gsk.example.test', 'STF-N2'));
    }

    #[Test]
    public function gsk_084_invitation_cannot_be_accepted_until_an_independent_identity_check_is_recorded(): void
    {
        ['profile' => $profile, 'token' => $token] = $this->service()->inviteStaff($this->hr, 'New Person', 'new@gsk.example.test', 'STF-N4');

        $this->assertDeniedWith('identity_not_verified', fn () => $this->service()->acceptInvitation($token, 'a-long-synthetic-password'));

        $this->service()->recordIdentityCheck($this->hr, $profile, 'synthetic document check');
        $user = $this->service()->acceptInvitation($token, 'a-long-synthetic-password');

        $this->assertSame('staff', $user->account_type);
        $this->assertSame($profile->id, $user->staff_profile_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('active', $profile->refresh()->status);
    }

    #[Test]
    public function gsk_084_nobody_can_record_their_own_identity_check(): void
    {
        $this->assertDeniedWith('own_identity', fn () => $this->service()->recordIdentityCheck($this->hr, $this->hr->staffProfile, 'self check'));
    }

    #[Test]
    public function gsk_084_token_is_single_use_stored_hashed_and_expires(): void
    {
        ['profile' => $profile, 'token' => $token] = $this->service()->inviteStaff($this->hr, 'New Person', 'new@gsk.example.test', 'STF-N5');
        $this->service()->recordIdentityCheck($this->hr, $profile, 'synthetic document check');

        $this->assertFalse(StaffInvitation::query()->where('token_hash', $token)->exists(), 'The plain token must not be stored.');
        $this->assertTrue(StaffInvitation::query()->where('token_hash', hash('sha256', $token))->exists());

        $this->assertDeniedWith('weak_password', fn () => $this->service()->acceptInvitation($token, 'short'));
        $this->service()->acceptInvitation($token, 'a-long-synthetic-password');
        $this->assertDeniedWith('invitation_invalid_or_expired', fn () => $this->service()->acceptInvitation($token, 'a-long-synthetic-password'));
    }

    #[Test]
    public function gsk_084_expired_invitation_is_refused(): void
    {
        ['profile' => $profile, 'token' => $token] = $this->service()->inviteStaff($this->hr, 'New Person', 'new@gsk.example.test', 'STF-N6');
        $this->service()->recordIdentityCheck($this->hr, $profile, 'synthetic document check');

        $this->travel(73)->hours();

        $this->assertDeniedWith('invitation_invalid_or_expired', fn () => $this->service()->acceptInvitation($token, 'a-long-synthetic-password'));
    }

    #[Test]
    public function gsk_089_suspension_revokes_sessions_ends_break_glass_and_removes_access(): void
    {
        $target = $this->makeStaff('STF-L2');
        $this->grant($target, $this->makeRole('auditor', ['audit.view']));
        $this->insertSession($target);
        BreakGlassGrant::query()->create([
            'user_id' => $target->id, 'staff_profile_id' => $target->staff_profile_id, 'reason' => 'synthetic emergency test reason',
            'permission_keys' => ['settings.manage'], 'activated_at' => now(), 'expires_at' => now()->addMinutes(30),
        ]);

        $this->service()->suspend($this->hr, $target, 'synthetic suspension');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertFalse(app(AccessService::class)->can($target->refresh(), 'audit.view'));
        $this->assertFalse(BreakGlassGrant::query()->where('user_id', $target->id)->activeNow()->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'account.suspended')->exists());
    }

    #[Test]
    public function suspension_needs_the_permission_a_reason_and_cannot_be_self_inflicted(): void
    {
        $target = $this->makeStaff('STF-L3');
        $nobody = $this->makeStaff('STF-L4');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->suspend($nobody, $target, 'attempt'));
        $this->assertDeniedWith('self_suspension', fn () => $this->service()->suspend($this->hr, $this->hr, 'self'));
        $this->assertDeniedWith('reason_required', fn () => $this->service()->suspend($this->hr, $target, ' '));
    }

    #[Test]
    public function reactivation_restores_the_account_but_not_its_sessions(): void
    {
        $target = $this->makeStaff('STF-L5');
        $this->service()->suspend($this->hr, $target, 'synthetic suspension');

        $this->service()->reactivate($this->hr, $target->refresh(), 'synthetic review complete');

        $this->assertTrue($target->refresh()->isActive());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    #[Test]
    public function revoking_sessions_needs_its_own_permission(): void
    {
        $target = $this->makeStaff('STF-L6');
        $this->insertSession($target);
        $nobody = $this->makeStaff('STF-L7');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->revokeSessions($nobody, $target));
        $this->assertSame(1, $this->service()->revokeSessions($this->hr, $target));
    }
}
