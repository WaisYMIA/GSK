<?php

namespace Tests\Feature\Access;

use App\Models\BreakGlassGrant;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\PartnerProgrammeScope;
use App\Models\Permission;
use App\Models\PermissionOverride;
use App\Models\Programme;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class AccessServiceTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function access(): AccessService
    {
        return app(AccessService::class);
    }

    #[Test]
    public function gsk_080_no_grant_is_denied_then_allowed_then_denied_after_revocation(): void
    {
        $user = $this->makeStaff('STF-A1');
        $role = $this->makeRole('auditor', ['audit.view']);

        $this->assertFalse($this->access()->can($user, 'audit.view'));
        $assignment = $this->grant($user, $role);
        $this->assertTrue($this->access()->can($user, 'audit.view'));
        $this->assertFalse($this->access()->can($user, 'users.manage'));

        $assignment->forceFill(['revoked_at' => now()])->save();
        $this->assertFalse($this->access()->can($user, 'audit.view'), 'GSK-096: revocation must apply on the next check.');
    }

    #[Test]
    public function gsk_080_expired_assignment_is_denied(): void
    {
        $user = $this->makeStaff('STF-A2');
        $role = $this->makeRole('auditor', ['audit.view']);
        $this->grant($user, $role, null, now()->subMinute());

        $this->assertFalse($this->access()->can($user, 'audit.view'));
    }

    #[Test]
    public function gsk_080_unknown_and_deferred_permissions_are_denied(): void
    {
        $user = $this->makeStaff('STF-A3');
        Permission::query()->create(['key' => 'training.cohorts.manage', 'module' => 'training', 'description' => 'deferred', 'status' => 'deferred']);
        $role = $this->makeRole('trainer', ['training.cohorts.manage']);
        $this->grant($user, $role);

        $this->assertFalse($this->access()->can($user, 'training.cohorts.manage'));
        $this->assertFalse($this->access()->can($user, 'does.not.exist'));
    }

    #[Test]
    public function gsk_080_suspended_user_is_denied_even_with_a_role(): void
    {
        $user = $this->makeStaff('STF-A4');
        $this->grant($user, $this->makeRole('auditor', ['audit.view']));
        $user->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse($this->access()->can($user->refresh(), 'audit.view'));
    }

    #[Test]
    public function gsk_082_programme_scoped_assignment_does_not_cross_programmes(): void
    {
        $user = $this->makeStaff('STF-A5');
        $role = $this->makeRole('programme-reader', ['reports.view']);
        $this->grant($user, $role, Scope::programme(10));

        $this->assertTrue($this->access()->can($user, 'reports.view', Scope::programme(10)));
        $this->assertFalse($this->access()->can($user, 'reports.view', Scope::programme(11)));
        $this->assertFalse($this->access()->can($user, 'reports.view', Scope::organisation(10)), 'A programme scope must not satisfy an organisation scope.');
        $this->assertFalse($this->access()->can($user, 'reports.view'), 'A scoped assignment must not satisfy an unscoped request.');
    }

    #[Test]
    public function gsk_080_global_assignment_satisfies_any_scope(): void
    {
        $user = $this->makeStaff('STF-A6');
        $this->grant($user, $this->makeRole('finance-reader', ['reports.view']));

        $this->assertTrue($this->access()->can($user, 'reports.view'));
        $this->assertTrue($this->access()->can($user, 'reports.view', Scope::programme(99)));
    }

    #[Test]
    public function gsk_080_explicit_deny_override_beats_a_role(): void
    {
        $user = $this->makeStaff('STF-A7');
        $this->grant($user, $this->makeRole('auditor', ['audit.view']));
        $permission = Permission::query()->where('key', 'audit.view')->firstOrFail();
        PermissionOverride::query()->create([
            'user_id' => $user->id, 'permission_id' => $permission->id, 'effect' => 'deny',
            'scope_type' => 'global', 'reason' => 'synthetic test',
        ]);

        $this->assertFalse($this->access()->can($user, 'audit.view'));
    }

    #[Test]
    public function gsk_080_allow_override_needs_an_expiry(): void
    {
        $user = $this->makeStaff('STF-A8');
        $permission = Permission::query()->where('key', 'audit.view')->firstOrFail();
        $override = PermissionOverride::query()->create([
            'user_id' => $user->id, 'permission_id' => $permission->id, 'effect' => 'allow',
            'scope_type' => 'global', 'reason' => 'synthetic test', 'expires_at' => null,
        ]);
        $this->assertFalse($this->access()->can($user, 'audit.view'), 'An allow override without an expiry must be ignored.');

        $override->forceFill(['expires_at' => now()->addHour()])->save();
        $this->assertTrue($this->access()->can($user, 'audit.view'));

        $override->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertFalse($this->access()->can($user, 'audit.view'));
    }

    #[Test]
    public function gsk_081_account_type_admin_grants_nothing(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);

        foreach (['users.manage', 'audit.view', 'welfare.cases.view_all_authorised', 'roles.assign', 'settings.manage'] as $key) {
            $this->assertFalse($this->access()->can($admin, $key), "{$key} must not be granted by account_type.");
        }
    }

    #[Test]
    public function gsk_093_break_glass_grant_allows_only_its_listed_keys_while_active(): void
    {
        $user = $this->makeStaff('STF-A9');
        BreakGlassGrant::query()->create([
            'user_id' => $user->id, 'staff_profile_id' => $user->staff_profile_id, 'reason' => 'synthetic emergency test reason',
            'permission_keys' => ['audit.view'], 'activated_at' => now(), 'expires_at' => now()->addMinutes(30),
        ]);

        $this->assertTrue($this->access()->can($user, 'audit.view'));
        $this->assertFalse($this->access()->can($user, 'users.manage'));

        $this->travel(31)->minutes();
        $this->assertFalse($this->access()->can($user, 'audit.view'), 'An expired break-glass grant must not apply.');
    }

    #[Test]
    public function gsk_083_partner_access_needs_membership_and_an_active_scope_row(): void
    {
        $owner = User::factory()->create();
        $partner = Organisation::query()->create(['owner_user_id' => $owner->id, 'legal_name' => 'Synthetic Partner']);
        $programme = Programme::query()->create(['name' => 'P', 'slug' => 'p-scope', 'description' => 'd', 'status' => 'draft']);
        $other = Programme::query()->create(['name' => 'Q', 'slug' => 'q-scope', 'description' => 'd', 'status' => 'draft']);
        $coordinator = User::factory()->create(['account_type' => 'partner']);

        $this->assertFalse($this->access()->partnerAllows($coordinator, $partner, $programme, 'candidate_support'), 'No membership, no scope.');

        OrganisationMembership::query()->create([
            'organisation_id' => $partner->id, 'user_id' => $coordinator->id, 'membership_role' => 'member', 'status' => 'active',
        ]);
        $this->assertFalse($this->access()->partnerAllows($coordinator, $partner, $programme, 'candidate_support'), 'Membership alone is not enough.');

        $scope = PartnerProgrammeScope::query()->create([
            'organisation_id' => $partner->id, 'programme_id' => $programme->id, 'function' => 'candidate_support',
        ]);
        $this->assertTrue($this->access()->partnerAllows($coordinator, $partner, $programme, 'candidate_support'));
        $this->assertFalse($this->access()->partnerAllows($coordinator, $partner, $other, 'candidate_support'), 'Other programme denied.');
        $this->assertFalse($this->access()->partnerAllows($coordinator, $partner, $programme, 'placement_follow_up'), 'Other function denied.');

        $scope->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertFalse($this->access()->partnerAllows($coordinator, $partner, $programme, 'candidate_support'), 'Expired scope denied.');

        $scope->forceFill(['expires_at' => null, 'revoked_at' => now()])->save();
        $this->assertFalse($this->access()->partnerAllows($coordinator, $partner, $programme, 'candidate_support'), 'Revoked scope denied.');
    }
}
