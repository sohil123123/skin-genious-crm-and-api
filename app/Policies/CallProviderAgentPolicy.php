<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CallProviderAgent;
use Illuminate\Auth\Access\HandlesAuthorization;

class CallProviderAgentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CallProviderAgent');
    }

    public function view(AuthUser $authUser, CallProviderAgent $callProviderAgent): bool
    {
        return $authUser->can('View:CallProviderAgent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CallProviderAgent');
    }

    public function update(AuthUser $authUser, CallProviderAgent $callProviderAgent): bool
    {
        return $authUser->can('Update:CallProviderAgent');
    }

    public function delete(AuthUser $authUser, CallProviderAgent $callProviderAgent): bool
    {
        return $authUser->can('Delete:CallProviderAgent');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CallProviderAgent');
    }

    public function restore(AuthUser $authUser, CallProviderAgent $callProviderAgent): bool
    {
        return $authUser->can('Restore:CallProviderAgent');
    }

    public function forceDelete(AuthUser $authUser, CallProviderAgent $callProviderAgent): bool
    {
        return $authUser->can('ForceDelete:CallProviderAgent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CallProviderAgent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CallProviderAgent');
    }

    public function replicate(AuthUser $authUser, CallProviderAgent $callProviderAgent): bool
    {
        return $authUser->can('Replicate:CallProviderAgent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CallProviderAgent');
    }

}