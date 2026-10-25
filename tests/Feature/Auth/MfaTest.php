<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Access\ApprovalService;
use App\Support\Access\MfaAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private const PASSWORD = 'test-password-not-for-production';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    /** @return array{0: string, 1: array<int, string>} secret and recovery codes */
    private function startMfa(User $user): array
    {
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $user->refresh();

        return [decrypt($user->two_factor_secret), json_decode(decrypt($user->two_factor_recovery_codes), true)];
    }

    private function challengeSession(User $user): array
    {
        return ['login.id' => $user->getKey(), 'login.remember' => false];
    }

    #[Test]
    public function gsk_085_staff_without_mfa_are_sent_to_setup_and_can_reach_it(): void
    {
        $staff = $this->makeStaff('STF-M1');

        $this->actingAs($staff)->get('/staff')->assertRedirect(route('account.security'));
        $this->get('/account/security')->assertOk();
        $this->getJson('/staff')->assertForbidden();

        $this->startMfa($staff);
        $this->get('/staff')->assertOk();
    }

    #[Test]
    public function gsk_085_a_candidate_is_not_forced_to_enrol(): void
    {
        $candidate = User::factory()->create();

        $this->actingAs($candidate)->get('/account')->assertOk();
    }

    #[Test]
    public function gsk_085_staff_with_mfa_must_pass_the_challenge_after_the_password(): void
    {
        $staff = $this->makeStaff('STF-M2');
        [$secret] = $this->startMfa($staff);

        $this->post('/login', ['email' => $staff->email, 'password' => self::PASSWORD])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $code = (new Google2FA())->getCurrentOtp($secret);
        $this->withSession($this->challengeSession($staff))->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/account');
        $this->assertAuthenticatedAs($staff);
    }

    #[Test]
    public function gsk_086_a_recovery_code_works_once_and_is_not_stored_in_plain_text(): void
    {
        $staff = $this->makeStaff('STF-M3');
        [, $codes] = $this->startMfa($staff);
        $this->assertStringNotContainsString($codes[0], (string) $staff->refresh()->getRawOriginal('two_factor_recovery_codes'));

        $this->withSession($this->challengeSession($staff))->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertRedirect('/account');
        $this->assertAuthenticatedAs($staff);

        $this->post('/logout');
        $this->assertGuest();
        $this->withSession($this->challengeSession($staff))->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    #[Test]
    public function gsk_087_repeated_wrong_codes_are_throttled_and_audited(): void
    {
        $staff = $this->makeStaff('STF-M4');
        $this->startMfa($staff);
        $session = $this->challengeSession($staff);

        for ($i = 0; $i < 5; $i++) {
            $this->withSession($session)->postJson('/two-factor-challenge', ['code' => '000000'])->assertStatus(422);
        }
        $this->withSession($session)->postJson('/two-factor-challenge', ['code' => '000000'])->assertStatus(429);

        $this->assertGuest();
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.mfa_failed')->exists());
    }

    #[Test]
    public function gsk_088_staff_cannot_switch_off_their_own_mfa(): void
    {
        $staff = $this->makeStaff('STF-M5');
        $this->startMfa($staff);

        $this->actingAs($staff)->deleteJson('/user/two-factor-authentication')->assertForbidden();

        $this->assertNotNull($staff->refresh()->two_factor_confirmed_at);
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.mfa_disable_blocked')->where('outcome', 'denied')->exists());
    }

    #[Test]
    public function gsk_088_an_administrator_can_reset_another_persons_mfa_and_the_reset_is_audited(): void
    {
        $admin = $this->makeStaff('STF-M6');
        $this->grant($admin, $this->makeRole('mfa-admin', ['users.reset_mfa']));
        $target = $this->makeStaff('STF-M7');
        $this->startMfa($target);
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $target->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => 'synthetic', 'last_activity' => time(),
        ]);

        app(MfaAdminService::class)->reset($admin, $target, 'Lost device, identity re-checked.');

        $target->refresh();
        $this->assertNull($target->two_factor_confirmed_at);
        $this->assertNull($target->two_factor_secret);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.mfa_reset')->exists());
    }

    #[Test]
    public function gsk_088_mfa_reset_needs_the_permission_a_reason_and_cannot_target_yourself(): void
    {
        $admin = $this->makeStaff('STF-M8');
        $this->grant($admin, $this->makeRole('mfa-admin', ['users.reset_mfa']));
        $nobody = $this->makeStaff('STF-M9');
        $target = $this->makeStaff('STF-M10');
        $service = app(MfaAdminService::class);

        $this->assertDeniedWith('actor_not_authorised', fn () => $service->reset($nobody, $target, 'attempt'));
        $this->assertDeniedWith('reason_required', fn () => $service->reset($admin, $target, ' '));
        $this->assertDeniedWith('self_reset', fn () => $service->reset($admin, $admin, 'self'));
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.mfa_reset_denied')->exists());
    }

    #[Test]
    public function gsk_088_resetting_a_privileged_accounts_mfa_needs_independent_approval_used_once(): void
    {
        $this->enableApprovals();
        $admin = $this->makeStaff('STF-M11');
        $this->grant($admin, $this->makeRole('mfa-admin', ['users.reset_mfa']));
        $approver = $this->makeStaff('STF-M12');
        $this->grant($approver, $this->makeRole('priv-approver', ['roles.approve_privileged_grant']));
        $target = $this->makeStaff('STF-M13');
        $this->grant($target, $this->makeRole('safeguarding-lead', ['welfare.cases.view_all_authorised'], true));
        $service = app(MfaAdminService::class);
        $approvals = app(ApprovalService::class);

        $this->assertDeniedWith('approval_required', fn () => $service->reset($admin, $target, 'Lost device.'));

        $request = $approvals->request($admin, 'mfa.reset_privileged', $target, 'Lost device.', ['target_user_id' => $target->id]);
        $approvals->approve($approver, $request, 'Identity re-checked.');
        $request->refresh();

        $service->reset($admin, $target, 'Lost device.', $request);
        $this->assertNull($target->refresh()->two_factor_confirmed_at);

        $this->assertDeniedWith('approval_already_used', fn () => $service->reset($admin, $target, 'Again.', $request->refresh()));
    }
}
