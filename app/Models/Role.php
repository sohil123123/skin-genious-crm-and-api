<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends \Spatie\Permission\Models\Role
{
    public static function defaultRoles()
	{
        return [
            'super_admin',
            'admin',
            'therapist',
            'clinic_manager',
            'doctor',
            'user'
        ];
    }
}
