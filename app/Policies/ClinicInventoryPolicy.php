<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ClinicInventory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicInventoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClinicInventory');
    }

    public function view(AuthUser $authUser, ClinicInventory $clinicInventory): bool
    {
        return $authUser->can('View:ClinicInventory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClinicInventory');
    }

    public function update(AuthUser $authUser, ClinicInventory $clinicInventory): bool
    {
        return $authUser->can('Update:ClinicInventory');
    }

    public function delete(AuthUser $authUser, ClinicInventory $clinicInventory): bool
    {
        return $authUser->can('Delete:ClinicInventory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClinicInventory');
    }

    public function restore(AuthUser $authUser, ClinicInventory $clinicInventory): bool
    {
        return $authUser->can('Restore:ClinicInventory');
    }

    public function forceDelete(AuthUser $authUser, ClinicInventory $clinicInventory): bool
    {
        return $authUser->can('ForceDelete:ClinicInventory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClinicInventory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClinicInventory');
    }

    public function replicate(AuthUser $authUser, ClinicInventory $clinicInventory): bool
    {
        return $authUser->can('Replicate:ClinicInventory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClinicInventory');
    }

}