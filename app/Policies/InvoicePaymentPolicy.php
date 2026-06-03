<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InvoicePayment;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePaymentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InvoicePayment');
    }

    public function view(AuthUser $authUser, InvoicePayment $invoicePayment): bool
    {
        return $authUser->can('View:InvoicePayment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InvoicePayment');
    }

    public function update(AuthUser $authUser, InvoicePayment $invoicePayment): bool
    {
        return $authUser->can('Update:InvoicePayment');
    }

    public function delete(AuthUser $authUser, InvoicePayment $invoicePayment): bool
    {
        return $authUser->can('Delete:InvoicePayment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InvoicePayment');
    }

    public function restore(AuthUser $authUser, InvoicePayment $invoicePayment): bool
    {
        return $authUser->can('Restore:InvoicePayment');
    }

    public function forceDelete(AuthUser $authUser, InvoicePayment $invoicePayment): bool
    {
        return $authUser->can('ForceDelete:InvoicePayment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InvoicePayment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InvoicePayment');
    }

    public function replicate(AuthUser $authUser, InvoicePayment $invoicePayment): bool
    {
        return $authUser->can('Replicate:InvoicePayment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InvoicePayment');
    }

}