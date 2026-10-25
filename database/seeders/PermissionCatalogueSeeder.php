<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

// Reference data only. Safe in every environment because it creates no accounts and grants nothing.
class PermissionCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = require database_path('data/permissions.php');
        foreach ($catalogue as $entry) {
            Permission::query()->updateOrCreate(
                ['key' => $entry['key']],
                ['module' => $entry['module'], 'description' => $entry['description'], 'status' => 'active'],
            );
        }
    }
}
