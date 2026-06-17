<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\StateInvoiceSequence;
use Illuminate\Support\Facades\DB;

class InvoicesRollbackSequenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:rollback-sequence';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollbacks the invoice numbering to the original numbers before migration.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('Starting Invoice Sequence Rollback...');

        if (!$this->confirm('Are you sure you want to rollback to old invoice numbers?')) {
            return;
        }

        DB::transaction(function () {
            $this->info('Restoring old invoice numbers...');
            
            DB::statement('UPDATE invoices SET invoice_number = old_invoice_number, state_code = NULL, sequence_number = NULL WHERE old_invoice_number IS NOT NULL');

            $this->info('Clearing state sequences table...');
            StateInvoiceSequence::query()->delete();

            $this->info('Rollback completed successfully!');
        });
    }
}
