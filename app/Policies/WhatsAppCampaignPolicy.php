<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsAppCampaign;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppCampaignPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsAppCampaign');
    }

    public function view(AuthUser $authUser, WhatsAppCampaign $whatsAppCampaign): bool
    {
        return $authUser->can('View:WhatsAppCampaign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsAppCampaign');
    }

    public function update(AuthUser $authUser, WhatsAppCampaign $whatsAppCampaign): bool
    {
        return $authUser->can('Update:WhatsAppCampaign');
    }

    public function delete(AuthUser $authUser, WhatsAppCampaign $whatsAppCampaign): bool
    {
        return $authUser->can('Delete:WhatsAppCampaign');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatsAppCampaign');
    }

    public function restore(AuthUser $authUser, WhatsAppCampaign $whatsAppCampaign): bool
    {
        return $authUser->can('Restore:WhatsAppCampaign');
    }

    public function forceDelete(AuthUser $authUser, WhatsAppCampaign $whatsAppCampaign): bool
    {
        return $authUser->can('ForceDelete:WhatsAppCampaign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsAppCampaign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsAppCampaign');
    }

    public function replicate(AuthUser $authUser, WhatsAppCampaign $whatsAppCampaign): bool
    {
        return $authUser->can('Replicate:WhatsAppCampaign');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsAppCampaign');
    }

}