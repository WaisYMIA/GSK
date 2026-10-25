<?php

namespace Tests\Feature\Access;

use App\Models\AuditEvent;
use App\Support\Access\AuditLogger;
use App\Support\Access\Scope;
use App\Support\Access\SensitiveAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    #[Test]
    public function sec_12_audit_events_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $event = app(AuditLogger::class)->record('test.event');

        try {
            $event->forceFill(['action' => 'tampered'])->save();
            $this->fail('An update to an audit event must be refused.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $event->delete();
            $this->fail('A delete of an audit event must be refused.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame('test.event', $event->fresh()->action);
    }

    #[Test]
    public function gsk_094_events_record_actor_person_scope_outcome_reason_and_request_id(): void
    {
        $actor = $this->makeStaff('STF-U1');
        $event = app(AuditLogger::class)->record('example.action', $actor, null, 'success', 'synthetic reason', ['k' => 'v'], Scope::programme(7));

        $this->assertSame($actor->id, $event->actor_user_id);
        $this->assertSame($actor->staff_profile_id, $event->actor_staff_profile_id);
        $this->assertSame('programme', $event->scope_type);
        $this->assertSame('7', $event->scope_id);
        $this->assertSame('synthetic reason', $event->reason);
        $this->assertNotEmpty($event->request_id);
        $this->assertNull($event->ip_address, 'IP addresses are not stored.');
    }

    #[Test]
    public function gsk_094_sensitive_looking_metadata_is_dropped_and_long_values_truncated(): void
    {
        $event = app(AuditLogger::class)->record('example.redaction', null, null, 'success', null, [
            'password' => 'hunter2',
            'api_token' => 'abc',
            'recovery_codes' => ['one', 'two'],
            'report_description' => 'full narrative text',
            'nested' => ['credential' => 'secret', 'safe' => 'kept'],
            'note' => str_repeat('a', 500),
            'count' => 3,
        ]);
        $metadata = $event->fresh()->metadata;

        foreach (['password', 'api_token', 'recovery_codes', 'report_description'] as $dropped) {
            $this->assertArrayNotHasKey($dropped, $metadata);
        }
        $this->assertSame('kept', $metadata['nested']['safe']);
        $this->assertArrayNotHasKey('credential', $metadata['nested']);
        $this->assertLessThanOrEqual(203, strlen($metadata['note']));
        $this->assertSame(3, $metadata['count']);
    }

    #[Test]
    public function gsk_095_sensitive_reads_are_audited_whether_allowed_or_denied_and_need_a_reason(): void
    {
        $case = $this->makeCase();
        $lead = $this->makeStaff('STF-U2');
        $this->grant($lead, $this->makeRole('safeguarding-lead', ['welfare.cases.view_all_authorised']));
        $other = $this->makeStaff('STF-U3');
        $access = app(SensitiveAccess::class);

        $this->assertTrue($access->authorizeRead($lead, 'view', $case, 'Supervision review'));
        $this->assertFalse($access->authorizeRead($other, 'view', $case, 'Curiosity'));

        $allowed = AuditEvent::query()->where('action', 'access.sensitive_read')->where('outcome', 'success')->first();
        $denied = AuditEvent::query()->where('action', 'access.sensitive_read')->where('outcome', 'denied')->first();
        $this->assertNotNull($allowed);
        $this->assertNotNull($denied);
        $this->assertSame('Supervision review', $allowed->reason);
        $this->assertSame('WelfareCase', $allowed->subject_type);
        $this->assertStringNotContainsString('Synthetic test narrative', json_encode($allowed->metadata));
    }

    #[Test]
    public function sensitive_reads_without_a_reason_are_refused(): void
    {
        $case = $this->makeCase();
        $lead = $this->makeStaff('STF-U4');

        $this->expectException(InvalidArgumentException::class);
        app(SensitiveAccess::class)->authorizeRead($lead, 'view', $case, '   ');
    }
}
