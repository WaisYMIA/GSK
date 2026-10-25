<?php

namespace App\Support\Access;

use App\Models\ApprovalRequest;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// An administrator clears another person's two-factor setup so they can enrol again.
// Needs users.reset_mfa, a reason, and (for accounts holding a privileged role) an approved, unused, matching request.
class MfaAdminService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly AuditLogger $audit,
        private readonly SessionRevoker $sessions,
    ) {
    }

    public function reset(User $actor, User $target, string $reason, ?ApprovalRequest $approval = null): User
    {
        try {
            $this->assertAllowed($actor, $target, $reason, $approval);
        } catch (AccessDenied $e) {
            $this->audit->record('auth.mfa_reset_denied', $actor, $target, 'denied', $reason, ['reason_code' => $e->reasonCode]);
            throw $e;
        }

        return DB::transaction(function () use ($actor, $target, $reason, $approval) {
            if ($approval !== null && $this->holdsPrivilegedRole($target)) {
                $approval->forceFill(['consumed_at' => now()])->save();
            }
            $target->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
            $this->sessions->revokeAll($target);
            $this->audit->record('auth.mfa_reset', $actor, $target, 'success', $reason, ['approval_request_id' => $approval?->id]);

            return $target->refresh();
        });
    }

    private function assertAllowed(User $actor, User $target, string $reason, ?ApprovalRequest $approval): void
    {
        if (! $this->access->can($actor, 'users.reset_mfa')) {
            throw new AccessDenied('actor_not_authorised');
        }
        if (trim($reason) === '') {
            throw new AccessDenied('reason_required');
        }
        if ($actor->id === $target->id
            || ($actor->staff_profile_id !== null && $actor->staff_profile_id === $target->staff_profile_id)) {
            throw new AccessDenied('self_reset');
        }
        if ($this->holdsPrivilegedRole($target)) {
            if ($approval === null) {
                throw new AccessDenied('approval_required');
            }
            if ($approval->action_type !== 'mfa.reset_privileged' || $approval->status !== 'approved') {
                throw new AccessDenied('approval_not_valid');
            }
            if ($approval->consumed_at !== null) {
                throw new AccessDenied('approval_already_used');
            }
            if ($approval->requested_by !== $actor->id || (($approval->payload ?? [])['target_user_id'] ?? null) !== $target->id) {
                throw new AccessDenied('approval_does_not_match_reset');
            }
        }
    }

    private function holdsPrivilegedRole(User $user): bool
    {
        return RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('role', function ($q) {
                $q->where('is_privileged', true);
            })
            ->exists();
    }
}
