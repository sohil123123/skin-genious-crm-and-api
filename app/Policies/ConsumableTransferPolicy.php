<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ConsumableTransfer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConsumableTransferPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConsumableTransfer');
    }

    public function view(AuthUser $authUser, ConsumableTransfer $consumableTransfer): bool
    {
        return $authUser->can('View:ConsumableTransfer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConsumableTransfer');
    }

    public function update(AuthUser $authUser, ConsumableTransfer $consumableTransfer): bool
    {
        return $authUser->can('Update:ConsumableTransfer');
    }

    public function delete(AuthUser $authUser, ConsumableTransfer $consumableTransfer): bool
    {
        return $authUser->can('Delete:ConsumableTransfer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ConsumableTransfer');
    }

    public function restore(AuthUser $authUser, ConsumableTransfer $consumableTransfer): bool
    {
        return $authUser->can('Restore:ConsumableTransfer');
    }

    public function forceDelete(AuthUser $authUser, ConsumableTransfer $consumableTransfer): bool
    {
        return $authUser->can('ForceDelete:ConsumableTransfer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConsumableTransfer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConsumableTransfer');
    }

    public function replicate(AuthUser $authUser, ConsumableTransfer $consumableTransfer): bool
    {
        return $authUser->can('Replicate:ConsumableTransfer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConsumableTransfer');
    }

}