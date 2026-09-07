<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Call;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Who may see and act on a call.
 *
 * Follows the same shield-permission shape as every other policy here, with two
 * additions that exist because a call is not an ordinary record.
 *
 * The first is clinic scoping enforced in the policy, not only in the query.
 * The list is already scoped, but a call detail page reached by guessing a URL
 * would otherwise show one clinic's patient conversations to another's staff.
 *
 * The second is that recordings and raw payloads are gated separately from the
 * call itself. Seeing that a call happened is administrative; listening to it
 * is reading a patient's medical conversation, and the raw payload contains the
 * unredacted numbers of everyone involved. Reception needs the first; almost
 * nobody needs the third.
 */
class CallPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Call');
    }

    public function view(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('View:Call') && $this->sharesClinic($authUser, $call);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Call');
    }

    public function update(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('Update:Call') && $this->sharesClinic($authUser, $call);
    }

    public function delete(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('Delete:Call') && $this->sharesClinic($authUser, $call);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Call');
    }

    public function restore(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('Restore:Call');
    }

    public function forceDelete(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('ForceDelete:Call');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Call');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Call');
    }

    public function replicate(AuthUser $authUser, Call $call): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }

    /**
     * Listen to a call's audio.
     *
     * A separate permission from viewing the call. Knowing that a patient rang
     * at 3pm is roster information; hearing what they said about their skin
     * condition is not, and the two should not be granted together by default.
     */
    public function playRecording(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('PlayRecording:Call') && $this->sharesClinic($authUser, $call);
    }

    /**
     * Read the transcript.
     */
    public function viewTranscript(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('ViewTranscript:Call') && $this->sharesClinic($authUser, $call);
    }

    /**
     * Read the untouched provider payload.
     *
     * Restricted to super admins regardless of permissions. The payload is
     * stored for debugging and contains every phone number involved, in full
     * and unredacted — it is a diagnostic artefact, not a CRM screen.
     */
    public function viewRawPayload(AuthUser $authUser): bool
    {
        return $authUser->hasRole(config('project.roles.super_admin'));
    }

    /**
     * Attach an unmatched or ambiguous call to a patient or lead by hand.
     */
    public function matchCustomer(AuthUser $authUser, Call $call): bool
    {
        return $authUser->can('Update:Call') && $this->sharesClinic($authUser, $call);
    }

    /**
     * A super admin sees everything, including calls no clinic owns yet.
     *
     * An unattributed call is a configuration problem rather than anyone's
     * conversation, so it must remain visible to whoever can fix it — and to
     * nobody else.
     */
    protected function sharesClinic(AuthUser $authUser, Call $call): bool
    {
        if ($authUser->hasRole(config('project.roles.super_admin'))) {
            return true;
        }

        if ($call->clinic_id === null) {
            return false;
        }

        return (int) $call->clinic_id === (int) $authUser->clinic_id;
    }
}
