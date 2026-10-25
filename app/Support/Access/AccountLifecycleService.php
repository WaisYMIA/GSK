<?php

namespace App\Support\Access;

use App\Models\StaffInvitation;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Staff accounts are invitation-only, tied to one staff profile per real person, and cannot be activated
// until an identity check has been recorded by someone other than the person being checked.
class AccountLifecycleService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly AuditLogger $audit,
        private readonly SessionRevoker $sessions,
        private readonly BreakGlassService $breakGlass,
    ) {
    }

    /** @return array{profile: StaffProfile, token: string} The plain token is returned once, for delivery by email. */
    public function inviteStaff(User $actor, string $fullName, string $workEmail, string $personRef): array
    {
        $this->requirePermission($actor, 'users.invite', 'staff.invite_denied');

        $workEmail = strtolower(trim($workEmail));
        $duplicate = StaffProfile::query()
            ->where('work_email', $workEmail)
            ->orWhere('person_ref', $personRef)
            ->exists();
        if ($duplicate) {
            $this->audit->record('staff.invite_denied', $actor, null, 'denied', null, ['reason_code' => 'duplicate_identity']);
            throw new AccessDenied('duplicate_identity');
        }

        $token = Str::random(64);

        return DB::transaction(function () use ($actor, $fullName, $workEmail, $personRef, $token) {
            $profile = StaffProfile::query()->create([
                'person_ref' => $personRef, 'full_name' => $fullName, 'work_email' => $workEmail, 'status' => 'invited',
            ]);
            StaffInvitation::query()->create([
                'staff_profile_id' => $profile->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours((int) config('gsk.invitations.expiry_hours', 72)),
                'invited_by' => $actor->id,
            ]);
            $this->audit->record('staff.invited', $actor, $profile, 'success', null, ['person_ref' => $personRef]);

            return ['profile' => $profile, 'token' => $token];
        });
    }

    public function recordIdentityCheck(User $actor, StaffProfile $profile, string $method): StaffProfile
    {
        $this->requirePermission($actor, 'users.manage', 'staff.identity_check_denied');

        if ($actor->staff_profile_id !== null && $actor->staff_profile_id === $profile->id) {
            $this->audit->record('staff.identity_check_denied', $actor, $profile, 'denied', null, ['reason_code' => 'own_identity']);
            throw new AccessDenied('own_identity');
        }
        if (trim($method) === '') {
            throw new AccessDenied('method_required');
        }

        $profile->forceFill(['identity_checked_at' => now(), 'identity_checked_by' => $actor->id])->save();
        $this->audit->record('staff.identity_checked', $actor, $profile, 'success', $method);

        return $profile->refresh();
    }

    public function acceptInvitation(string $token, string $password): User
    {
        $invitation = StaffInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        if ($invitation === null || $invitation->accepted_at !== null || $invitation->expires_at->isPast()) {
            $this->audit->record('staff.invitation_rejected', null, null, 'denied', null, ['reason_code' => 'invalid_or_expired']);
            throw new AccessDenied('invitation_invalid_or_expired');
        }

        $profile = $invitation->staffProfile;
        if ($profile->identity_checked_at === null) {
            $this->audit->record('staff.invitation_rejected', null, $profile, 'denied', null, ['reason_code' => 'identity_not_verified']);
            throw new AccessDenied('identity_not_verified');
        }
        if (mb_strlen($password) < (int) config('gsk.invitations.min_password_length', 12)) {
            throw new AccessDenied('weak_password');
        }
        if (User::withTrashed()->where('email', $profile->work_email)->exists()) {
            throw new AccessDenied('account_exists');
        }

        return DB::transaction(function () use ($invitation, $profile, $password) {
            $user = new User([
                'name' => $profile->full_name,
                'email' => $profile->work_email,
                'password' => $password,
                'account_type' => 'staff',
                'status' => 'active',
            ]);
            $user->email_verified_at = now();
            $user->staff_profile_id = $profile->id;
            $user->save();

            $profile->forceFill(['status' => 'active'])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();
            $this->audit->record('staff.invitation_accepted', $user, $profile, 'success');

            return $user;
        });
    }

    public function suspend(User $actor, User $target, string $reason): User
    {
        $this->requirePermission($actor, 'users.suspend', 'account.suspend_denied');
        if ($actor->id === $target->id) {
            $this->audit->record('account.suspend_denied', $actor, $target, 'denied', $reason, ['reason_code' => 'self_suspension']);
            throw new AccessDenied('self_suspension');
        }
        if (trim($reason) === '') {
            throw new AccessDenied('reason_required');
        }

        return DB::transaction(function () use ($actor, $target, $reason) {
            $target->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
            $revoked = $this->sessions->revokeAll($target);
            $this->breakGlass->endAllFor($target);
            $this->audit->record('account.suspended', $actor, $target, 'success', $reason, ['sessions_revoked' => $revoked]);

            return $target->refresh();
        });
    }

    public function reactivate(User $actor, User $target, string $reason): User
    {
        $this->requirePermission($actor, 'users.suspend', 'account.reactivate_denied');
        if ($actor->id === $target->id) {
            throw new AccessDenied('self_reactivation');
        }
        if (trim($reason) === '') {
            throw new AccessDenied('reason_required');
        }

        $target->forceFill(['status' => 'active', 'suspended_at' => null])->save();
        $this->audit->record('account.reactivated', $actor, $target, 'success', $reason);

        return $target->refresh();
    }

    public function revokeSessions(User $actor, User $target): int
    {
        $this->requirePermission($actor, 'users.revoke_sessions', 'sessions.revoke_denied');
        $revoked = $this->sessions->revokeAll($target);
        $this->audit->record('sessions.revoked', $actor, $target, 'success', null, ['sessions_revoked' => $revoked]);

        return $revoked;
    }

    private function requirePermission(User $actor, string $permission, string $auditAction): void
    {
        if (! $this->access->can($actor, $permission)) {
            $this->audit->record($auditAction, $actor, null, 'denied', null, ['reason_code' => 'actor_not_authorised', 'permission' => $permission]);
            throw new AccessDenied('actor_not_authorised');
        }
    }
}
