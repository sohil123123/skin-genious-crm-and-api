<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

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

