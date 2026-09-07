<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Live call events for one clinic.
 *
 * Carries patient names and phone numbers to whoever is subscribed, so the
 * check is on clinic membership rather than on being logged in. Without it any
 * authenticated account — including a patient with a portal login — could
 * subscribe to a branch's channel and watch its callers arrive.
 *
 * A super admin may listen to any clinic, matching how every other
 * clinic-scoped query in this application behaves.
 */
Broadcast::channel('clinic.{clinicId}.calls', function (User $user, int $clinicId): bool {
    if ($user->hasRole(config('project.roles.super_admin'))) {
        return true;
    }

    // A patient must never receive these, even for their own clinic.
    if ($user->hasRole(config('project.roles.client'))) {
        return false;
    }

    return (int) $user->clinic_id === $clinicId;
});
