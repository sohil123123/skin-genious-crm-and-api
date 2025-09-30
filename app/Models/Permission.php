<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends \Spatie\Permission\Models\Permission
{
    public static function defaultPermissions()
	{
	    return [

            'ViewAny:User',
            'View:User',
            'Create:User',
            'Update:User',
            'Delete:User',
            'Restore:User',
            'ForceDelete:User',
            'ForceDeleteAny:User',
            'RestoreAny:User',
            'Replicate:User',

            'ViewAny:Role',
            'View:Role',
            'Create:Role',
            'Update:Role',
            'Delete:Role',
            'Restore:Role',
            'ForceDelete:Role',
            'ForceDeleteAny:Role',
            'RestoreAny:Role',
            'Replicate:Role',

            // 'ViewAny:Permission',
            // 'View:Permission',
            // 'Create:Permission',
            // 'Update:Permission',
            // 'Delete:Permission',
            // 'Restore:Permission',
            // 'ForceDelete:Permission',
            // 'ForceDeleteAny:Permission',
            // 'RestoreAny:Permission',
            // 'Replicate:Permission',


            // 'view_users',
	        // 'create_users',
	        // 'edit_users',
			// 'delete_users',

            // 'view_roles',
	        // 'create_roles',
	        // 'edit_roles',
			// 'delete_roles',

            // 'view_permissions',
	        // 'create_permissions',
	        // 'edit_permissions',
			// 'delete_permissions',

            // 'view_events',
	        // 'create_events',
	        // 'edit_events',
			// 'delete_events',

            // 'view_event_years',
	        // 'create_event_years',
	        // 'edit_event_years',
			// 'delete_event_years',

            // 'view_photos',
	        // 'create_photos',
	        // 'edit_photos',
			// 'delete_photos',
        ];
    }
}
