<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AvailabilityException;
use Illuminate\Auth\Access\HandlesAuthorization;

class AvailabilityExceptionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AvailabilityException');
    }

    public function view(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return $authUser->can('View:AvailabilityException');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AvailabilityException');
    }

    public function update(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return $authUser->can('Update:AvailabilityException');
    }

    public function delete(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return $authUser->can('Delete:AvailabilityException');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AvailabilityException');
    }

    public function restore(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return $authUser->can('Restore:AvailabilityException');
    }

    public function forceDelete(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return $authUser->can('ForceDelete:AvailabilityException');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AvailabilityException');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AvailabilityException');
    }

    public function replicate(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return $authUser->can('Replicate:AvailabilityException');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AvailabilityException');
    }

}