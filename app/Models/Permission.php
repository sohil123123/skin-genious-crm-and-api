<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends \Spatie\Permission\Models\Permission
{
    public static function defaultPermissions()
	{
	    return [

            // 'ViewAny:Clinic',
            // 'View:Clinic',
            // 'Create:Clinic',
            // 'Update:Clinic',
            // 'Delete:Clinic',
            // 'Restore:Clinic',
            // 'ForceDelete:Clinic',
            // 'ForceDeleteAny:Clinic',
            // 'RestoreAny:Clinic',
            // 'Replicate:Clinic',
            // 'Reorder:Clinic',
            // 'DeleteAny:Clinic',

            // 'ViewAny:User',
            // 'View:User',
            // 'Create:User',
            // 'Update:User',
            // 'Delete:User',
            // 'Restore:User',
            // 'ForceDelete:User',
            // 'ForceDeleteAny:User',
            // 'RestoreAny:User',
            // 'Replicate:User',
            // 'Reorder:User',
            // 'DeleteAny:User',

            // 'ViewAny:Role',
            // 'View:Role',
            // 'Create:Role',
            // 'Update:Role',
            // 'Delete:Role',
            // 'Restore:Role',
            // 'ForceDelete:Role',
            // 'ForceDeleteAny:Role',
            // 'RestoreAny:Role',
            // 'Replicate:Role',
            // 'Reorder:Role',
            // 'DeleteAny:Role',

        ];
    }
}
