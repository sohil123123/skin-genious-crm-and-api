<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\Role;
use App\Models\TreatmentSession;

if (!function_exists('has_clinic_related_role')) {
    function has_clinic_related_role(?int $roleId): bool
    {
        $selectedRole = Role::find($roleId);
        if($selectedRole)
            $selectedRoleName = $selectedRole->name;
        else
            $selectedRoleName = null;

        return in_array($selectedRoleName, ['clinic_manager', 'client', 'therapist']);

    }
}

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

if (!function_exists('get_treatment_session_duration')) {
    function get_treatment_session_duration($treatment_session_id)
    {
        $session = TreatmentSession::find( $treatment_session_id );
        return ($session) ? (int) filter_var($session->treatment_time, FILTER_SANITIZE_NUMBER_INT) : 0;

        // if (!$session || empty($session->treatment_time)) {
        //     $duration = 90; // fallback
        // } else {
        //     // convert "60 mins" → 60
        //     $duration = (int) filter_var($session->treatment_time, FILTER_SANITIZE_NUMBER_INT);
        //     if ($duration <= 0) $duration = 90; // safety fallback
        // }

        // return $duration;
    }
}
