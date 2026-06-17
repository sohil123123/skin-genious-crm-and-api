<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\StateInvoiceSequence;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;

class InvoicesMigrateSequenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:migrate-sequence';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates existing invoices to the new state-wise sequential numbering system.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Invoice Sequence Migration...');

        DB::transaction(function () {
            $this->info('Backing up old invoice numbers...');
            // Backup existing invoice numbers
            DB::statement('UPDATE invoices SET old_invoice_number = invoice_number WHERE old_invoice_number IS NULL');

            $this->info('Resetting state sequences...');
            StateInvoiceSequence::query()->delete();

            $invoices = Invoice::with('clinic')->orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->get();

            $service = new InvoiceNumberService();
            $bar = $this->output->createProgressBar(count($invoices));

            foreach ($invoices as $invoice) {
                // Clear the current invoice number so the service generates a new one
                // Wait, our service might just overwrite it. The service does:
                // $invoice->invoice_number = sprintf(...)
                // So it will overwrite.
                
                $service->generate($invoice, $invoice->clinic);
                
                // Save quietly so we don't trigger events
                $invoice->saveQuietly();
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Migration completed successfully!');
        });
    }
}
