@php use Illuminate\Support\Str; @endphp

<h2 class="block-title" style="margin-top: 16px;">Package Session Usage Log</h2>

@if ($packageUsages->isEmpty())
    <div class="empty">No package sessions have been used yet.</div>
@else
    <table class="data-table">
        <thead>
            <tr>
                <th>Recorded</th>
                <th>Package</th>
                <th>Service</th>
                <th class="text-center">Sessions</th>
                <th>Appointment</th>
                <th>Invoice</th>
                <th>Recorded by</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($packageUsages as $i => $usage)
                @php $item = $usage->packageItem; @endphp
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td class="nowrap">{{ $usage->created_at?->format('d M Y, h:i A') }}</td>
                    <td class="bold">{{ $item?->package?->package_name ?? '—' }}</td>
                    <td>{{ $item?->service?->name ?? ($item?->service_snapshot['name'] ?? '—') }}</td>
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
        <tfoot>
            <tr>
                <td colspan="3">Total sessions used</td>
                <td class="text-center">{{ $packageUsages->sum('sessions_used') }}</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
@endif
