@php
    use App\Services\UserReportPdfService as Report;
    use Illuminate\Support\Str;
@endphp

@include('pdf.user-report.partials.section-banner', [
    'key' => 'invoices',
    'subtitle' => $invoices->count() . ' ' . Str::plural('invoice', $invoices->count()) . ' — line items, GST breakdown and payment history',
])

@if ($invoices->isEmpty())
    <div class="empty">No invoices have been raised for this client yet.</div>
@else
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Invoice</th>
                <th>Date</th>
                <th>Type</th>
                <th class="text-right">Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoices as $i => $invoice)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td class="bold">{{ $invoice->invoice_number ?? '#' . $invoice->id }}</td>
                    <td class="nowrap">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                    <td class="small">{{ Str::headline($invoice->invoice_type ?? '—') }}</td>
                    <td class="text-right nowrap">{{ Report::money($invoice->grand_total) }}</td>
                    <td class="text-right nowrap">{{ Report::money($invoice->amount_paid) }}</td>
                    <td class="text-right nowrap">{{ Report::money($invoice->amount_due) }}</td>
                    <td><span class="chip chip-{{ Report::statusColor($invoice->status) }}">{{ Str::headline($invoice->status ?? '—') }}</span></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total (excluding cancelled)</td>
                <td class="text-right nowrap">{{ Report::money($summary['invoices']['total']) }}</td>
                <td class="text-right nowrap">{{ Report::money($summary['invoices']['paid']) }}</td>
                <td class="text-right nowrap">{{ Report::money($summary['invoices']['due']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endif
