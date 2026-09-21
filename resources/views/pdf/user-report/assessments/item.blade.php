@php
    use App\Services\UserReportPdfService as Report;
    use App\Support\Pdf\StructuredDataHtml as Data;
    use Illuminate\Support\Str;

    $yesNo = fn($value) => $value === null || $value === '' ? null : (in_array(strtolower((string) $value), ['1', 'true', 'yes'], true) ? 'Yes' : (in_array(strtolower((string) $value), ['0', 'false', 'no'], true) ? 'No' : Str::headline((string) $value)));
    $temp = fn($value) => filled($value) ? $value . ' °C' : null;
    $sessions = $assessment->getRelation('treatmentSessions')->sortBy('session_number');
@endphp

@if ($index > 0)
    <pagebreak />
@endif

<div class="record">
    <div class="keep">
    <div class="record-head">
        <table>
            <tr>
                <td class="index-badge">{{ $index + 1 }}</td><td style="width: 8px;"></td>
                <td>
                    <div class="record-title">{{ Str::title($assessment->name ?? 'Assessment #' . $assessment->id) }}</div>
                    <div class="record-sub">
                        {{ Report::assessmentTypeLabel($assessment->assessment_type) }} assessment &middot;
                        {{ $assessment->created_at?->format('d M Y, h:i A') }}
                        @if ($assessment->createdBy) &middot; by {{ $assessment->createdBy->name }}@endif
                    </div>
                </td>
                <td class="text-right" style="width: 25%;">
                    <span class="chip chip-{{ Report::statusColor(Report::enumValue($assessment->status)) }}">{{ Report::enumLabel($assessment->status) }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="record-body">
        <div class="sub-title">Assessment details</div>
        @include('pdf.user-report.partials.info-grid', ['rows' => [
            'Assessment ID' => '#' . $assessment->id,
            'Reference' => $assessment->assessment_id,
            'Type' => Report::assessmentTypeLabel($assessment->assessment_type),
            'Status' => Report::enumLabel($assessment->status),
            'Clinic' => $assessment->clinic?->name,
            'Conducted by' => $assessment->createdBy?->name,
            'Created' => $assessment->created_at?->format('d M Y, h:i A'),
            'Last updated' => $assessment->updated_at?->format('d M Y, h:i A'),
            'Follow-up of' => $assessment->parentAssessment ? Str::title($assessment->parentAssessment->name) . ' (' . $assessment->parentAssessment->created_at?->format('d M Y') . ')' : null,
            'Selected plan' => $assessment->selected_plan_type ? Report::enumLabel($assessment->selected_plan_type) : null,
            'Total treatment time' => Report::minutes($assessment->total_time),
            'Treatment sessions' => $sessions->count() ?: null,
        ]])
    </div>
    </div>
    <div class="record-body record-body-cont">

        <div class="keep">
        <div class="sub-title">Intake &amp; safety screening</div>
        @include('pdf.user-report.partials.info-grid', ['rows' => [
            'Age at assessment' => $assessment->age,
            'Daily sun exposure' => filled($assessment->daily_sun_exposure_hours) ? $assessment->daily_sun_exposure_hours . ' hrs' : null,
            'Upcoming social event' => $yesNo($assessment->social_event),
            'Upcoming travel' => $yesNo($assessment->upcoming_travel),
            'Recent peel / laser' => $yesNo($assessment->recent_peel_or_laser),
            'Retinol used last night' => $yesNo($assessment->retinol_used_last_night),
            'Pregnant' => $assessment->is_pregnant === null ? null : ($assessment->is_pregnant ? 'Yes' : 'No'),
            'Breastfeeding' => $yesNo($assessment->breastfeeding),
            'Forehead temperature' => $temp($assessment->skin_temp_for_head),
            'Left cheek temperature' => $temp($assessment->left_cheek_temp),
            'Right cheek temperature' => $temp($assessment->right_cheek_temp),
        ]])

        @if (! Data::isEmpty($assessment->medical_history) || ! Data::isEmpty($assessment->allergies))
            <table class="info-grid">
                @if (! Data::isEmpty($assessment->medical_history))
                    <tr><td class="label">Medical history</td><td class="value" colspan="3">{!! Data::render($assessment->medical_history, 1) !!}</td></tr>
                @endif
                @if (! Data::isEmpty($assessment->allergies))
                    <tr><td class="label">Allergies</td><td class="value" colspan="3">{!! Data::render($assessment->allergies, 1) !!}</td></tr>
                @endif
            </table>
        @endif

        @if (filled($assessment->therapist_notes))
            <div class="note note-indigo"><b>Therapist notes:</b><br>{!! nl2br(e($assessment->therapist_notes)) !!}</div>
        @endif
        </div>

        @include('pdf.user-report.partials.photos', ['photos' => $preImages, 'title' => 'Assessment photos'])
        @include('pdf.user-report.partials.photos', ['photos' => $postImages, 'title' => 'Post-treatment photos'])

        @if ($sessions->isNotEmpty())
            <div class="keep">
            <div class="sub-title">Treatment sessions in this plan</div>
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Session</th><th>Week</th><th>Duration</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $i => $session)
                        <tr class="{{ $loop->odd ? '' : 'alt' }}">
                            <td>{{ $session->session_number }}</td>
                            <td>{{ $session->title ?: '—' }}</td>
                            <td>{{ $session->week ? 'Week ' . $session->week : '—' }}</td>
                            <td class="nowrap">{{ Report::minutes($session->treatment_time) ?? '—' }}</td>
                            <td><span class="chip chip-{{ Report::statusColor($session->status) }}">{{ Str::headline($session->status ?? 'pending') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</div>

@foreach ($clinical as $heading => $content)
    {!! Data::section($heading, $content) !!}
@endforeach
