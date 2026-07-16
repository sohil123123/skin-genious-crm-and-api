<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsAppScheduledMessage;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppScheduledMessagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsAppScheduledMessage');
    }

    public function view(AuthUser $authUser, WhatsAppScheduledMessage $whatsAppScheduledMessage): bool
    {
        return $authUser->can('View:WhatsAppScheduledMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsAppScheduledMessage');
    }

    public function update(AuthUser $authUser, WhatsAppScheduledMessage $whatsAppScheduledMessage): bool
    {
        return $authUser->can('Update:WhatsAppScheduledMessage');
    }

    public function delete(AuthUser $authUser, WhatsAppScheduledMessage $whatsAppScheduledMessage): bool
    {
        return $authUser->can('Delete:WhatsAppScheduledMessage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatsAppScheduledMessage');
    }

    public function restore(AuthUser $authUser, WhatsAppScheduledMessage $whatsAppScheduledMessage): bool
    {
        return $authUser->can('Restore:WhatsAppScheduledMessage');
    }

    public function forceDelete(AuthUser $authUser, WhatsAppScheduledMessage $whatsAppScheduledMessage): bool
    {
        return $authUser->can('ForceDelete:WhatsAppScheduledMessage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsAppScheduledMessage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsAppScheduledMessage');
    }

    public function replicate(AuthUser $authUser, WhatsAppScheduledMessage $whatsAppScheduledMessage): bool
    {
        return $authUser->can('Replicate:WhatsAppScheduledMessage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsAppScheduledMessage');
    }

}