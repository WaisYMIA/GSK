<?php

namespace App\Policies;

use App\Models\OrganisationMembership;
use App\Models\User;
use App\Models\Vacancy;

// Task 1 scope: employer access comes only from an active organisation membership, never from an
// account type. Employers can edit their own organisation's drafts only. They cannot publish.
// Moderation and publication permissions arrive in Task 3.
class VacancyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && OrganisationMembership::query()->where('user_id', $user->id)->active()->exists();
    }

    public function update(User $user, Vacancy $vacancy): bool
    {
        if (! $user->isActive() || ! in_array($vacancy->status, ['draft', 'changes_requested'], true)) {
            return false;
        }

        return OrganisationMembership::query()
            ->where('user_id', $user->id)
            ->where('organisation_id', $vacancy->organisation_id)
            ->whereIn('membership_role', ['owner', 'admin'])
            ->active()
            ->exists();
    }

    public function publish(User $user, Vacancy $vacancy): bool
    {
        return false;
    }
}
