<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the EmergencyOverride:Appointment permission so it can be assigned
 * to any role (Shield → Roles → Custom Permissions) or user (Users → Manage
 * Permissions). Granted to super_admin so the role keeps its existing access.
 */
return new class extends Migration
{
    private const PERMISSION = 'EmergencyOverride:Appointment';

    public function up(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');

        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => $guard,
        ]);

        $superAdmin = Role::query()
            ->where('name', config('project.roles.super_admin', 'super_admin'))
            ->where('guard_name', $guard)
            ->first();

        if ($superAdmin && ! $superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
