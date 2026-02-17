<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StockTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StockTransaction');
    }

    public function view(AuthUser $authUser, StockTransaction $stockTransaction): bool
    {
        return $authUser->can('View:StockTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StockTransaction');
    }

    public function update(AuthUser $authUser, StockTransaction $stockTransaction): bool
    {
        return $authUser->can('Update:StockTransaction');
    }

    public function delete(AuthUser $authUser, StockTransaction $stockTransaction): bool
    {
        return $authUser->can('Delete:StockTransaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StockTransaction');
    }

    public function restore(AuthUser $authUser, StockTransaction $stockTransaction): bool
    {
        return $authUser->can('Restore:StockTransaction');
    }

    public function forceDelete(AuthUser $authUser, StockTransaction $stockTransaction): bool
    {
        return $authUser->can('ForceDelete:StockTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StockTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StockTransaction');
    }

    public function replicate(AuthUser $authUser, StockTransaction $stockTransaction): bool
    {
        return $authUser->can('Replicate:StockTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StockTransaction');
    }

}