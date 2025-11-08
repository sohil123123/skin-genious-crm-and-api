<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Spatie\Permission\Models\Permission;
use App\Models\Role;


if (!function_exists('remove_empty_value')) {
    function remove_empty_value($array){
        return array_values(array_filter($array));
    }
}


// ---------------------------------- Filament Functions ------------------------------

// if (!function_exists('has_clinic_related_role')) {
//     function has_clinic_related_role(?array $roleIds): bool
//     {
//         $roles = Role::whereIn('id', $roleIds ?? [])->pluck('name')->toArray();

//         return in_array('clinic_manager', $roles)
//             || in_array('client', $roles)
//             || in_array('therapist', $roles);
//     }
// }

if (!function_exists('has_clinic_related_role')) {
    function has_clinic_related_role(?int $roleId): bool
    {
        // $roles = Role::whereIn('id', $roleIds ?? [])->pluck('name')->toArray();
        $selectedRole = Role::find($roleId);
        if($selectedRole)
            $selectedRoleName = $selectedRole->name;
        else
            $selectedRoleName = null;
        
        return in_array($selectedRoleName, ['clinic_manager', 'client', 'therapist']);
        
    }
}

// if (!function_exists('has_user_related_role')) {
//     function has_user_related_role(?array $roleIds): bool
//     {
//         $roles = Role::whereIn('id', $roleIds ?? [])->pluck('name')->toArray();

//         return in_array('client', $roles);
//     }
// }

if (!function_exists('has_user_related_role')) {
    function has_user_related_role(?int $roleId): bool
    {
        $selectedRole = Role::find($roleId);
        if($selectedRole)
            $selectedRoleName = $selectedRole->name;
        else
            $selectedRoleName = null;

        return $selectedRoleName == 'client';
    }
}

if (!function_exists('new_assessment')) {
    function new_assessment($user)
    {
        // Generate short-lived Sanctum token (e.g., expires in 1 hour)
        $auth_user = auth()->user();
        $token = $auth_user->createToken(
            'assessment-token-' . Str::random(10),
            ['assessment'], // Abilities/scopes
            // now()->addHour() // Expiration
        )->plainTextToken;

        // Redirect to Assessment App with token and patient ID
        $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $user->id;

        return $assessmentUrl;
    }
}
