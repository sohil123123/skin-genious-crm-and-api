<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Clinic;
use App\Models\StateInvoiceSequence;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Generate a state-wise sequential invoice number safely.
     *
     * @param Invoice $invoice
     * @param Clinic|null $clinic
     * @return void
     */
    public function generate(Invoice $invoice, ?Clinic $clinic = null): void
    {
        if (!$clinic && $invoice->clinic_id) {
            $clinic = Clinic::find($invoice->clinic_id);
        }

        // If no clinic or state is found, fallback to 'UN' (Unknown)
        $state = $clinic?->state ?? config('project.company_state_code');

        DB::transaction(function () use ($invoice, $state) {
            // Row-level lock to prevent concurrent duplicates
            $sequence = StateInvoiceSequence::lockForUpdate()->firstOrCreate(
                ['state_code' => $state],
                ['last_number' => 0]
            );

            $sequence->increment('last_number');

            $invoice->state_code = $state;
            $invoice->sequence_number = $sequence->last_number;

            // Format: INV-MH-0001
            $invoice->invoice_number = sprintf('INV-%s-%04d', $state, $sequence->last_number);
        });
    }
}
