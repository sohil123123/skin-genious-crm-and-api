<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class InvoiceExport extends SelectableColumnsExport
{
    public static function relationsToLoad(): array
    {
        return ['client', 'clinic', 'package'];
    }

    public static function columnDefinitions(): array
    {
        return [
            'invoice_number' => [
                'label' => 'Invoice #',
                'value' => fn (Invoice $invoice): string => (string) $invoice->invoice_number,
                'type' => 'text',
                'width' => 18,
            ],
            'invoice_date' => [
                'label' => 'Invoice Date',
                'value' => fn (Invoice $invoice): string => $invoice->invoice_date
                    ? Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : '',
                'type' => 'text',
                'width' => 14,
            ],
            'invoice_type' => [
                'label' => 'Type',
                'value' => fn (Invoice $invoice): string => ucfirst((string) $invoice->invoice_type),
                'type' => 'text',
                'width' => 12,
            ],
            'status' => [
                'label' => 'Status',
                'value' => fn (Invoice $invoice): string => ucfirst((string) $invoice->status),
                'type' => 'text',
                'width' => 12,
            ],
            'clinic' => [
                'label' => 'Clinic',
                'value' => fn (Invoice $invoice): string => $invoice->clinic?->name ?? '',
                'type' => 'text',
                'width' => 22,
            ],
            'client' => [
                'label' => 'Client',
                'value' => fn (Invoice $invoice): string => $invoice->client?->name ?? 'N/A',
                'type' => 'text',
                'width' => 22,
            ],
            'client_mobile' => [
                'label' => 'Client Mobile',
                'value' => fn (Invoice $invoice): string => (string) ($invoice->client?->mobile ?? ''),
                'type' => 'text',
                'width' => 15,
            ],
            'client_email' => [
                'label' => 'Client Email',
                'value' => fn (Invoice $invoice): string => (string) ($invoice->client?->email ?? ''),
                'type' => 'text',
                'width' => 24,
            ],
            'client_state' => [
                'label' => 'State',
                'value' => fn (Invoice $invoice): string => (string) ($invoice->client?->state ?? $invoice->clinic?->state ?? ''),
                'type' => 'text',
                'width' => 10,
            ],
            'package_name' => [
                'label' => 'Package Ref',
                'value' => fn (Invoice $invoice): string => $invoice->package?->package_name ?? '',
                'type' => 'text',
                'width' => 22,
            ],
            'payment_mode' => [
                'label' => 'Payment Mode',
                'value' => fn (Invoice $invoice): string => $invoice->payment_mode
                    ? ucfirst(str_replace('_', ' ', (string) $invoice->payment_mode))
                    : '',
                'type' => 'text',
                'width' => 15,
            ],
            'source_note' => [
                'label' => 'Source Note',
                'value' => fn (Invoice $invoice): string => (string) ($invoice->source_note ?? ''),
                'type' => 'text',
                'width' => 28,
            ],
            'subtotal' => [
                'label' => 'Subtotal',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->subtotal, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'discount_total' => [
                'label' => 'Discount',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->discount_total, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'taxable_value' => [
                'label' => 'Taxable Amount',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->taxable_value, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 16,
            ],
            'gst_total' => [
                'label' => 'GST Amount',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->gst_total, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'grand_total' => [
                'label' => 'Invoice Total',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->grand_total, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 16,
            ],
            'amount_paid' => [
                'label' => 'Amount Paid',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->amount_paid, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'amount_due' => [
                'label' => 'Amount Due',
                'value' => fn (Invoice $invoice): float => round((float) $invoice->amount_due, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'created_at' => [
                'label' => 'Created At',
                'value' => fn (Invoice $invoice): string => $invoice->created_at?->format('d-m-Y H:i') ?? '',
                'type' => 'text',
                'width' => 18,
            ],
        ];
    }

    public function title(): string
    {
        return $this->reportTitle ?: 'Invoices';
    }
}
