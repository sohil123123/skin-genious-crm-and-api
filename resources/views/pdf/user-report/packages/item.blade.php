@php
    use App\Services\UserReportPdfService as Report;
    use Illuminate\Support\Str;

    $total = $package->items->sum('quantity');
    $used = $package->items->sum('used_sessions');
    $paid = (float) $package->invoices->sum('amount_paid');
    $outstanding = max(0, (float) $package->final_amount - $paid);
    $usages = $package->items
        ->flatMap(fn($item) => $item->usages->map(fn($usage) => [$item, $usage]))
        ->sortByDesc(fn($pair) => $pair[1]->created_at)
        ->values();
    $discountType = Report::enumValue($package->discount_type);
    $trim = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<div class="record">
    <div class="keep">
    <div class="record-head">
        <table>
            <tr>
                <td class="index-badge">{{ $index + 1 }}</td><td style="width: 8px;"></td>
                <td>
                    <div class="record-title">{{ $package->package_name ?: 'Package #' . $package->id }}</div>
                    <div class="record-sub">Purchased {{ $package->created_at?->format('d M Y, h:i A') }}@if ($package->createdBy) &middot; by {{ $package->createdBy->name }}@endif</div>
                </td>
                <td class="text-right" style="width: 25%;">
                    <span class="chip chip-{{ $status['color'] }}">{{ $status['label'] }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="record-body">
        @include('pdf.user-report.partials.stats', ['stats' => [
            ['Sessions', "$used / $total", max(0, $total - $used) . ' remaining', 'teal'],
            ['Package amount', Report::money($package->final_amount), $package->discount_amount > 0 ? 'after ' . Report::money($package->discount_amount) . ' discount' : null, 'indigo'],
            ['Paid', Report::money($paid), null, 'green'],
            ['Outstanding', Report::money($outstanding), null, $outstanding > 0 ? 'red' : 'green'],
        ]])

        @include('pdf.user-report.partials.info-grid', ['rows' => [
            'Package ID' => '#' . $package->id,
            'Clinic' => $package->clinic?->name,
            'Purchased on' => $package->created_at?->format('d M Y'),
            'Expires on' => $package->expired_at
                ? $package->expired_at->format('d M Y') . ($package->isExpired() ? ' (expired)' : ' (' . $package->expired_at->diffForHumans() . ')')
                : 'No expiry',
            'Subtotal' => Report::money($package->subtotal),
            'Discount' => $discountType === 'percentage'
                ? $trim($package->discount_value) . '% (' . Report::money($package->discount_amount) . ')'
                : Report::money($package->discount_amount),
            'Final amount' => Report::money($package->final_amount),
            'Active' => $package->is_active ? 'Yes' : 'No',
            'Created by' => $package->createdBy?->name,
            'Last updated' => $package->updated_at?->format('d M Y, h:i A'),
        ]])

        @if (filled($package->notes))
            <div class="note">{!! nl2br(e($package->notes)) !!}</div>
        @endif
    </div>
    </div>
    <div class="record-body record-body-cont">

        <div class="keep">
        <div class="sub-title">Services included</div>
        @if ($package->items->isEmpty())
            <div class="empty">No services in this package.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>SKU</th>
                        <th class="text-right">Price / session</th>
                        <th class="text-center">Qty</th>
                        <th class="text-center">Used</th>
                        <th class="text-center">Left</th>
                        <th style="width: 16%;">Progress</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($package->items as $i => $item)
                        @php
                            $snapshot = $item->service_snapshot ?? [];
                            $pct = $item->quantity > 0 ? min(100, (int) round($item->used_sessions / $item->quantity * 100)) : 0;
                        @endphp
                        <tr class="{{ $i % 2 ? 'alt' : '' }}">
                            <td class="bold">
                                {{ $item->service?->name ?? ($snapshot['name'] ?? 'Service #' . $item->service_id) }}
                                @if (! empty($snapshot['gst']))<div class="small muted">GST {{ $trim($snapshot['gst']) }}%</div>@endif
                            </td>
                            <td class="small">{{ $item->service?->sku ?? ($snapshot['sku'] ?? '—') }}</td>
                            <td class="text-right nowrap">{{ Report::money($item->price_per_unit) }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-center">{{ $item->used_sessions }}</td>
                            <td class="text-center bold">{{ $item->getRemainingSessions() }}</td>
                            <td>
                                <div class="small">{{ $pct }}%</div>
                                {!! Report::progressBar($pct, $pct >= 100 ? '#6366f1' : '#10b981') !!}
                            </td>
                            <td class="text-right nowrap">{{ Report::money($item->total_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="text-center">{{ $total }}</td>
                        <td class="text-center">{{ $used }}</td>
                        <td class="text-center">{{ max(0, $total - $used) }}</td>
                        <td></td>
                        <td class="text-right nowrap">{{ Report::money($package->subtotal) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        </div>

        <div class="keep">
        <div class="sub-title">Session usage history</div>
        @if ($usages->isEmpty())
            <div class="empty">No sessions have been used from this package yet.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Recorded</th>
                        <th>Service</th>
                        <th class="text-center">Sessions</th>
                        <th>Appointment</th>
                        <th>Invoice</th>
                        <th>Recorded by</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usages as $i => [$item, $usage])
                        <tr class="{{ $i % 2 ? 'alt' : '' }}">
                            <td class="nowrap">{{ $usage->created_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ $item->service?->name ?? ($item->service_snapshot['name'] ?? '—') }}</td>
                            <td class="text-center bold">{{ $usage->sessions_used }}</td>
                            <td class="small">
                                @if ($usage->appointment)
                                    {{ $usage->appointment->start_datetime?->format('d M Y, h:i A') }}
                                    @if ($usage->appointment->therapist)<br><span class="muted">{{ $usage->appointment->therapist->name }}</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">{{ $usage->invoice?->invoice_number ?? '—' }}</td>
                            <td class="small">{{ $usage->recordedBy?->name ?? '—' }}</td>
                            <td class="small">{{ $usage->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        </div>

        <div class="keep">
        <div class="sub-title">Invoices &amp; payments</div>
        @if ($package->invoices->isEmpty())
            <div class="empty">No invoices raised for this package.</div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($package->invoices as $i => $invoice)
                        <tr class="{{ $i % 2 ? 'alt' : '' }}">
                            <td class="bold">{{ $invoice->invoice_number ?? '#' . $invoice->id }}</td>
                            <td class="nowrap">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                            <td>{{ $invoice->payment_mode ? Str::headline($invoice->payment_mode) : '—' }}</td>
                            <td class="text-right nowrap">{{ Report::money($invoice->grand_total) }}</td>
                            <td class="text-right nowrap">{{ Report::money($invoice->amount_paid) }}</td>
                            <td class="text-right nowrap">{{ Report::money($invoice->amount_due) }}</td>
                            <td><span class="chip chip-{{ Report::statusColor($invoice->status) }}">{{ Str::headline($invoice->status ?? '—') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </div>
</div>
