<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsAppMediaLibrary;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsAppMediaLibraryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsAppMediaLibrary');
    }

    public function view(AuthUser $authUser, WhatsAppMediaLibrary $whatsAppMediaLibrary): bool
    {
        return $authUser->can('View:WhatsAppMediaLibrary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsAppMediaLibrary');
    }

    public function update(AuthUser $authUser, WhatsAppMediaLibrary $whatsAppMediaLibrary): bool
    {
        return $authUser->can('Update:WhatsAppMediaLibrary');
    }

    public function delete(AuthUser $authUser, WhatsAppMediaLibrary $whatsAppMediaLibrary): bool
    {
        return $authUser->can('Delete:WhatsAppMediaLibrary');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatsAppMediaLibrary');
    }

    public function restore(AuthUser $authUser, WhatsAppMediaLibrary $whatsAppMediaLibrary): bool
    {
        return $authUser->can('Restore:WhatsAppMediaLibrary');
    }

    public function forceDelete(AuthUser $authUser, WhatsAppMediaLibrary $whatsAppMediaLibrary): bool
    {
        return $authUser->can('ForceDelete:WhatsAppMediaLibrary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsAppMediaLibrary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsAppMediaLibrary');
    }

    public function replicate(AuthUser $authUser, WhatsAppMediaLibrary $whatsAppMediaLibrary): bool
    {
        return $authUser->can('Replicate:WhatsAppMediaLibrary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsAppMediaLibrary');
    }

}