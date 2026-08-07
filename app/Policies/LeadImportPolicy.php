<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LeadImport;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadImportPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeadImport');
    }

    public function view(AuthUser $authUser, LeadImport $leadImport): bool
    {
        return $authUser->can('View:LeadImport');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeadImport');
    }

    public function update(AuthUser $authUser, LeadImport $leadImport): bool
    {
        return $authUser->can('Update:LeadImport');
    }

    public function delete(AuthUser $authUser, LeadImport $leadImport): bool
    {
        return $authUser->can('Delete:LeadImport');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LeadImport');
    }

    public function restore(AuthUser $authUser, LeadImport $leadImport): bool
    {
        return $authUser->can('Restore:LeadImport');
    }

    public function forceDelete(AuthUser $authUser, LeadImport $leadImport): bool
    {
        return $authUser->can('ForceDelete:LeadImport');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeadImport');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeadImport');
    }

    public function replicate(AuthUser $authUser, LeadImport $leadImport): bool
    {
        return $authUser->can('Replicate:LeadImport');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeadImport');
    }

}