<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserLeaveEntitlement;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserLeaveEntitlementPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserLeaveEntitlement');
    }

    public function view(AuthUser $authUser, UserLeaveEntitlement $userLeaveEntitlement): bool
    {
        return $authUser->can('View:UserLeaveEntitlement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserLeaveEntitlement');
    }

    public function update(AuthUser $authUser, UserLeaveEntitlement $userLeaveEntitlement): bool
    {
        return $authUser->can('Update:UserLeaveEntitlement');
    }

    public function delete(AuthUser $authUser, UserLeaveEntitlement $userLeaveEntitlement): bool
    {
        return $authUser->can('Delete:UserLeaveEntitlement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UserLeaveEntitlement');
    }

    public function restore(AuthUser $authUser, UserLeaveEntitlement $userLeaveEntitlement): bool
    {
        return $authUser->can('Restore:UserLeaveEntitlement');
    }

    public function forceDelete(AuthUser $authUser, UserLeaveEntitlement $userLeaveEntitlement): bool
    {
        return $authUser->can('ForceDelete:UserLeaveEntitlement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserLeaveEntitlement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserLeaveEntitlement');
    }

    public function replicate(AuthUser $authUser, UserLeaveEntitlement $userLeaveEntitlement): bool
    {
        return $authUser->can('Replicate:UserLeaveEntitlement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserLeaveEntitlement');
    }

}