<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = Permission::defaultPermissions();
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => trim($permission)]);
        }
    }
}
