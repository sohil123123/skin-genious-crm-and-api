<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Clinic;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Clinic');
    }

    public function view(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('View:Clinic');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Clinic');
    }

    public function update(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('Update:Clinic');
    }

    public function delete(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('Delete:Clinic');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Clinic');
    }

    public function restore(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('Restore:Clinic');
    }

    public function forceDelete(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('ForceDelete:Clinic');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Clinic');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Clinic');
    }

    public function replicate(AuthUser $authUser, Clinic $clinic): bool
    {
        return $authUser->can('Replicate:Clinic');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Clinic');
    }

}