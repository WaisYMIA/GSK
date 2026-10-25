<?php

namespace Tests\Feature\Auth;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\Access\AccountLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class InvitationRoutesTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private const PASSWORD = 'A-strong-password-123';

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->hr = $this->makeStaff('STF-I0');
        $this->grant($this->hr, $this->makeRole('account-admin', ['users.invite', 'users.manage']));
    }

    /** @return array{profile: \App\Models\StaffProfile, token: string} */
    private function invite(bool $identityChecked = true): array
    {
        $service = app(AccountLifecycleService::class);
        $result = $service->inviteStaff($this->hr, 'New Person', 'new@gsk.example.test', 'STF-NEW');
        if ($identityChecked) {
            $service->recordIdentityCheck($this->hr, $result['profile'], 'synthetic document check');
        }

        return $result;
    }

    private function payload(string $password = self::PASSWORD): array
    {
        return ['password' => $password, 'password_confirmation' => $password];
    }

    #[Test]
    public function gsk_084_a_valid_invitation_page_opens_and_an_unknown_one_is_not_found(): void
    {
        ['token' => $token] = $this->invite();

        $this->get(route('staff.invitation', ['token' => $token]))->assertOk()->assertSee('New Person');
        $this->get(route('staff.invitation', ['token' => 'not-a-real-token']))->assertNotFound();
    }

    #[Test]
    public function gsk_084_an_expired_invitation_is_not_found(): void
    {
        ['token' => $token] = $this->invite();

        $this->travel(73)->hours();

        $this->get(route('staff.invitation', ['token' => $token]))->assertNotFound();
    }

    #[Test]
    public function gsk_084_accepting_creates_a_verified_staff_account_that_is_then_forced_to_enrol_mfa(): void
    {
        ['token' => $token] = $this->invite();

        $this->post(route('staff.invitation.accept', ['token' => $token]), $this->payload())->assertRedirect(route('login'));

        $user = User::query()->where('email', 'new@gsk.example.test')->firstOrFail();
        $this->assertSame('staff', $user->account_type);
        $this->assertNotNull($user->email_verified_at);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/account');
        $this->get('/staff')->assertRedirect(route('account.security'));
    }

    #[Test]
    public function gsk_084_accepting_before_the_identity_check_is_refused_and_creates_nothing(): void
    {
        ['token' => $token] = $this->invite(false);

        $this->post(route('staff.invitation.accept', ['token' => $token]), $this->payload())->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->where('email', 'new@gsk.example.test')->count());
    }

    #[Test]
    public function gsk_084_a_weak_password_is_refused(): void
    {
        ['token' => $token] = $this->invite();

        $this->post(route('staff.invitation.accept', ['token' => $token]), $this->payload('short'))->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->where('email', 'new@gsk.example.test')->count());
    }

    #[Test]
    public function gsk_084_an_invitation_works_only_once(): void
    {
        ['token' => $token] = $this->invite();
        $this->post(route('staff.invitation.accept', ['token' => $token]), $this->payload());

        $this->post(route('staff.invitation.accept', ['token' => $token]), $this->payload())->assertNotFound();
        $this->assertSame(1, StaffInvitation::query()->whereNotNull('accepted_at')->count());
    }
}
