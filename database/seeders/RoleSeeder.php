<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Role;
use App\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::defaultRoles();
        foreach ($roles as $role) {
            $role_obj = Role::updateOrCreate(['name' => trim($role)]);
            if(in_array($role, ['admin']))
                $role_obj->syncPermissions(Permission::all());
        }
    }
}
