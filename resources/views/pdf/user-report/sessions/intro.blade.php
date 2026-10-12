@php
    use App\Services\UserReportPdfService as Report;
    use Illuminate\Support\Str;
@endphp

@include('pdf.user-report.partials.section-banner', [
    'key' => 'sessions',
    'subtitle' => $sessions->count() . ' treatment ' . Str::plural('session', $sessions->count()) . ' — protocol, steps, home care, results, plus package session usage',
])

@if ($sessions->isEmpty())
    <div class="empty">No treatment sessions have been planned for this client yet.</div>
@else
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Session</th>
                <th>Assessment</th>
                <th>Plan</th>
                <th>Week</th>
                <th>Duration</th>
                <th>Appointment</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sessions as $i => $session)
                @php $appointment = $sessionAppointments->get($session->id)?->first(); @endphp
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td class="bold">{{ Str::limit($session->title ?: 'Session ' . $session->session_number, 70) }}</td>
                    <td class="small">{{ Str::title($session->assessment?->name ?? '—') }}</td>
                    <td class="small">{{ Str::headline($session->plan_type ?? '—') }}</td>
                    <td>{{ $session->week ?: '—' }}</td>
                    <td class="nowrap">{{ Report::minutes($session->treatment_time) ?? '—' }}</td>
                    <td class="small nowrap">{{ $appointment?->start_datetime?->format('d M Y') ?? '—' }}</td>
                    <td><span class="chip chip-{{ Report::statusColor($session->status) }}">{{ Str::headline($session->status ?? 'pending') }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
