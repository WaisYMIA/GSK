<?php

namespace Tests\Feature\Access;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\BreakGlassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class BreakGlassTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    private const REASON = 'Synthetic emergency: restore access after an outage test.';

    private User $engineer;
    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->engineer = $this->makeStaff('STF-B1');
        $this->grant($this->engineer, $this->makeRole('break-glass-user', ['access.break_glass.activate']));
        $this->reviewer = $this->makeStaff('STF-B2');
        $this->grant($this->reviewer, $this->makeRole('break-glass-reviewer', ['access.break_glass.review']));
    }

    private function service(): BreakGlassService
    {
        return app(BreakGlassService::class);
    }

    #[Test]
    public function gsk_093_activation_grants_only_the_listed_keys_then_expires(): void
    {
        $grant = $this->service()->activate($this->engineer, ['audit.view'], self::REASON, 30);
        $access = app(AccessService::class);

        $this->assertSame('pending', $grant->review_status);
        $this->assertTrue($access->can($this->engineer, 'audit.view'));
        $this->assertFalse($access->can($this->engineer, 'users.manage'));
        $this->assertTrue(AuditEvent::query()->where('action', 'break_glass.activated')->exists());

        $this->travel(31)->minutes();
        $this->assertFalse($access->can($this->engineer, 'audit.view'));
    }

    #[Test]
    public function gsk_093_activation_needs_the_permission_a_real_reason_a_short_duration_and_an_allowed_key(): void
    {
        $nobody = $this->makeStaff('STF-B3');
        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->activate($nobody, ['audit.view'], self::REASON, 30));
        $this->assertDeniedWith('reason_too_short', fn () => $this->service()->activate($this->engineer, ['audit.view'], 'urgent', 30));
        $this->assertDeniedWith('duration_not_allowed', fn () => $this->service()->activate($this->engineer, ['audit.view'], self::REASON, 600));
        $this->assertDeniedWith('duration_not_allowed', fn () => $this->service()->activate($this->engineer, ['audit.view'], self::REASON, 0));
        $this->assertDeniedWith('no_permissions_requested', fn () => $this->service()->activate($this->engineer, [], self::REASON, 30));
    }

    #[Test]
    public function gsk_081_break_glass_cannot_open_welfare_or_anything_outside_the_allow_list(): void
    {
        $this->assertDeniedWith('permission_not_allowed_for_break_glass', fn () => $this->service()->activate(
            $this->engineer, ['welfare.cases.view_all_authorised'], self::REASON, 30,
        ));
        $this->assertDeniedWith('permission_not_allowed_for_break_glass', fn () => $this->service()->activate(
            $this->engineer, ['roles.assign'], self::REASON, 30,
        ));
        $this->assertTrue(AuditEvent::query()->where('action', 'break_glass.activation_denied')->where('outcome', 'denied')->exists());
    }

    #[Test]
    public function an_undelivered_alert_is_recorded_as_a_failure_not_as_sent(): void
    {
        config(['gsk.break_glass.alert_recipients' => []]);
        $this->service()->activate($this->engineer, ['audit.view'], self::REASON, 30);

        $event = AuditEvent::query()->where('action', 'break_glass.alert_not_delivered')->first();
        $this->assertNotNull($event);
        $this->assertSame('failure', $event->outcome);
        $this->assertFalse(AuditEvent::query()->where('action', 'break_glass.alert_queued')->exists());
    }

    #[Test]
    public function review_must_come_from_a_different_person_and_only_after_the_grant_ends(): void
    {
        $grant = $this->service()->activate($this->engineer, ['audit.view'], self::REASON, 30);

        $this->assertDeniedWith('grant_still_active', fn () => $this->service()->review($this->reviewer, $grant, 'too early'));

        $this->service()->end($grant, $this->engineer);
        $selfReviewer = $this->makeStaff('STF-B4');
        $this->grant($selfReviewer, $this->makeRole('second-reviewer', ['access.break_glass.review']));

        $this->grant($this->engineer, $this->makeRole('engineer-also-reviews', ['access.break_glass.review']));
        $this->assertDeniedWith('reviewer_not_independent', fn () => $this->service()->review($this->engineer, $grant->refresh(), 'self review'));

        $second = $this->makeSecondAccountFor($this->engineer, 'stf-b1-second@gsk.example.test');
        $this->grant($second, $this->makeRole('second-account-reviewer', ['access.break_glass.review']));
        $this->assertDeniedWith('reviewer_not_independent', fn () => $this->service()->review($second, $grant->refresh(), 'same person'));

        $reviewed = $this->service()->review($this->reviewer, $grant->refresh(), 'Reviewed: use was justified.');
        $this->assertSame('reviewed', $reviewed->review_status);
        $this->assertDeniedWith('already_reviewed', fn () => $this->service()->review($selfReviewer, $reviewed, 'again'));
    }

    #[Test]
    public function review_needs_its_own_permission_and_notes(): void
    {
        $grant = $this->service()->activate($this->engineer, ['audit.view'], self::REASON, 30);
        $this->service()->end($grant, $this->engineer);
        $nobody = $this->makeStaff('STF-B5');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->service()->review($nobody, $grant->refresh(), 'x'));
        $this->assertDeniedWith('notes_required', fn () => $this->service()->review($this->reviewer, $grant->refresh(), '  '));
    }
}
