<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserPackage;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPackagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserPackage');
    }

    public function view(AuthUser $authUser, UserPackage $userPackage): bool
    {
        return $authUser->can('View:UserPackage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserPackage');
    }

    public function update(AuthUser $authUser, UserPackage $userPackage): bool
    {
        return $authUser->can('Update:UserPackage');
    }

    public function delete(AuthUser $authUser, UserPackage $userPackage): bool
    {
        return $authUser->can('Delete:UserPackage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UserPackage');
    }

    public function restore(AuthUser $authUser, UserPackage $userPackage): bool
    {
        return $authUser->can('Restore:UserPackage');
    }

    public function forceDelete(AuthUser $authUser, UserPackage $userPackage): bool
    {
        return $authUser->can('ForceDelete:UserPackage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserPackage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserPackage');
    }

    public function replicate(AuthUser $authUser, UserPackage $userPackage): bool
    {
        return $authUser->can('Replicate:UserPackage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserPackage');
    }

}