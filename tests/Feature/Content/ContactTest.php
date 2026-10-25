<?php

namespace Tests\Feature\Content;

use App\Models\AuditEvent;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Person', 'email' => 'person@example.test', 'topic' => 'General', 'message' => 'This is a synthetic test message.',
        ], $overrides);
    }

    #[Test]
    public function gsk_148_a_valid_message_is_stored_audited_and_keeps_no_ip_address(): void
    {
        Notification::fake();

        $this->post(route('contact.store'), $this->payload())->assertRedirect(route('contact'))->assertSessionHas('status');

        $this->assertSame(1, ContactMessage::query()->count());
        $this->assertFalse(Schema::hasColumn('contact_messages', 'ip_address'));
        $event = AuditEvent::query()->where('action', 'contact.received')->firstOrFail();
        $this->assertStringNotContainsString('person@example.test', json_encode($event->metadata));
        $this->assertNull($event->actor_user_id);
    }

    #[Test]
    public function gsk_148_the_honeypot_gets_the_same_answer_but_nothing_is_stored_or_sent(): void
    {
        Notification::fake();
        $this->staffWith('CT0', ['contact.messages.view']);

        $this->post(route('contact.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertRedirect(route('contact'))->assertSessionHas('status');

        $this->assertSame(0, ContactMessage::query()->count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function gsk_148_invalid_input_is_refused(): void
    {
        $this->post(route('contact.store'), $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post(route('contact.store'), $this->payload(['message' => 'short']))->assertSessionHasErrors('message');
        $this->post(route('contact.store'), $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post(route('contact.store'), $this->payload(['message' => str_repeat('a', 3001)]))->assertSessionHasErrors('message');
        $this->assertSame(0, ContactMessage::query()->count());
    }

    #[Test]
    public function gsk_148_floods_are_throttled(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), $this->payload())->assertRedirect(route('contact'));
        }
        $this->post(route('contact.store'), $this->payload())->assertStatus(429);
        $this->assertSame(5, ContactMessage::query()->count());
    }

    #[Test]
    public function only_staff_who_may_view_contact_messages_and_the_shared_mailbox_are_notified(): void
    {
        Notification::fake();
        config(['gsk.contact.pool_email' => 'info@example.test']);
        $viewer = $this->staffWith('CT1', ['contact.messages.view']);
        $other = $this->staffWith('CT2', ['content.pages.view']);

        $this->post(route('contact.store'), $this->payload());

        Notification::assertSentTo($viewer, ContactMessageReceived::class);
        Notification::assertNotSentTo($other, ContactMessageReceived::class);
        Notification::assertSentOnDemand(ContactMessageReceived::class, fn ($n, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'info@example.test');
    }

    #[Test]
    public function by_default_the_email_carries_no_message_text_and_the_option_can_include_it(): void
    {
        $this->assertFalse(config('gsk.contact.include_message_body'));
        $viewer = $this->staffWith('CT3', ['contact.messages.view']);

        $neutral = (new ContactMessageReceived(7, null))->toMail($viewer);
        $this->assertStringNotContainsString('synthetic', implode(' ', $neutral->introLines));
        $this->assertSame(url('/staff/contact-messages/7'), $neutral->actionUrl);

        $full = (new ContactMessageReceived(7, ['name' => 'Test Person', 'email' => 'person@example.test', 'topic' => null, 'message' => 'A synthetic body.']))->toMail($viewer);
        $this->assertStringContainsString('A synthetic body.', implode(' ', $full->introLines));
    }

    #[Test]
    public function a_mail_failure_does_not_lose_the_message_or_break_the_page(): void
    {
        $this->staffWith('CT4', ['contact.messages.view']);
        $thrower = new class () {
            public function send(...$arguments)
            {
                throw new \RuntimeException('mail is down');
            }

            public function sendNow(...$arguments)
            {
                throw new \RuntimeException('mail is down');
            }
        };
        $this->app->instance(ChannelManager::class, $thrower);
        Notification::clearResolvedInstances();

        $this->post(route('contact.store'), $this->payload())->assertRedirect(route('contact'))->assertSessionHas('status');

        $this->assertSame(1, ContactMessage::query()->count());
    }

    #[Test]
    public function staff_need_the_permission_to_read_messages_and_every_read_is_audited(): void
    {
        $message = ContactMessage::query()->create(['name' => 'A', 'email' => 'a@example.test', 'message' => 'Synthetic message text.']);
        $this->get(route('staff.contact.index'))->assertRedirect(route('login'));

        $nobody = $this->staffWith('CT5', ['content.pages.view']);
        $this->actingAs($nobody)->get(route('staff.contact.index'))->assertForbidden();
        $this->get(route('staff.contact.show', $message->id))->assertForbidden();

        $viewer = $this->staffWith('CT6', ['contact.messages.view']);
        $this->actingAs($viewer)->get(route('staff.contact.index'))->assertOk();
        $this->get(route('staff.contact.show', $message->id))->assertOk()->assertSee('Synthetic message text.');
        $this->assertSame('read', $message->refresh()->status);
        $this->assertTrue(AuditEvent::query()->where('action', 'contact.message_read')->where('actor_user_id', $viewer->id)->exists());
    }

    #[Test]
    public function sec_02_script_in_a_contact_message_is_shown_escaped_to_staff(): void
    {
        $payload = '<script>alert(1)</script>';
        $message = ContactMessage::query()->create(['name' => $payload, 'email' => 'a@example.test', 'topic' => $payload, 'message' => 'Synthetic message with ' . $payload]);
        $viewer = $this->staffWith('CT7', ['contact.messages.view']);

        $response = $this->actingAs($viewer)->get(route('staff.contact.show', $message->id))->assertOk();

        $response->assertDontSee($payload, false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
