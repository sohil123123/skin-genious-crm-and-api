<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Assessment;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssessmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Assessment');
    }

    public function view(AuthUser $authUser, Assessment $assessment): bool
    {
        return $authUser->can('View:Assessment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Assessment');
    }

    public function update(AuthUser $authUser, Assessment $assessment): bool
    {
        return $authUser->can('Update:Assessment');
    }

    public function delete(AuthUser $authUser, Assessment $assessment): bool
    {
        return $authUser->can('Delete:Assessment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Assessment');
    }

    public function restore(AuthUser $authUser, Assessment $assessment): bool
    {
        return $authUser->can('Restore:Assessment');
    }

    public function forceDelete(AuthUser $authUser, Assessment $assessment): bool
    {
        return $authUser->can('ForceDelete:Assessment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Assessment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Assessment');
    }

    public function replicate(AuthUser $authUser, Assessment $assessment): bool
    {
        return $authUser->can('Replicate:Assessment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Assessment');
    }

}