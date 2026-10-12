@php use App\Services\UserReportPdfService as Report; @endphp

@include('pdf.user-report.partials.section-banner', [
    'key' => 'packages',
    'subtitle' => $packages->count() . ' ' . \Illuminate\Support\Str::plural('package', $packages->count()) . ' purchased — services, session balance, payments and usage history',
])

@if ($packages->isEmpty())
    <div class="empty">No packages have been purchased by this client yet.</div>
@else
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Package</th>
                <th>Purchased</th>
                <th>Expires</th>
                <th class="text-center">Sessions</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($packages as $i => $package)
                @php $status = $report->packageStatus($package); @endphp
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td class="bold">{{ $package->package_name ?: 'Package #' . $package->id }}</td>
                    <td class="nowrap">{{ $package->created_at?->format('d M Y') }}</td>
                    <td class="nowrap">{{ $package->expired_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-center">{{ $package->items->sum('used_sessions') }} / {{ $package->items->sum('quantity') }}</td>
                    <td class="text-right nowrap">{{ Report::money($package->final_amount) }}</td>
                    <td class="text-right nowrap">{{ Report::money($package->invoices->sum('amount_paid')) }}</td>
                    <td><span class="chip chip-{{ $status['color'] }}">{{ $status['label'] }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
