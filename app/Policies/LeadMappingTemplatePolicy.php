<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LeadMappingTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeadMappingTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeadMappingTemplate');
    }

    public function view(AuthUser $authUser, LeadMappingTemplate $leadMappingTemplate): bool
    {
        return $authUser->can('View:LeadMappingTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeadMappingTemplate');
    }

    public function update(AuthUser $authUser, LeadMappingTemplate $leadMappingTemplate): bool
    {
        return $authUser->can('Update:LeadMappingTemplate');
    }

    public function delete(AuthUser $authUser, LeadMappingTemplate $leadMappingTemplate): bool
    {
        return $authUser->can('Delete:LeadMappingTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LeadMappingTemplate');
    }

    public function restore(AuthUser $authUser, LeadMappingTemplate $leadMappingTemplate): bool
    {
        return $authUser->can('Restore:LeadMappingTemplate');
    }

    public function forceDelete(AuthUser $authUser, LeadMappingTemplate $leadMappingTemplate): bool
    {
        return $authUser->can('ForceDelete:LeadMappingTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeadMappingTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeadMappingTemplate');
    }

    public function replicate(AuthUser $authUser, LeadMappingTemplate $leadMappingTemplate): bool
    {
        return $authUser->can('Replicate:LeadMappingTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeadMappingTemplate');
    }

}