<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WelfareCase;
use App\Support\Access\AccessService;
use App\Support\Access\Scope;

// Task 1 scope: no admin branch. Access needs a welfare permission through a role assignment:
// oversight (view_all_authorised) or a case-scoped assignment (view_assigned with Scope::case).
// Writes are denied until Task 5 adds assignment workflow, conflict-of-interest checks and escalation.
// Sensitive reads must go through App\Support\Access\SensitiveAccess so they are audited.
class WelfareCasePolicy
{
    public function viewAny(User $user): bool
    {
        return app(AccessService::class)->can($user, 'welfare.cases.view_all_authorised');
    }

    public function view(User $user, WelfareCase $case): bool
    {
        $access = app(AccessService::class);

        return $access->can($user, 'welfare.cases.view_all_authorised')
            || $access->can($user, 'welfare.cases.view_assigned', Scope::case((int) $case->id));
    }

    public function update(User $user, WelfareCase $case): bool
    {
        return false;
    }
}
