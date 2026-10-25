<?php

namespace Tests\Support;

use App\Models\Application;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\WelfareCase;
use App\Support\Access\AccessDenied;
use App\Support\Access\Scope;
use App\Support\Content\ContentWorkflowService;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Str;

// Synthetic fixtures only. No real people, no real data.
trait AccessFixtures
{
    protected function seedPermissions(): void
    {
        $this->seed(PermissionCatalogueSeeder::class);
    }

    protected function makeRole(string $key, array $permissionKeys = [], bool $privileged = false, bool $requiresStaff = true): Role
    {
        $role = Role::query()->create([
            'key' => $key,
            'name' => $key,
            'is_privileged' => $privileged,
            'requires_staff_profile' => $requiresStaff,
        ]);
        $ids = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
        $role->permissions()->sync($ids);

        return $role;
    }

    protected function makeStaff(string $ref, bool $identityChecked = true, string $profileStatus = 'active'): User
    {
        $email = strtolower($ref) . '@gsk.example.test';
        $profile = StaffProfile::query()->create([
            'person_ref' => $ref,
            'full_name' => 'Staff ' . $ref,
            'work_email' => $email,
            'status' => $profileStatus,
            'identity_checked_at' => $identityChecked ? now() : null,
        ]);
        $user = User::factory()->staff()->create(['email' => $email]);
        $user->staff_profile_id = $profile->id;
        $user->save();

        return $user->refresh();
    }

    // A second account for the same real person (same staff profile).
    protected function makeSecondAccountFor(User $user, string $email): User
    {
        $second = User::factory()->staff()->create(['email' => $email]);
        $second->staff_profile_id = $user->staff_profile_id;
        $second->save();

        return $second->refresh();
    }

    protected function grant(User $user, Role $role, ?Scope $scope = null, ?CarbonInterface $expires = null): RoleAssignment
    {
        $scope ??= Scope::global();

        return RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->type,
            'scope_id' => $scope->id,
            'expires_at' => $expires,
        ]);
    }

    protected function enableApprovals(): void
    {
        config(['gsk.approvals.enabled' => true]);
    }

    /** @return array{owner: User, organisation: Organisation, vacancy: Vacancy, candidate: User, application: Application} */
    protected function makeVacancyChain(string $vacancyStatus = 'draft'): array
    {
        $owner = User::factory()->employer()->create();
        $organisation = Organisation::query()->create([
            'owner_user_id' => $owner->id,
            'legal_name' => 'Synthetic Employer ' . Str::random(6),
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
        $vacancy = Vacancy::query()->create([
            'organisation_id' => $organisation->id,
            'title' => 'Synthetic role',
            'slug' => 'synthetic-role-' . Str::lower(Str::random(8)),
            'description' => 'Synthetic description.',
            'status' => $vacancyStatus,
        ]);
        $candidate = User::factory()->create();
        $application = Application::query()->create([
            'vacancy_id' => $vacancy->id,
            'candidate_user_id' => $candidate->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return compact('owner', 'organisation', 'vacancy', 'candidate', 'application');
    }

    protected function makeCase(): WelfareCase
    {
        return WelfareCase::query()->create([
            'public_reference' => (string) Str::uuid(),
            'is_anonymous' => true,
            'category' => 'other',
            'status' => 'received',
            'description' => 'Synthetic test narrative, not a real report.',
        ]);
    }

    // ---- Task 2 helpers ----
    protected function withMfa(User $user): User
    {
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $user->refresh();
    }

    /** Staff member holding exactly the given permission keys through a role named after the reference, with MFA confirmed. */
    protected function staffWith(string $ref, array $permissionKeys, bool $mfa = true): User
    {
        $this->seedPermissions();
        $user = $this->makeStaff($ref);
        $this->grant($user, $this->makeRole('role-' . strtolower($ref), $permissionKeys));

        return $mfa ? $this->withMfa($user) : $user;
    }

    protected function editorKeys(): array
    {
        return ['content.pages.view', 'content.pages.create', 'content.pages.edit', 'content.posts.view', 'content.posts.create',
            'content.posts.edit', 'content.posts.manage_media'];
    }

    protected function publisherKeys(): array
    {
        return ['content.pages.view', 'content.pages.publish', 'content.posts.view', 'content.posts.publish'];
    }

    protected function makeMedia(?User $by = null, string $status = 'approved'): Media
    {
        return Media::query()->create([
            'uploaded_by' => $by?->id, 'stored_path' => 'media/synthetic-' . Str::lower(Str::random(8)) . '.png', 'mime' => 'image/png',
            'size_bytes' => 1000, 'width' => 100, 'height' => 80, 'alt_text' => 'Synthetic alt text', 'credit_or_source' => 'Synthetic source',
            'rights_confirmed' => true, 'people_consent_recorded' => false, 'status' => $status,
        ]);
    }

    protected function workflow(): ContentWorkflowService
    {
        return app(ContentWorkflowService::class);
    }

    /** Creates, submits and publishes a page (or post) using two different staff members. Returns the owner. */
    protected function publishedContent(string $type, string $slug, string $title, string $text = 'Synthetic body.', array $extra = []): Page|Post
    {
        static $n = 0;
        $n++;
        $editor = $this->staffWith('EDT-' . $n . '-' . Str::upper(Str::random(4)), $this->editorKeys());
        $publisher = $this->staffWith('PUB-' . $n . '-' . Str::upper(Str::random(4)), $this->publisherKeys());
        $owner = $this->workflow()->create($editor, $type, array_merge(['title' => $title, 'slug' => $slug], $extra), $text);
        $this->workflow()->submit($editor, $owner);

        return $this->workflow()->publish($publisher, $owner);
    }

    protected function assertDeniedWith(string $code, callable $action): void
    {
        try {
            $action();
        } catch (AccessDenied $e) {
            $this->assertSame($code, $e->reasonCode);

            return;
        }
        $this->fail("Expected AccessDenied({$code}) but nothing was thrown.");
    }
}
