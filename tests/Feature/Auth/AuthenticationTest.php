<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private const PASSWORD = 'test-password-not-for-production';

    #[Test]
    public function gsk_131_anonymous_visitors_are_sent_to_sign_in_for_private_pages(): void
    {
        foreach (['/account', '/account/security', '/staff'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    #[Test]
    public function gsk_061_a_candidate_can_sign_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->get('/account')->assertOk();

        $this->post('/logout');
        $this->assertGuest();
    }

    #[Test]
    public function gsk_061_sign_in_and_sign_out_are_audited_and_record_the_last_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/logout');

        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.login')->where('actor_user_id', $user->id)->exists());
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.logout')->exists());
    }

    #[Test]
    public function gsk_094_a_failed_sign_in_is_audited_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'a-wrong-password-value'])->assertStatus(422);

        $event = AuditEvent::query()->where('action', 'auth.login_failed')->first();
        $this->assertNotNull($event);
        $this->assertSame('failure', $event->outcome);
        $this->assertStringNotContainsString('a-wrong-password-value', json_encode($event->metadata));
        $this->assertStringNotContainsString($user->email, json_encode($event->metadata));
    }

    #[Test]
    public function sec_05_repeated_sign_in_attempts_are_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(429);
        $this->assertGuest();
    }

    #[Test]
    public function gsk_089_a_suspended_account_cannot_sign_in_and_gets_the_same_failure_as_a_wrong_password(): void
    {
        $suspended = User::factory()->suspended()->create();

        $response = $this->postJson('/login', ['email' => $suspended->email, 'password' => self::PASSWORD]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    #[Test]
    public function gsk_089_an_account_suspended_mid_session_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(AuditEvent::query()->where('action', 'auth.inactive_session_ended')->exists());
    }

    #[Test]
    public function gsk_010_a_candidate_cannot_open_the_staff_area(): void
    {
        $candidate = User::factory()->create();

        $this->actingAs($candidate)->get('/staff')->assertForbidden();
    }

    #[Test]
    public function an_unverified_account_is_sent_to_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get('/account')->assertRedirect(route('verification.notice'));
    }
}
