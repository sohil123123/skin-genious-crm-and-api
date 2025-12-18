<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserWeeklySchedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserWeeklySchedulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserWeeklySchedule');
    }

    public function view(AuthUser $authUser, UserWeeklySchedule $userWeeklySchedule): bool
    {
        return $authUser->can('View:UserWeeklySchedule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserWeeklySchedule');
    }

    public function update(AuthUser $authUser, UserWeeklySchedule $userWeeklySchedule): bool
    {
        return $authUser->can('Update:UserWeeklySchedule');
    }

    public function delete(AuthUser $authUser, UserWeeklySchedule $userWeeklySchedule): bool
    {
        return $authUser->can('Delete:UserWeeklySchedule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UserWeeklySchedule');
    }

    public function restore(AuthUser $authUser, UserWeeklySchedule $userWeeklySchedule): bool
    {
        return $authUser->can('Restore:UserWeeklySchedule');
    }

    public function forceDelete(AuthUser $authUser, UserWeeklySchedule $userWeeklySchedule): bool
    {
        return $authUser->can('ForceDelete:UserWeeklySchedule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserWeeklySchedule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserWeeklySchedule');
    }

    public function replicate(AuthUser $authUser, UserWeeklySchedule $userWeeklySchedule): bool
    {
        return $authUser->can('Replicate:UserWeeklySchedule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserWeeklySchedule');
    }

}