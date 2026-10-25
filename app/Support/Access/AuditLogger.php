<?php

namespace App\Support\Access;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Writes append-only audit events. Never records passwords, tokens, recovery codes, credentials,
// welfare narratives or IP addresses. Metadata keys that look sensitive are dropped.
class AuditLogger
{
    private const REDACTED_KEY_PARTS = ['password', 'token', 'secret', 'recovery', 'credential', 'narrative', 'description', 'card', 'otp'];

    private const MAX_VALUE_LENGTH = 200;

    public function record(
        string $action,
        ?User $actor = null,
        Model|array|null $subject = null,
        string $outcome = 'success',
        ?string $reason = null,
        array $metadata = [],
        ?Scope $scope = null,
    ): AuditEvent {
        [$subjectType, $subjectId] = $this->subjectParts($subject);

        return AuditEvent::query()->create([
            'actor_user_id' => $actor?->id,
            'actor_staff_profile_id' => $actor?->staff_profile_id,
            'action' => $action,
            'outcome' => $outcome,
            'reason' => $reason,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'scope_type' => $scope?->type,
            'scope_id' => $scope?->id !== null ? (string) $scope->id : null,
            'request_id' => $this->requestId(),
            'metadata' => $this->sanitise($metadata),
            'created_at' => now(),
        ]);
    }

    public function sanitise(array $metadata): array
    {
        $clean = [];
        foreach ($metadata as $key => $value) {
            $lower = strtolower((string) $key);
            foreach (self::REDACTED_KEY_PARTS as $part) {
                if (str_contains($lower, $part)) {
                    continue 2;
                }
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitise($value);
            } elseif (is_string($value)) {
                $clean[$key] = Str::limit($value, self::MAX_VALUE_LENGTH, '...');
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function subjectParts(Model|array|null $subject): array
    {
        if ($subject instanceof Model) {
            return [class_basename($subject), (string) $subject->getKey()];
        }
        if (is_array($subject)) {
            return [isset($subject['type']) ? (string) $subject['type'] : null, isset($subject['id']) ? (string) $subject['id'] : null];
        }

        return [null, null];
    }

    private function requestId(): string
    {
        $request = request();
        if (! $request->attributes->has('gsk_request_id')) {
            $request->attributes->set('gsk_request_id', (string) Str::uuid());
        }

        return (string) $request->attributes->get('gsk_request_id');
    }
}
