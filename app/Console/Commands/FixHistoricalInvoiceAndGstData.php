<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixHistoricalInvoiceAndGstData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:fix-historical-gst';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix historical HSN/SAC codes and recalculate taxable_value and gst_amount for invoices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting historical data fix...');

        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            // 1. Update Products HSN/SAC
            $this->info('Updating Product HSN/SAC codes...');
            $products = \App\Models\Product::whereNull('hsn_sac_code')->orWhere('hsn_sac_code', '')->get();
            $productCount = 0;
            foreach ($products as $product) {
                $product->hsn_sac_code = $product->type === 'service' ? '999729' : '330499';
                $product->saveQuietly();
                $productCount++;
            }
            $this->info("Updated {$productCount} products.");

            // 2. Update Invoice Items & 3. Invoices
            $this->info('Recalculating Invoice Items and Invoices...');
            $service = new \App\Services\InvoiceCalculationService();
            
            $invoices = \App\Models\Invoice::with('items.product')->get();
            $invoiceCount = 0;
            $itemCount = 0;

            foreach ($invoices as $invoice) {
                $subtotalInclusive = 0;
                $discountTotal = 0;
                $taxableValueTotal = 0;
                $gstTotal = 0;
                $grandTotal = 0;

                foreach ($invoice->items as $item) {
                    // Fix missing GST %
                    if (empty($item->gst_percentage) && $item->product) {
                        $item->gst_percentage = $item->product->gst ?? 18;
                    }

                    // Fix HSN/SAC
                    if (empty($item->hsn_sac_code) && $item->product) {
                        $item->hsn_sac_code = $item->product->hsn_sac_code;
                    }

                    $qty = (int) $item->quantity;
                    $price = (float) $item->unit_price;
                    $dType = $item->discount_type ?? 'flat';
                    $disc = (float) $item->discount_value;
                    $gstP = (float) $item->gst_percentage;

                    $metrics = $service->calculateLineItem($qty, $price, $dType, $disc, $gstP);

                    $item->taxable_value = $metrics['taxable_value'];
                    $item->gst_amount = $metrics['gst_amount'];
                    $item->valid_discount_amount = $metrics['discount_amount'];
                    $item->line_total = $metrics['line_total'];
                    $item->saveQuietly();

                    $subtotalInclusive += $metrics['gross_amount'];
                    $discountTotal += $metrics['discount_amount'];
                    $taxableValueTotal += $metrics['taxable_value'];
                    $gstTotal += $metrics['gst_amount'];
                    $grandTotal += $metrics['line_total'];

                    $itemCount++;
                }

                $invoice->subtotal = $subtotalInclusive;
                $invoice->discount_total = $discountTotal;
                $invoice->taxable_value = $taxableValueTotal;
                $invoice->gst_total = $gstTotal;
                $invoice->grand_total = $grandTotal;
                $invoice->amount_due = max(0, $grandTotal - ($invoice->amount_paid ?? 0));
                
                $invoice->saveQuietly();

                $invoiceCount++;
            }

            \Illuminate\Support\Facades\DB::commit();

            $this->info("Successfully recalculated {$itemCount} invoice items across {$invoiceCount} invoices.");
            $this->info('Data fix completed successfully.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}
