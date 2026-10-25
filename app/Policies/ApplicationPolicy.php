<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

// Task 1 scope: deny by default. There is no admin branch and no generic staff branch.
// Candidates may view their own application. Employer and staff access (membership, consent,
// field limits, applications.* permissions) is added in Task 3 together with its tests.
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Application $application): bool
    {
        return $user->isActive() && $application->candidate_user_id === $user->id;
    }

    public function update(User $user, Application $application): bool
    {
        return false;
    }
}
