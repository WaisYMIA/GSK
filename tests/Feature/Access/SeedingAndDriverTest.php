<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedingAndDriverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function base_05_tests_run_against_mysql_not_sqlite(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'Tests must run against the disposable MySQL database.');
    }

    #[Test]
    public function base_04_seeding_is_repeatable_and_seeds_the_permission_catalogue_only_as_reference_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = [Permission::query()->count(), User::query()->count()];
        $this->seed(DatabaseSeeder::class);
        $second = [Permission::query()->count(), User::query()->count()];

        $this->assertSame($first, $second, 'A second seed run must not duplicate anything.');
        $this->assertSame(32, Permission::query()->count());
        $this->assertSame(0, Role::query()->count(), 'Role-to-permission mappings are unapproved and must not be seeded.');
        $this->assertSame(0, RoleAssignment::query()->count());
    }

    #[Test]
    public function base_04_no_demo_accounts_are_created_outside_local_and_testing(): void
    {
        $original = $this->app['env'];
        try {
            foreach (['production', 'staging'] as $environment) {
                $this->app['env'] = $environment;
                $this->seed(DatabaseSeeder::class);
            }
            $this->assertSame(0, User::query()->count(), 'No users may be seeded outside local and testing.');
            $this->assertSame(32, Permission::query()->count(), 'Reference data is still seeded.');
        } finally {
            $this->app['env'] = $original;
        }
    }

    #[Test]
    public function base_04_the_seeder_has_no_admin_account_and_no_fixed_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->where('account_type', 'admin')->count());
        $this->assertSame(0, User::query()->where('email', 'admin@example.test')->count());
        foreach (User::query()->get() as $user) {
            $this->assertFalse(password_verify('local-only-change-me', $user->password));
        }
    }

    #[Test]
    public function the_permission_catalogue_has_exactly_the_32_listed_keys_and_no_deferred_modules(): void
    {
        $keys = array_column(require database_path('data/permissions.php'), 'key');
        sort($keys);

        $this->assertCount(32, $keys);
        $this->assertCount(32, array_unique($keys));
        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression('/^(training|reviews)\./', $key);
        }
        $this->assertNotContains('support.impersonate', $keys);
    }

    #[Test]
    public function the_permissions_document_lists_exactly_the_catalogue_keys(): void
    {
        $catalogue = array_column(require database_path('data/permissions.php'), 'key');
        $document = file_get_contents(base_path('docs/PERMISSIONS.md'));
        preg_match_all('/^\| `([a-z_.]+)` \|/m', $document, $matches);

        $documented = $matches[1];
        sort($catalogue);
        sort($documented);
        $this->assertSame($catalogue, $documented, 'docs/PERMISSIONS.md has drifted from database/data/permissions.php.');
    }
}
