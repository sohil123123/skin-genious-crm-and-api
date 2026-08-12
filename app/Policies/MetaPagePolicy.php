<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MetaPage;
use Illuminate\Auth\Access\HandlesAuthorization;

class MetaPagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MetaPage');
    }

    public function view(AuthUser $authUser, MetaPage $metaPage): bool
    {
        return $authUser->can('View:MetaPage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MetaPage');
    }

    public function update(AuthUser $authUser, MetaPage $metaPage): bool
    {
        return $authUser->can('Update:MetaPage');
    }

    public function delete(AuthUser $authUser, MetaPage $metaPage): bool
    {
        return $authUser->can('Delete:MetaPage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MetaPage');
    }

    public function restore(AuthUser $authUser, MetaPage $metaPage): bool
    {
        return $authUser->can('Restore:MetaPage');
    }

    public function forceDelete(AuthUser $authUser, MetaPage $metaPage): bool
    {
        return $authUser->can('ForceDelete:MetaPage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MetaPage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MetaPage');
    }

    public function replicate(AuthUser $authUser, MetaPage $metaPage): bool
    {
        return $authUser->can('Replicate:MetaPage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MetaPage');
    }

}