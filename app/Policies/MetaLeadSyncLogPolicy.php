<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MetaLeadSyncLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class MetaLeadSyncLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MetaLeadSyncLog');
    }

    public function view(AuthUser $authUser, MetaLeadSyncLog $metaLeadSyncLog): bool
    {
        return $authUser->can('View:MetaLeadSyncLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MetaLeadSyncLog');
    }

    public function update(AuthUser $authUser, MetaLeadSyncLog $metaLeadSyncLog): bool
    {
        return $authUser->can('Update:MetaLeadSyncLog');
    }

    public function delete(AuthUser $authUser, MetaLeadSyncLog $metaLeadSyncLog): bool
    {
        return $authUser->can('Delete:MetaLeadSyncLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MetaLeadSyncLog');
    }

    public function restore(AuthUser $authUser, MetaLeadSyncLog $metaLeadSyncLog): bool
    {
        return $authUser->can('Restore:MetaLeadSyncLog');
    }

    public function forceDelete(AuthUser $authUser, MetaLeadSyncLog $metaLeadSyncLog): bool
    {
        return $authUser->can('ForceDelete:MetaLeadSyncLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MetaLeadSyncLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MetaLeadSyncLog');
    }

    public function replicate(AuthUser $authUser, MetaLeadSyncLog $metaLeadSyncLog): bool
    {
        return $authUser->can('Replicate:MetaLeadSyncLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MetaLeadSyncLog');
    }

}