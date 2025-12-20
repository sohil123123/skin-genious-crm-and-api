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
        return ($authUser->can('Update:AvailabilityException') && $authUser->id === $availabilityException->exceptionable_id && $availabilityException->status->value === 'pending') || $authUser->hasRole('super_admin');
    }

    public function delete(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return ($authUser->can('Delete:AvailabilityException') && $authUser->id === $availabilityException->exceptionable_id && $availabilityException->status->value === 'pending') || $authUser->hasRole('super_admin');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AvailabilityException');
    }

    public function restore(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return ($authUser->can('Restore:AvailabilityException') && $authUser->id === $availabilityException->exceptionable_id) || $authUser->hasRole('super_admin');
    }

    public function forceDelete(AuthUser $authUser, AvailabilityException $availabilityException): bool
    {
        return ($authUser->can('ForceDelete:AvailabilityException') && $authUser->id === $availabilityException->exceptionable_id) || $authUser->hasRole('super_admin');
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
        return ($authUser->can('Replicate:AvailabilityException') && $authUser->id === $availabilityException->exceptionable_id) || $authUser->hasRole('super_admin');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AvailabilityException');
    }

}
