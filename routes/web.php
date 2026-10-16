<?php

use App\Http\Controllers\CallRecordingController;
use App\Http\Controllers\InvoiceFileController;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

/*
 * Call recording playback.
 *
 * Behind auth and behind the call's own policy, and streamed rather than
 * redirected — so neither the private storage path nor the provider's URL ever
 * reaches a browser. This is the only way audio leaves the application.
 */
Route::middleware(['web', 'auth'])
    ->get('/calls/recordings/{recording}/stream', [CallRecordingController::class, 'stream'])
    ->name('calls.recordings.stream');

// Uploaded purchase / expense invoice download, behind auth and clinic scoping.
Route::middleware(['web', 'auth'])
    ->get('/invoice-files/{type}/{id}/download', [InvoiceFileController::class, 'download'])
    ->whereIn('type', ['purchases', 'expenses'])
    ->whereNumber('id')
    ->name('invoice-files.download');

Route::get('/test_invoice', function () {
    // return view('welcome');
    $invoice = Invoice::first();
    return view('pdf.invoice', [
        'invoice' => $invoice,
        'clinic' => $invoice->clinic,
        'client' => $invoice->client,
        'items' => $invoice->items,
    ]);
});

