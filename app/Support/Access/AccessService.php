<?php

namespace App\Support\Access;

use App\Models\BreakGlassGrant;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\PartnerProgrammeScope;
use App\Models\Permission;
use App\Models\PermissionOverride;
use App\Models\Programme;
use App\Models\RoleAssignment;
use App\Models\User;

// Default deny. A permission is granted only by an active role assignment, an unexpired allow override,
// or an active break-glass grant, and never when an explicit deny override applies.
// Nothing is cached, so a revocation takes effect on the very next check.
class AccessService
{
    public function can(User $user, string $permissionKey, ?Scope $scope = null): bool
    {
        return $this->decide($user, $permissionKey, $scope)['allowed'];
    }

    /**
     * @return array{allowed: bool, via: string, break_glass_grant_id?: int}
     */
    public function decide(User $user, string $permissionKey, ?Scope $scope = null): array
    {
        $scope ??= Scope::global();

        if (! $user->isActive()) {
            return ['allowed' => false, 'via' => 'inactive_user'];
        }

        $permission = Permission::query()->where('key', $permissionKey)->where('status', 'active')->first();
        if ($permission === null) {
            return ['allowed' => false, 'via' => 'unknown_or_inactive_permission'];
        }

        $overrides = PermissionOverride::query()
            ->where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->active()
            ->get();

        foreach ($overrides as $override) {
            if ($override->effect === 'deny' && $this->scopeMatches($override->scope_type, $override->scope_id, $scope)) {
                return ['allowed' => false, 'via' => 'explicit_deny'];
            }
        }

        $assignments = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('role.permissions', function ($q) use ($permission) {
                $q->where('permissions.id', $permission->id);
            })
            ->get();

        foreach ($assignments as $assignment) {
            if ($this->scopeMatches($assignment->scope_type, $assignment->scope_id, $scope)) {
                return ['allowed' => true, 'via' => 'role'];
            }
        }

        foreach ($overrides as $override) {
            // An allow override must expire; one without an expiry is ignored (fail closed).
            if ($override->effect === 'allow' && $override->expires_at !== null
                && $this->scopeMatches($override->scope_type, $override->scope_id, $scope)) {
                return ['allowed' => true, 'via' => 'override'];
            }
        }

        $grants = BreakGlassGrant::query()->where('user_id', $user->id)->activeNow()->get();
        foreach ($grants as $grant) {
            if (in_array($permissionKey, $grant->permission_keys ?? [], true)) {
                return ['allowed' => true, 'via' => 'break_glass', 'break_glass_grant_id' => $grant->id];
            }
        }

        return ['allowed' => false, 'via' => 'no_grant'];
    }

    // Partner access needs all of: active membership in the partner organisation, and an active scope row
    // for that organisation, programme and function. No scope row means no access.
    public function partnerAllows(User $user, Organisation $partner, Programme $programme, string $function): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $isMember = OrganisationMembership::query()
            ->where('organisation_id', $partner->id)
            ->where('user_id', $user->id)
            ->active()
            ->exists();
        if (! $isMember) {
            return false;
        }

        return PartnerProgrammeScope::query()
            ->where('organisation_id', $partner->id)
            ->where('programme_id', $programme->id)
            ->where('function', $function)
            ->active()
            ->exists();
    }

    // A global assignment matches any requested scope. A scoped assignment matches only the identical scope.
    // A request with no scope (global) is satisfied only by a global assignment.
    private function scopeMatches(string $grantType, ?int $grantId, Scope $requested): bool
    {
        if ($grantType === 'global') {
            return true;
        }

        return $grantType === $requested->type && $grantId !== null && $grantId === $requested->id;
    }
}
