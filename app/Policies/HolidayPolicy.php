<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Holiday;
use Illuminate\Auth\Access\HandlesAuthorization;

class HolidayPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Holiday') && auth()->user()->hasAnyRole(['therapist', 'clinic_manager', 'super_admin']);
    }

    public function view(AuthUser $authUser, Holiday $holiday): bool
    {
        return ($authUser->can('View:Holiday') && $authUser->id === $holiday->user_id) || $authUser->hasRole('super_admin');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Holiday');
    }

    public function update(AuthUser $authUser, Holiday $holiday): bool
    {
        return ($authUser->can('Update:Holiday') && $authUser->id === $holiday->user_id && $holiday->status === 'pending') || $authUser->hasRole('super_admin');
    }

    public function delete(AuthUser $authUser, Holiday $holiday): bool
    {
        return ($authUser->can('Delete:Holiday') && $authUser->id === $holiday->user_id) || $authUser->hasRole('super_admin');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Holiday');
    }

    public function restore(AuthUser $authUser, Holiday $holiday): bool
    {
        return ($authUser->can('Restore:Holiday') && $authUser->id === $holiday->user_id) || $authUser->hasRole('super_admin');
    }

    public function forceDelete(AuthUser $authUser, Holiday $holiday): bool
    {
        return ($authUser->can('ForceDelete:Holiday') && $authUser->id === $holiday->user_id) || $authUser->hasRole('super_admin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Holiday');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Holiday');
    }

    public function replicate(AuthUser $authUser, Holiday $holiday): bool
    {
        return ($authUser->can('Replicate:Holiday') && $authUser->id === $holiday->user_id) || $authUser->hasRole('super_admin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Holiday');
    }

}
