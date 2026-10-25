<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data: safe everywhere.
        $this->call(PermissionCatalogueSeeder::class);

        // Demo data: local and testing only. Never in staging or production. No admin account, no fixed passwords.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        Programme::firstOrCreate(
            ['slug' => 'foundational-skills'],
            ['name' => 'Foundational Skills', 'description' => 'Development seed programme. Replace before launch.', 'status' => 'draft'],
        );

        $employer = User::firstOrCreate(
            ['email' => 'employer@example.test'],
            ['name' => 'Demo Employer', 'password' => Str::password(32), 'account_type' => 'employer', 'status' => 'active'],
        );
        $organisation = Organisation::firstOrCreate(
            ['owner_user_id' => $employer->id, 'legal_name' => 'Demo Employer Ltd'],
            ['country_code' => 'KE', 'verification_status' => 'pending'],
        );
        OrganisationMembership::firstOrCreate(
            ['organisation_id' => $organisation->id, 'user_id' => $employer->id],
            ['membership_role' => 'owner', 'status' => 'active', 'approved_at' => now()],
        );
    }
}
