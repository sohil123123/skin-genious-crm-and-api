@php
    use App\Services\UserReportPdfService as Report;
    use Illuminate\Support\Str;

    $trim = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<div class="record" style="margin-top: 10px;">
    <div class="keep">
    <div class="record-head">
        <table>
            <tr>
                <td class="index-badge" style="background-color: #059669;">{{ $index + 1 }}</td><td style="width: 8px;"></td>
                <td>
                    <div class="record-title">Invoice {{ $invoice->invoice_number ?? '#' . $invoice->id }}</div>
                    <div class="record-sub">
                        {{ $invoice->invoice_date?->format('d M Y, h:i A') }}
                        @if ($invoice->clinic) &middot; {{ $invoice->clinic->name }}@endif
                        @if ($invoice->package) &middot; Package: {{ $invoice->package->package_name }}@endif
                    </div>
                </td>
                <td class="text-right" style="width: 22%;">
                    <span class="chip chip-{{ Report::statusColor($invoice->status) }}">{{ Str::headline($invoice->status ?? '—') }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="record-body">
        @include('pdf.user-report.partials.info-grid', ['rows' => [
            'Invoice number' => $invoice->invoice_number ?? '#' . $invoice->id,
            'Invoice date' => $invoice->invoice_date?->format('d M Y'),
            'Invoice type' => $invoice->invoice_type ? Str::headline($invoice->invoice_type) : null,
            'Payment mode' => $invoice->payment_mode ? Str::headline($invoice->payment_mode) : null,
            'Clinic' => $invoice->clinic?->name,
            'Clinic GSTIN' => $invoice->clinic?->gst_number,
            'Package' => $invoice->package?->package_name,
            'Source note' => $invoice->source_note,
        ]])
    </div>
    </div>
    <div class="record-body record-body-cont">

        <div class="keep">
        <div class="sub-title">Items</div>
        @if ($invoice->items->isEmpty())
            <div class="empty">No line items on this invoice.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>HSN/SAC</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Taxable</th>
                        <th class="text-right">GST</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $i => $item)
                        <tr class="{{ $i % 2 ? 'alt' : '' }}">
                            <td class="bold">{{ $item->product?->name ?? 'Item #' . $item->product_id }}</td>
                            <td class="small">{{ $item->hsn_sac_code ?: '—' }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right nowrap">{{ Report::money($item->unit_price) }}</td>
                            <td class="text-right nowrap">
                                @if ((float) $item->valid_discount_amount > 0)
                                    {{ Report::money($item->valid_discount_amount) }}
                                    @if ($item->discount_type === 'percentage')<div class="small muted">{{ $trim($item->discount_value) }}%</div>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right nowrap">{{ Report::money($item->taxable_value) }}</td>
                            <td class="text-right nowrap">
                                {{ Report::money($item->gst_amount) }}
                                <div class="small muted">{{ $trim($item->gst_percentage) }}%</div>
                            </td>
                            <td class="text-right nowrap bold">{{ Report::money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- One flat table, right-aligned by an empty first column: a table
             nested in a cell leaves mPDF painting a stray strip when the
             block is moved to the next page. --}}
        @php $gstRates = $invoice->items->groupBy(fn($item) => $trim($item->gst_percentage)); @endphp
        <table class="totals">
            <tr><td style="width: 48%;"></td><td class="t-label">Subtotal</td><td class="t-value">{{ Report::money($invoice->subtotal) }}</td></tr>
            @if ((float) $invoice->discount_total > 0)
                <tr><td></td><td class="t-label">Discount</td><td class="t-value">− {{ Report::money($invoice->discount_total) }}</td></tr>
            @endif
            <tr><td></td><td class="t-label">Taxable value</td><td class="t-value">{{ Report::money($invoice->taxable_value) }}</td></tr>
            @if ($gstRates->count() > 1)
                @foreach ($gstRates as $rate => $rows)
                    <tr><td></td><td class="t-label">GST {{ $rate }}% on {{ Report::money($rows->sum('taxable_value')) }}</td><td class="t-value">{{ Report::money($rows->sum('gst_amount')) }}</td></tr>
                @endforeach
            @endif
            <tr><td></td><td class="t-label">GST{{ $gstRates->count() === 1 ? ' @ ' . $gstRates->keys()->first() . '%' : '' }}</td><td class="t-value">{{ Report::money($invoice->gst_total) }}</td></tr>
            <tr class="grand"><td></td><td class="t-label">Grand total</td><td class="t-value">{{ Report::money($invoice->grand_total) }}</td></tr>
            <tr><td></td><td class="t-label">Amount paid</td><td class="t-value" style="color: #047857;">{{ Report::money($invoice->amount_paid) }}</td></tr>
            <tr><td></td><td class="t-label">Balance due</td><td class="t-value" style="color: {{ (float) $invoice->amount_due > 0 ? '#b91c1c' : '#047857' }};">{{ Report::money($invoice->amount_due) }}</td></tr>
        </table>

        </div>

        <div class="keep">
        <div class="sub-title">Payment history</div>
        @if ($invoice->payments->isEmpty())
            <div class="empty">No payments recorded against this invoice.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Transaction ID</th>
                        <th>Received by</th>
                        <th>Notes</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $i => $payment)
                        <tr class="{{ $i % 2 ? 'alt' : '' }}">
                            <td class="nowrap">{{ $payment->payment_date?->format('d M Y') }}</td>
                            <td>{{ $payment->payment_method ? Str::headline($payment->payment_method) : '—' }}</td>
                            <td class="small">{{ $payment->reference_number ?: '—' }}</td>
                            <td class="small">{{ $payment->transaction_id ?: '—' }}</td>
                            <td class="small">{{ $payment->creator?->name ?? '—' }}</td>
                            <td class="small">{{ $payment->notes ?: '—' }}</td>
                            <td class="text-right nowrap bold">{{ Report::money($payment->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">Total received</td>
                        <td class="text-right nowrap">{{ Report::money($invoice->payments->sum('amount')) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
        </div>
    </div>
</div>
