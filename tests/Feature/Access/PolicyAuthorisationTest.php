<?php

namespace Tests\Feature\Access;

use App\Models\OrganisationMembership;
use App\Models\User;
use App\Models\WelfareCase;
use App\Support\Access\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class PolicyAuthorisationTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    #[Test]
    public function gsk_081_admin_account_type_is_denied_on_applications_vacancies_and_welfare(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $chain = $this->makeVacancyChain();
        $case = $this->makeCase();
        $gate = Gate::forUser($admin);

        $this->assertTrue($gate->denies('view', $chain['application']));
        $this->assertTrue($gate->denies('update', $chain['vacancy']));
        $this->assertTrue($gate->denies('viewAny', WelfareCase::class));
        $this->assertTrue($gate->denies('view', $case));
        $this->assertTrue($gate->denies('update', $case));
    }

    #[Test]
    public function gsk_029_roles_without_a_welfare_capability_cannot_read_a_case(): void
    {
        $case = $this->makeCase();
        $roles = [
            'content-editor' => ['users.view'],
            'finance' => ['reports.view'],
            'system-admin' => ['users.manage', 'roles.manage', 'settings.manage', 'audit.view'],
            'auditor' => ['audit.view'],
        ];
        foreach ($roles as $key => $permissions) {
            $user = $this->makeStaff('STF-' . strtoupper($key));
            $this->grant($user, $this->makeRole($key, $permissions));
            $this->assertTrue(Gate::forUser($user)->denies('view', $case), "{$key} must not read a case.");
        }
    }

    #[Test]
    public function gsk_106_welfare_access_needs_oversight_or_a_case_scoped_assignment(): void
    {
        $caseA = $this->makeCase();
        $caseB = $this->makeCase();

        $officer = $this->makeStaff('STF-W1');
        $this->grant($officer, $this->makeRole('welfare-officer', ['welfare.cases.view_assigned']), Scope::case((int) $caseA->id));
        $this->assertTrue(Gate::forUser($officer)->allows('view', $caseA));
        $this->assertTrue(Gate::forUser($officer)->denies('view', $caseB), 'An assignment to case A must not open case B.');

        $lead = $this->makeStaff('STF-W2');
        $this->grant($lead, $this->makeRole('safeguarding-lead', ['welfare.cases.view_all_authorised']));
        $this->assertTrue(Gate::forUser($lead)->allows('view', $caseA));
        $this->assertTrue(Gate::forUser($lead)->allows('view', $caseB));
        $this->assertTrue(Gate::forUser($lead)->denies('update', $caseA), 'Writes stay denied until Task 5.');
    }

    #[Test]
    public function gsk_010_candidate_can_view_only_their_own_application(): void
    {
        $chain = $this->makeVacancyChain();
        $stranger = User::factory()->create();

        $this->assertTrue(Gate::forUser($chain['candidate'])->allows('view', $chain['application']));
        $this->assertTrue(Gate::forUser($stranger)->denies('view', $chain['application']));
        $this->assertTrue(Gate::forUser($chain['owner'])->denies('view', $chain['application']), 'Employer access is a Task 3 feature and is denied for now.');
    }

    #[Test]
    public function gsk_068_employer_vacancy_edit_needs_an_active_owner_or_admin_membership(): void
    {
        $chain = $this->makeVacancyChain('draft');
        $member = User::factory()->employer()->create();
        $outsider = User::factory()->employer()->create();

        // The legacy owner_user_id on the organisation grants nothing by itself.
        $this->assertTrue(Gate::forUser($chain['owner'])->denies('update', $chain['vacancy']));

        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $chain['organisation']->id, 'user_id' => $member->id, 'membership_role' => 'admin', 'status' => 'active',
        ]);
        $this->assertTrue(Gate::forUser($member)->allows('update', $chain['vacancy']));
        $this->assertTrue(Gate::forUser($outsider)->denies('update', $chain['vacancy']));

        $membership->forceFill(['status' => 'suspended'])->save();
        $this->assertTrue(Gate::forUser($member)->denies('update', $chain['vacancy']));

        $membership->forceFill(['status' => 'active', 'membership_role' => 'member'])->save();
        $this->assertTrue(Gate::forUser($member)->denies('update', $chain['vacancy']), 'A plain member must not edit.');
    }

    #[Test]
    public function gsk_098_employer_cannot_edit_a_published_vacancy_or_publish(): void
    {
        $chain = $this->makeVacancyChain('published');
        $member = User::factory()->employer()->create();
        OrganisationMembership::query()->create([
            'organisation_id' => $chain['organisation']->id, 'user_id' => $member->id, 'membership_role' => 'owner', 'status' => 'active',
        ]);

        $this->assertTrue(Gate::forUser($member)->denies('update', $chain['vacancy']));
        $this->assertTrue(Gate::forUser($member)->denies('publish', $chain['vacancy']));
    }
}
