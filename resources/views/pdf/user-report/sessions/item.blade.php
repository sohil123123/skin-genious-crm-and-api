@php
    use App\Services\UserReportPdfService as Report;
    use App\Support\Pdf\StructuredDataHtml as Data;
    use Illuminate\Support\Str;

    $blocks = array_filter([
        'Therapist preparation checklist' => $session->preparations_checklist_for_therapist,
        'Concerns addressed' => $session->concerns_addressed,
        'Treatment steps' => $session->steps,
        'Daily home care routine' => $session->daily_home_care_routine,
        'IV preparation' => $session->iv_prep_data,
        'IV session' => $ivSession,
        'Post-treatment diagnosis' => $session->post_diagnosis,
        'Post-treatment scan metrics' => $session->post_feature_packet,
    ], fn($value) => ! Data::isEmpty($value));
@endphp

<div class="record" style="margin-top: 10px;">
    <div class="keep">
    <div class="record-head">
        <table>
            <tr>
                <td class="index-badge" style="background-color: #0d9488;">{{ $index + 1 }}</td><td style="width: 8px;"></td>
                <td>
                    <div class="record-title">{{ $session->title ?: 'Session ' . $session->session_number }}</div>
                    <div class="record-sub">
                        Session {{ $session->session_number }}
                        @if ($session->week) &middot; Week {{ $session->week }}@endif
                        @if ($session->assessment) &middot; {{ Str::title($session->assessment->name) }}@endif
                    </div>
                </td>
                <td class="text-right" style="width: 22%;">
                    <span class="chip chip-{{ Report::statusColor($session->status) }}">{{ Str::headline($session->status ?? 'pending') }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="record-body">
        @include('pdf.user-report.partials.info-grid', ['rows' => [
            'Session ID' => '#' . $session->id,
            'Session number' => $session->session_number,
            'Plan type' => $session->plan_type ? Str::headline($session->plan_type) : null,
            'Week' => $session->week,
            'Treatment time' => Report::minutes($session->treatment_time),
            'Status' => Str::headline($session->status ?? 'pending'),
            'Assessment' => $session->assessment ? Str::title($session->assessment->name) . ' (' . Report::assessmentTypeLabel($session->assessment->assessment_type) . ')' : null,
            'Assessment date' => $session->assessment?->created_at?->format('d M Y'),
            'Planned on' => $session->created_at?->format('d M Y, h:i A'),
            'Last updated' => $session->updated_at?->format('d M Y, h:i A'),
        ]])
    </div>
    </div>
    <div class="record-body record-body-cont">

        @if ($appointments->isNotEmpty())
            <div class="keep">
            <div class="sub-title">Appointments</div>
            <table class="data-table">
                <thead><tr><th>Date &amp; time</th><th>Duration</th><th>Therapist</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                    @foreach ($appointments as $appointment)
                        <tr>
                            <td class="nowrap">{{ $appointment->start_datetime?->format('d M Y, h:i A') }}</td>
                            <td class="nowrap">{{ $appointment->duration_minutes ? $appointment->duration_minutes . ' min' : '—' }}</td>
                            <td>{{ $appointment->therapist?->name ?? '—' }}</td>
                            <td><span class="chip chip-{{ Report::statusColor(Report::enumValue($appointment->status)) }}">{{ Report::enumLabel($appointment->status) }}</span></td>
                            <td class="small">{{ $appointment->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif

        @foreach ($blocks as $heading => $content)
            {!! Data::section($heading, $content, 'background-color: #0f766e;') !!}
        @endforeach

        @if (filled($session->audio_text))
            <div class="note note-teal keep"><b>Therapist script:</b><br>{!! nl2br(e($session->audio_text)) !!}</div>
        @endif

        @include('pdf.user-report.partials.photos', ['photos' => $postImages, 'title' => 'Post-treatment photos'])
    </div>
</div>
