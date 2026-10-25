<?php

namespace App\Support\Access;

use App\Models\BreakGlassGrant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

// Emergency access is explicit, reasoned, time-limited, restricted to a short allow-list of permissions,
// and reviewed afterwards by a different person. It is never a general admin shortcut.
class BreakGlassService
{
    public function __construct(private readonly AccessService $access, private readonly AuditLogger $audit)
    {
    }

    public function activate(User $user, array $permissionKeys, string $reason, int $minutes): BreakGlassGrant
    {
        try {
            $this->assertCanActivate($user, $permissionKeys, $reason, $minutes);
        } catch (AccessDenied $e) {
            $this->audit->record('break_glass.activation_denied', $user, null, 'denied', $reason, ['reason_code' => $e->reasonCode]);
            throw $e;
        }

        $grant = BreakGlassGrant::query()->create([
            'user_id' => $user->id,
            'staff_profile_id' => $user->staff_profile_id,
            'reason' => $reason,
            'permission_keys' => array_values($permissionKeys),
            'activated_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
            'review_status' => 'pending',
        ]);
        $this->audit->record('break_glass.activated', $user, $grant, 'success', $reason, [
            'permission_keys' => array_values($permissionKeys), 'minutes' => $minutes,
        ]);
        $this->alert($grant);

        return $grant;
    }

    public function end(BreakGlassGrant $grant, ?User $actor = null): void
    {
        if ($grant->ended_at === null) {
            $grant->forceFill(['ended_at' => now()])->save();
            $this->audit->record('break_glass.ended', $actor, $grant);
        }
    }

    public function endAllFor(User $user): void
    {
        $grants = BreakGlassGrant::query()->where('user_id', $user->id)->activeNow()->get();
        foreach ($grants as $grant) {
            $this->end($grant);
        }
    }

    public function review(User $reviewer, BreakGlassGrant $grant, string $notes): BreakGlassGrant
    {
        try {
            $this->assertCanReview($reviewer, $grant, $notes);
        } catch (AccessDenied $e) {
            $this->audit->record('break_glass.review_denied', $reviewer, $grant, 'denied', $notes, ['reason_code' => $e->reasonCode]);
            throw $e;
        }

        $grant->forceFill([
            'review_status' => 'reviewed',
            'reviewed_by' => $reviewer->id,
            'reviewer_staff_profile_id' => $reviewer->staff_profile_id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();
        $this->audit->record('break_glass.reviewed', $reviewer, $grant, 'success', $notes);

        return $grant->refresh();
    }

    private function assertCanActivate(User $user, array $keys, string $reason, int $minutes): void
    {
        if (! $this->access->can($user, 'access.break_glass.activate')) {
            throw new AccessDenied('actor_not_authorised');
        }
        if ($user->staff_profile_id === null) {
            throw new AccessDenied('no_staff_profile');
        }
        if (mb_strlen(trim($reason)) < (int) config('gsk.break_glass.min_reason_length', 20)) {
            throw new AccessDenied('reason_too_short');
        }
        if ($minutes < 1 || $minutes > (int) config('gsk.break_glass.max_minutes', 120)) {
            throw new AccessDenied('duration_not_allowed');
        }
        if ($keys === []) {
            throw new AccessDenied('no_permissions_requested');
        }
        $allowed = (array) config('gsk.break_glass.allowed_permissions', []);
        foreach ($keys as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new AccessDenied('permission_not_allowed_for_break_glass');
            }
        }
    }

    private function assertCanReview(User $reviewer, BreakGlassGrant $grant, string $notes): void
    {
        if (! $this->access->can($reviewer, 'access.break_glass.review')) {
            throw new AccessDenied('actor_not_authorised');
        }
        if ($reviewer->staff_profile_id === null || $reviewer->staff_profile_id === $grant->staff_profile_id
            || $reviewer->id === $grant->user_id) {
            throw new AccessDenied('reviewer_not_independent');
        }
        if ($grant->isActiveNow()) {
            throw new AccessDenied('grant_still_active');
        }
        if ($grant->review_status === 'reviewed') {
            throw new AccessDenied('already_reviewed');
        }
        if (trim($notes) === '') {
            throw new AccessDenied('notes_required');
        }
    }

    // Alert recipients are an open decision. With none configured the alert is recorded as undelivered, never as sent.
    private function alert(BreakGlassGrant $grant): void
    {
        $recipients = (array) config('gsk.break_glass.alert_recipients', []);
        if ($recipients === []) {
            $this->audit->record('break_glass.alert_not_delivered', null, $grant, 'failure', 'no_alert_recipients_configured');
            Log::warning('GSK break-glass activated and no alert recipients are configured.', ['grant_id' => $grant->id]);

            return;
        }
        Log::warning('GSK break-glass activated.', ['grant_id' => $grant->id, 'recipients' => count($recipients)]);
        $this->audit->record('break_glass.alert_queued', null, $grant, 'success', null, ['recipient_count' => count($recipients)]);
    }
}
