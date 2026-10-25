<?php

namespace Tests\Feature\Auth;

use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'A-new-Strong-pass-123';

    private function resetPayload(string $token, string $email, string $password = self::NEW_PASSWORD): array
    {
        return ['token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password];
    }

    #[Test]
    public function gsk_062_the_reset_form_answers_the_same_for_known_and_unknown_addresses(): void
    {
        Notification::fake();
        $known = User::factory()->create();

        $a = $this->post('/forgot-password', ['email' => $known->email]);
        $b = $this->post('/forgot-password', ['email' => 'nobody-here@example.test']);

        $a->assertSessionHas('status', GenericPasswordResetLinkResponse::MESSAGE)->assertSessionHasNoErrors();
        $b->assertSessionHas('status', GenericPasswordResetLinkResponse::MESSAGE)->assertSessionHasNoErrors();
        Notification::assertSentToTimes($known, ResetPassword::class, 1);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    #[Test]
    public function gsk_008_a_valid_token_resets_the_password_revokes_sessions_and_works_only_once(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => 'synthetic', 'last_activity' => time(),
        ]);

        $this->post('/reset-password', $this->resetPayload($token, $user->email))->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());

        $this->post('/reset-password', $this->resetPayload($token, $user->email))->assertSessionHasErrors('email');
    }

    #[Test]
    public function gsk_008_an_expired_token_is_refused(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();

        $this->post('/reset-password', $this->resetPayload($token, $user->email))->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
    }

    #[Test]
    public function gsk_008_a_weak_new_password_is_refused(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', $this->resetPayload($token, $user->email, 'short'))->assertSessionHasErrors('password');
    }
}
