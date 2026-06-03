<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsAppMessageLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppMessageLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsAppMessageLog');
    }

    public function view(AuthUser $authUser, WhatsAppMessageLog $whatsAppMessageLog): bool
    {
        return $authUser->can('View:WhatsAppMessageLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsAppMessageLog');
    }

    public function update(AuthUser $authUser, WhatsAppMessageLog $whatsAppMessageLog): bool
    {
        return $authUser->can('Update:WhatsAppMessageLog');
    }

    public function delete(AuthUser $authUser, WhatsAppMessageLog $whatsAppMessageLog): bool
    {
        return $authUser->can('Delete:WhatsAppMessageLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatsAppMessageLog');
    }

    public function restore(AuthUser $authUser, WhatsAppMessageLog $whatsAppMessageLog): bool
    {
        return $authUser->can('Restore:WhatsAppMessageLog');
    }

    public function forceDelete(AuthUser $authUser, WhatsAppMessageLog $whatsAppMessageLog): bool
    {
        return $authUser->can('ForceDelete:WhatsAppMessageLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsAppMessageLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsAppMessageLog');
    }

    public function replicate(AuthUser $authUser, WhatsAppMessageLog $whatsAppMessageLog): bool
    {
        return $authUser->can('Replicate:WhatsAppMessageLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsAppMessageLog');
    }

}