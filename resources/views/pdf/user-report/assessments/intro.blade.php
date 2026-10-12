@php use App\Services\UserReportPdfService as Report; @endphp

@include('pdf.user-report.partials.section-banner', [
    'key' => 'assessments',
    'subtitle' => $assessments->count() . ' ' . \Illuminate\Support\Str::plural('assessment', $assessments->count()) . ' — intake, photos, diagnosis, treatment plan and scan metrics',
])

@if ($assessments->isEmpty())
    <div class="empty">No assessments have been recorded for this client yet.</div>
@else
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Assessment</th>
                <th>Type</th>
                <th>Date</th>
                <th>Plan</th>
                <th class="text-center">Sessions</th>
                <th>By</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assessments as $i => $assessment)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td class="bold">{{ \Illuminate\Support\Str::title($assessment->name ?? 'Assessment #' . $assessment->id) }}</td>
                    <td><span class="chip chip-purple">{{ Report::assessmentTypeLabel($assessment->assessment_type) }}</span></td>
                    <td class="nowrap">{{ $assessment->created_at?->format('d M Y') }}</td>
                    <td>{{ Report::enumLabel($assessment->selected_plan_type) }}</td>
                    <td class="text-center">{{ $assessment->getRelation('treatmentSessions')->count() }}</td>
                    <td class="small">{{ $assessment->createdBy?->name ?? '—' }}</td>
                    <td><span class="chip chip-{{ Report::statusColor(Report::enumValue($assessment->status)) }}">{{ Report::enumLabel($assessment->status) }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
