<div class="collection-payments-container">
    @if ($payments->isEmpty())
        <div class="collection-payments-empty">
            No transaction records found for this payment method in the selected period.
        </div>
    @else
        <div class="collection-payments-table-container">
            <table class="collection-payments-table">
                <thead>
                    <tr>
                        <th scope="col" class="collection-payments-th" style="text-align: left;">Date</th>
                        <th scope="col" class="collection-payments-th" style="text-align: left;">Invoice #</th>
                        <th scope="col" class="collection-payments-th" style="text-align: left;">Client Details</th>
                        <th scope="col" class="collection-payments-th" style="text-align: left;">Ref / TXN ID</th>
                        <th scope="col" class="collection-payments-th" style="text-align: left;">Notes</th>
                        <th scope="col" class="collection-payments-th" style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr class="collection-payments-tr">
                            <td class="collection-payments-td" style="text-align: left; white-space: nowrap;">
                                {{ $payment->payment_date ? $payment->payment_date->format('d-m-Y') : 'N/A' }}
                            </td>
                            <td class="collection-payments-td collection-payments-invoice" style="text-align: left; white-space: nowrap;">
                                @if ($payment->invoice)
                                    <a href="{{ route('filament.admin.resources.invoices.edit', ['record' => $payment->invoice]) }}" class="collection-payments-link" target="_blank">
                                        #{{ $payment->invoice->invoice_number }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="collection-payments-td" style="text-align: left;">
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <span class="collection-payments-client-name">{{ $payment->invoice?->client?->name ?: 'N/A' }}</span>
                                    @if ($payment->invoice?->client?->mobile)
                                        <span style="font-size: 0.75rem; opacity: 0.8;">{{ $payment->invoice->client->mobile }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="collection-payments-td" style="text-align: left;">
                                <span class="collection-payments-ref-badge">
                                    {{ $payment->reference_number ?: '-' }}
                                </span>
                            </td>
                            <td class="collection-payments-td collection-payments-notes" style="text-align: left; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $payment->notes }}">
                                {{ $payment->notes ?: '-' }}
                            </td>
                            <td class="collection-payments-td collection-payments-amount" style="text-align: right; font-weight: 700; white-space: nowrap;">
                                ₹{{ number_format($payment->amount, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
    .collection-payments-container {
        width: 100%;
        padding: 0.5rem 0;
    }

    .collection-payments-empty {
        padding: 2rem;
        text-align: center;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .dark .collection-payments-empty {
        color: #9ca3af;
    }

    .collection-payments-table-container {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        background-color: #ffffff;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }

    .dark .collection-payments-table-container {
        border-color: #374151;
        background-color: #111827;
    }

    .collection-payments-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .collection-payments-th {
        background-color: #f9fafb;
        color: #374151;
        font-weight: 600;
        padding: 14px 20px;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e5e7eb;
    }

    .dark .collection-payments-th {
        background-color: #1f2937;
        color: #9ca3af;
        border-bottom-color: #374151;
    }

    .collection-payments-tr {
        border-bottom: 1px solid #e5e7eb;
        transition: background-color 0.15s ease-in-out;
    }

    .dark .collection-payments-tr {
        border-bottom-color: #374151;
    }

    .collection-payments-tr:last-child {
        border-bottom: none;
    }

    .collection-payments-tr:hover {
        background-color: #f9fafb;
    }

    .dark .collection-payments-tr:hover {
        background-color: #1f2937;
    }

    .collection-payments-td {
        padding: 14px 20px;
        color: #4b5563;
        vertical-align: middle;
    }

    .dark .collection-payments-td {
        color: #d1d5db;
    }

    .collection-payments-client-name {
        font-weight: 600;
        color: #111827;
    }

    .dark .collection-payments-client-name {
        color: #ffffff;
    }

    .collection-payments-link {
        font-weight: 600;
        color: #3b82f6;
        text-decoration: none;
        transition: color 0.15s ease-in-out;
    }

    .collection-payments-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    .dark .collection-payments-link {
        color: #60a5fa;
    }

    .dark .collection-payments-link:hover {
        color: #93c5fd;
    }

    .collection-payments-ref-badge {
        font-family: monospace;
        font-size: 0.8rem;
        background-color: #f3f4f6;
        color: #374151;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #e5e7eb;
    }

    .dark .collection-payments-ref-badge {
        background-color: #1f2937;
        color: #d1d5db;
        border-color: #374151;
    }

    .collection-payments-notes {
        color: #6b7280;
        font-style: italic;
    }

    .dark .collection-payments-notes {
        color: #9ca3af;
    }

    .collection-payments-amount {
        color: #111827;
    }

    .dark .collection-payments-amount {
        color: #ffffff;
    }
</style>
