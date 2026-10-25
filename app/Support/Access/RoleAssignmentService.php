<?php

namespace App\Support\Access;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly AuditLogger $audit,
        private readonly SessionRevoker $sessions,
    ) {
    }

    public function assign(
        User $actor,
        User $target,
        Role $role,
        ?Scope $scope,
        ?CarbonInterface $expiresAt,
        string $reason,
        ?ApprovalRequest $approval = null,
    ): RoleAssignment {
        $scope ??= Scope::global();

        try {
            $this->assertCanAssign($actor, $target, $role, $reason, $approval);
        } catch (AccessDenied $e) {
            $this->audit->record('role.assign_denied', $actor, $target, 'denied', $reason, [
                'role' => $role->key, 'reason_code' => $e->reasonCode,
            ], $scope);
            throw $e;
        }

        return DB::transaction(function () use ($actor, $target, $role, $scope, $expiresAt, $reason, $approval) {
            if ($approval !== null && $role->is_privileged) {
                $approval->forceFill(['consumed_at' => now()])->save();
            }
            $assignment = RoleAssignment::query()->create([
                'user_id' => $target->id,
                'role_id' => $role->id,
                'scope_type' => $scope->type,
                'scope_id' => $scope->id,
                'granted_by' => $actor->id,
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ]);
            $this->audit->record('role.assigned', $actor, $assignment, 'success', $reason, [
                'target_user_id' => $target->id, 'role' => $role->key, 'privileged' => $role->is_privileged,
                'approval_request_id' => $approval?->id,
            ], $scope);
            $this->sessions->revokeAll($target);

            return $assignment;
        });
    }

    public function revoke(User $actor, RoleAssignment $assignment, string $reason): RoleAssignment
    {
        if (! $this->access->can($actor, 'roles.assign')) {
            $this->audit->record('role.revoke_denied', $actor, $assignment, 'denied', $reason, ['reason_code' => 'actor_not_authorised']);
            throw new AccessDenied('actor_not_authorised');
        }
        if (trim($reason) === '') {
            throw new AccessDenied('reason_required');
        }

        return DB::transaction(function () use ($actor, $assignment, $reason) {
            $assignment->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            $this->audit->record('role.revoked', $actor, $assignment, 'success', $reason, ['target_user_id' => $assignment->user_id]);
            $target = User::query()->find($assignment->user_id);
            if ($target !== null) {
                $this->sessions->revokeAll($target);
            }

            return $assignment->refresh();
        });
    }

    private function assertCanAssign(User $actor, User $target, Role $role, string $reason, ?ApprovalRequest $approval): void
    {
        if (trim($reason) === '') {
            throw new AccessDenied('reason_required');
        }
        if (! $this->access->can($actor, 'roles.assign')) {
            throw new AccessDenied('actor_not_authorised');
        }
        if ($actor->id === $target->id
            || ($actor->staff_profile_id !== null && $actor->staff_profile_id === $target->staff_profile_id)) {
            throw new AccessDenied('self_assignment');
        }
        if (! $target->isActive()) {
            throw new AccessDenied('target_inactive');
        }
        if ($role->requires_staff_profile) {
            $profile = $target->staffProfile;
            if ($profile === null || ! $profile->isActive()) {
                throw new AccessDenied('target_not_active_staff');
            }
        }
        if ($role->is_privileged) {
            $this->assertApprovalCovers($approval, $actor, $target, $role);
        }
    }

    private function assertApprovalCovers(?ApprovalRequest $approval, User $actor, User $target, Role $role): void
    {
        if ($approval === null) {
            throw new AccessDenied('approval_required');
        }
        if ($approval->action_type !== 'role.privileged_grant' || $approval->status !== 'approved') {
            throw new AccessDenied('approval_not_valid');
        }
        if ($approval->consumed_at !== null) {
            throw new AccessDenied('approval_already_used');
        }
        if ($approval->requested_by !== $actor->id) {
            throw new AccessDenied('approval_belongs_to_another_request');
        }
        $payload = $approval->payload ?? [];
        if (($payload['target_user_id'] ?? null) !== $target->id || ($payload['role_id'] ?? null) !== $role->id) {
            throw new AccessDenied('approval_does_not_match_grant');
        }
    }
}
