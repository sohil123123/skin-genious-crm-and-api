<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LeadCustomField;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadCustomFieldPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeadCustomField');
    }

    public function view(AuthUser $authUser, LeadCustomField $leadCustomField): bool
    {
        return $authUser->can('View:LeadCustomField');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeadCustomField');
    }

    public function update(AuthUser $authUser, LeadCustomField $leadCustomField): bool
    {
        return $authUser->can('Update:LeadCustomField');
    }

    public function delete(AuthUser $authUser, LeadCustomField $leadCustomField): bool
    {
        return $authUser->can('Delete:LeadCustomField');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LeadCustomField');
    }

    public function restore(AuthUser $authUser, LeadCustomField $leadCustomField): bool
    {
        return $authUser->can('Restore:LeadCustomField');
    }

    public function forceDelete(AuthUser $authUser, LeadCustomField $leadCustomField): bool
    {
        return $authUser->can('ForceDelete:LeadCustomField');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeadCustomField');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeadCustomField');
    }

    public function replicate(AuthUser $authUser, LeadCustomField $leadCustomField): bool
    {
        return $authUser->can('Replicate:LeadCustomField');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeadCustomField');
    }

}