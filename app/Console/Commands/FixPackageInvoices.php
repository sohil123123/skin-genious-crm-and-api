<?php

namespace App\Console\Commands;

use App\Models\UserPackage;
use App\Models\Product;
use App\Models\Invoice;
use App\Services\InvoiceCalculationService;
use App\Enums\PackageDiscountType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPackageInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:fix-package-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix historical package invoices by recalculating item discounts, HSN/SAC codes, taxable values, and GST amounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting historical package invoice data fix...');

        DB::beginTransaction();

        try {
            $invoices = Invoice::where('invoice_type', 'package')->with('items.product', 'package.items')->get();
            $count = 0;

            $calculationService = new InvoiceCalculationService();

            foreach ($invoices as $invoice) {
                $this->info("Recalculating invoice #{$invoice->id}: {$invoice->invoice_number}...");

                $package = $invoice->package;
                if (!$package) {
                    $this->warn("Invoice #{$invoice->id} does not have a linked package. Skipping.");
                    continue;
                }

                $discountType = $package->discount_type instanceof PackageDiscountType
                    ? $package->discount_type->value
                    : ($package->discount_type ?? 'flat');

                $subtotalTotal = 0;
                $discountTotal = 0;
                $taxableValueTotal = 0;
                $gstTotal = 0;
                $grandTotal = 0;

                foreach ($invoice->items as $item) {
                    $packageItem = $package->items->where('service_id', $item->product_id)->first();

                    if ($packageItem) {
                        if ($discountType === 'percentage') {
                            $itemDiscountType = 'percentage';
                            $itemDiscountValue = (float) ($package->discount_value ?? 0);
                        } else {
                            $itemDiscountType = 'flat';
                            $itemDiscountValue = $package->subtotal > 0
                                ? round(($packageItem->total_amount / $package->subtotal) * ($package->discount_value ?? 0), 2)
                                : 0.0;
                        }
                    } else {
                        $itemDiscountType = $item->discount_type ?? 'flat';
                        $itemDiscountValue = (float) ($item->discount_value ?? 0);
                    }

                    $gstPercentage = $item->gst_percentage ?? ($item->product ? ($item->product->gst ?? 18) : 18);
                    
                    // Fallback to product HSN if empty
                    $hsnSacCode = $item->hsn_sac_code;
                    if (empty($hsnSacCode) && $item->product) {
                        $hsnSacCode = $item->product->hsn_sac_code;
                    }
                    $hsnSacCode = $hsnSacCode ?: ($item->product && $item->product->type === 'service' ? '999729' : '330499');

                    $metrics = $calculationService->calculateLineItem(
                        $item->quantity,
                        $item->unit_price,
                        $itemDiscountType,
                        $itemDiscountValue,
                        $gstPercentage
                    );

                    $item->update([
                        'hsn_sac_code' => $hsnSacCode,
                        'gst_percentage' => $gstPercentage,
                        'discount_type' => $itemDiscountType,
                        'discount_value' => $itemDiscountValue,
                        'valid_discount_amount' => $metrics['discount_amount'],
                        'taxable_value' => $metrics['taxable_value'],
                        'gst_amount' => $metrics['gst_amount'],
                        'line_total' => $metrics['line_total'],
                    ]);

                    $subtotalTotal += $metrics['gross_amount'];
                    $discountTotal += $metrics['discount_amount'];
                    $taxableValueTotal += $metrics['taxable_value'];
                    $gstTotal += $metrics['gst_amount'];
                    $grandTotal += $metrics['line_total'];
                }

                $invoice->update([
                    'subtotal' => $subtotalTotal,
                    'discount_total' => $discountTotal,
                    'taxable_value' => $taxableValueTotal,
                    'gst_total' => $gstTotal,
                    'grand_total' => $grandTotal,
                    'amount_due' => max(0, $grandTotal - ($invoice->amount_paid ?? 0)),
                ]);

                $invoice->recalculatePaymentStatus();
                $count++;
            }

            DB::commit();
            $this->info("Successfully recalculated and fixed {$count} package invoices.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}
