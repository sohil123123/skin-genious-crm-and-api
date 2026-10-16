<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceFileController extends Controller
{
    /**
     * Download the invoice uploaded on a purchase or expense.
     *
     * Non super admins may only download invoices of their own clinic,
     * matching the clinic scoping of the Purchase and Expense resources.
     */
    public function download(Request $request, string $type, int $id): Response
    {
        $record = match ($type) {
            'purchases' => Purchase::findOrFail($id),
            'expenses' => Expense::findOrFail($id),
            default => abort(404),
        };

        abort_if(
            ! check_role(config('project.roles.super_admin')) && $record->clinic_id !== auth()->user()->clinic_id,
            403,
        );

        $media = $record->getFirstMedia('invoice');

        abort_unless($media, 404);

        // Media responds as a download (Content-Disposition: attachment).
        return $media->toResponse($request);
    }
}
