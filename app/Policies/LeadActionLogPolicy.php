<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeadActionLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeadActionLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeadActionLog');
    }

    public function view(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        return $authUser->can('View:LeadActionLog') && $this->sharesClinic($authUser, $leadActionLog);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeadActionLog');
    }

    public function update(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        return $authUser->can('Update:LeadActionLog') && $this->sharesClinic($authUser, $leadActionLog);
    }

    public function delete(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        return $authUser->can('Delete:LeadActionLog') && $this->sharesClinic($authUser, $leadActionLog);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LeadActionLog');
    }

    public function restore(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        return $authUser->can('Restore:LeadActionLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeadActionLog');
    }

    public function forceDelete(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        return $authUser->can('ForceDelete:LeadActionLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeadActionLog');
    }

    public function replicate(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        return $authUser->can('Replicate:LeadActionLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeadActionLog');
    }

    /**
     * Lead actions carry a phone number and a script, so clinic ownership is
     * enforced at the record level as well as in the query scope.
     */
    protected function sharesClinic(AuthUser $authUser, LeadActionLog $leadActionLog): bool
    {
        if ($authUser->hasRole(config('project.roles.super_admin'))) {
            return true;
        }

        return $leadActionLog->clinic_id === $authUser->clinic_id;
    }
}
