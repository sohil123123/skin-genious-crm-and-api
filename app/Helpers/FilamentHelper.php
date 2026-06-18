<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

        return !in_array($selectedRoleName, [config('project.roles.super_admin')]);

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



if (!function_exists('can_create_assessment')) {
    function can_create_assessment($appointment)
    {
        $start = Carbon::parse($appointment->start_datetime)->subMinutes(15);
        $end   = $appointment->end_datetime;

        return $appointment->type->value === 'consult'
        && in_array($appointment->status->value, ['confirmed'])
        && is_null($appointment->assessment_id)
        // && $appointment->appointment_datetime->isBetween(now(), now()->addMinutes(20)); //not used
        && now()->between($start, $end);
    }
}

if (!function_exists('new_assessment')) {
    function new_assessment($user, $type = 'assessment', $appointment = [])
    {
        // Generate short-lived Sanctum token (e.g., expires in 1 hour)
        $auth_user = auth()->user();
        $token = $auth_user->createToken(
            'assessment-token-' . Str::random(10),
            ['assessment'], // Abilities/scopes
            // now()->addHour() // Expiration
        )->plainTextToken;

        // Redirect to Assessment App with token and patient ID
        if($appointment)
            $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $user->id . '&appointment_id=' . $appointment->id. '&type='.$type;
        else
            $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $user->id . '&type='.$type;

        return $assessmentUrl;
    }
}

if (!function_exists('edit_assessment')) {
    function edit_assessment($assessment, $appointment = [])
    {
        // Generate short-lived Sanctum token (e.g., expires in 1 hour)
        $auth_user = auth()->user();
        $token = $auth_user->createToken(
            'assessment-token-' . Str::random(10),
            ['assessment'], // Abilities/scopes
            // now()->addHour() // Expiration
        )->plainTextToken;

        // Redirect to Assessment App with token and patient ID
        if($appointment)
            $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $assessment->user_id . '&appointment_id=' . $appointment->id. '&assessment_id=' . $assessment->id .'&type=edit&assessment_type='.$assessment->assessment_type;
        else
            $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $assessment->user_id . '&assessment_id=' . $assessment->id .'&type=edit&assessment_type='.$assessment->assessment_type;

        return $assessmentUrl;
    }
}

if (!function_exists('can_start_session')) {
    function can_start_session($appointment)
    {
        $start = Carbon::parse($appointment->start_datetime)->subMinutes(15);
        $end   = $appointment->end_datetime;

        return in_array($appointment->status->value, ['confirmed'])
        && !is_null($appointment->treatment_session_id)
        && now()->between($start, $end);
    }
}

if (!function_exists('start_session')) {
    function start_session($appointment)
    {
        // return 'www.google.com';
        // Generate short-lived Sanctum token (e.g., expires in 1 hour)
        $auth_user = auth()->user();
        $token = $auth_user->createToken(
            'assessment-token-' . Str::random(10),
            ['assessment'], // Abilities/scopes
            // now()->addHour() // Expiration
        )->plainTextToken;
        $assessment_type = $appointment->assessment->assessment_type;

        // Redirect to Assessment App with token and patient ID
        if($assessment_type == 'iv'){
            $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $appointment->user_id . '&appointment_id=' . $appointment->id . '&assessment_id=' . $appointment->assessment_id . '&session_id=' . $appointment->treatment_session_id . '&type=iv-treatment';
            return $assessmentUrl;
        } else {
            $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $appointment->user_id . '&appointment_id=' . $appointment->id . '&assessment_id=' . $appointment->assessment_id . '&session_id=' . $appointment->treatment_session_id . '&type=treatment';
            return $assessmentUrl;
        }
    }
}

if (!function_exists('clinic_head_complete_session')) {
    function clinic_head_complete_session($session)
    {
        $auth_user = auth()->user();
        $token = $auth_user->createToken(
            'assessment-token-' . Str::random(10),
            ['assessment']
        )->plainTextToken;

        $userId = $session->assessment->user_id;
        $assessmentId = $session->assessment->id;
        $sessionId = $session->id;

        $assessmentUrl = config('project.frontend_url').'/authenticate?token=' . $token . '&user_id=' . $userId . '&assessment_id=' . $assessmentId . '&session_id=' . $sessionId . '&type=clinic-head-complete';

        return $assessmentUrl;
    }
}

if (!function_exists('time_options')) {
    function time_options($start = 7, $end = 23)
    {
        $options = [];
        foreach (range(7, 23) as $h) {
            foreach ([0, 15, 30, 45] as $m) {
                $t = sprintf('%02d:%02d', $h, $m);
                $options[$t] = $t;
            }
        }
        return $options;
    }
}

if (!function_exists('disabled_sunday_dates')) {
    function disabled_sunday_dates($how_many_year = 1)
    {
        $disabled = [];

        // disable Sundays for next 1 years (adjust if needed)
        $start = now();
        $end   = now()->addYears($how_many_year);

        while ($start <= $end) {
            if ($start->isSunday()) {
                $disabled[] = $start->format('Y-m-d');
            }
            $start->addDay();
        }

        return $disabled;
    }
}

if (!function_exists('day_options')) {
    function day_options()
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }
}

if (! function_exists('day_color')) {
    function day_color(int $day): string
    {
        return match ($day) {
            1 => 'primary',
            2 => 'success',
            3 => 'warning',
            4 => 'info',
            5 => 'danger',
            6 => 'gray',
            7 => 'gray',
            default => 'gray',
        };
    }
}

