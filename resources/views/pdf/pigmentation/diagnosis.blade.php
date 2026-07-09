@extends('pdf.master')

@section('content')

<style>
    /* ─── Tech Sections ─── */
    .section-title-table {
        width: 100%;
        margin-bottom: 15px;
        background-color: #0E2B5C;
    }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #FFFFFF;
        text-transform: uppercase;
        letter-spacing: 2px;
        padding: 8px 15px;
    }

    .overview-text {
        font-size: 14px;
        color: #4A5568;
        line-height: 1.5;
        text-align: justify;
        margin-bottom: 30px;
        padding: 0 5px;
    }

    /* ─── Diagnosis Cards ─── */
    .section-label {
        font-size: 12px;
        color: #C29F5D;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 6px;
        border-bottom: 1px solid #E2E8F0;
        padding-bottom: 10px;
        line-height: 1.5;
    }
    .section-text {
        font-size: 12px;
        color: #4A5568;
        line-height: 1.5;
        text-align: justify;
        margin-bottom: 15px;
    }
    .section-card {
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px;
        margin-top: 10px;
    }
    .section-header {
        background: #0E2B5C;
        color: #fff;
        font-size: 16px;
        font-weight: bold;
        padding: 12px 16px;
        border-radius: 8px;
    }
    .divider {
        border-left: 1px solid #E5E7EB;
    }
    .score-circle {
        width: 50px;
        text-align: center;
        vertical-align: middle;
        border: 4px solid #E0E7FF;
        border-radius: 50%;
        padding: 5px;
    }
    .score-inner {
        font-size: 30px;
        font-weight: bold;
        color: #0E2B5C;
    }
    .img-box {
        background: #FFFFFF;
        border: 1px dashed #D1D5DB;
        padding: 10px;
        text-align: center;
        border-radius: 8px;
    }
    .img-box img {
        max-width: 100%;
        height: 150px;
        display: inline-block;
    }
</style>

@php
    $patient = $record->user;
    $diagnosis = $record->diagnosis ?? [];
    $impression = $diagnosis['working_impression'] ?? [];
    $scores = $diagnosis['scores'] ?? [];
    
    $primaryDx = $diagnosis['differential']['primary']['dx'] ?? $impression['primary_category'] ?? $impression['primary_impression'] ?? 'N/A';
    $primaryDxLabel = ucwords(str_replace('_', ' ', $primaryDx));
    
    $confidence = $diagnosis['differential']['primary']['confidence'] ?? $impression['primary_confidence_100'] ?? 80;
    $pigmentationType = $diagnosis['depth_assessment']['verdict'] ?? $impression['pigmentation_type'] ?? 'N/A';
    
    $melaninIndex = $scores['melanin_load_index'] ?? $scores['melanin_index'] ?? '—';
    $erythemaIndex = $scores['erythema_load_index'] ?? $scores['erythema_index'] ?? '—';
    
    $age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A';
    $gender = $patient->gender ? strtoupper(substr($patient->gender, 0, 1)) : 'N/A';
    
    $alternatives = $diagnosis['differential']['alternatives'] ?? $diagnosis['differential_diagnosis'] ?? [];
@endphp

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">PIGMENTATION DIAGNOSTIC REPORT</div>
        <div class="report-subtitle">AI-Powered Clinical Diagnostics</div>
    </div>

    {{-- ── Patient Info Cards ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">PATIENT NAME</div>
                    <div class="info-value">{{ $patient->name }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">AGE / GENDER</div>
                    <div class="info-value">{{ $age }} / {{ $gender }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Report Date</div>
                    <div class="info-value">{{ $record->created_at->format('d/m/Y') }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">CLINIC</div>
                    <div class="info-value">{{ $record->clinic->name ?? 'Main Clinic' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Assessment Overview ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">ASSESSMENT OVERVIEW</td>
        </tr>
    </table>

    <div class="overview-text">
        {!! preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', e($diagnosis['clinical_summary_for_doctor'] ?? 'N/A')) !!}
    </div>

    {{-- ── Clinical Metrics Table ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">CLINICAL METRICS & SEVERITY INDICES</td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 15px; border: 1px solid #E2E8F0; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
            <tr style="color: #0E2B5C;">
                <th align="left" style="padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #C29F5D; border-right: 1px solid #E2E8F0;">Parameter name</th>
                <th align="center" style="padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #C29F5D; border-right: 1px solid #E2E8F0;">Value / Score</th>
                <th align="left" style="padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #C29F5D;">Interpretation</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background-color: #FFFFFF;">
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Melanin Index</td>
                <td align="center" style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; color: #0E2B5C; font-weight: bold; font-size: 14px;">{{ $melaninIndex }}</td>
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; color: #4A5568;">Measures overall pigment distribution and melanin load density on scanning.</td>
            </tr>
            <tr style="background-color: #F8FAFC;">
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Erythema Index</td>
                <td align="center" style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; color: #0E2B5C; font-weight: bold; font-size: 14px;">{{ $erythemaIndex }}</td>
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; color: #4A5568;">Measures micro-vascular redness or baseline inflammation in skin regions.</td>
            </tr>
            <tr style="background-color: #FFFFFF;">
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Wood's Lamp Accentuation</td>
                <td align="center" style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; color: #0E2B5C; font-weight: bold; font-size: 14px;">{{ !empty($scores['wood_lamp_accentuation']) && $scores['wood_lamp_accentuation'] === 'accentuated' ? 'Accentuated' : 'Not Accentuated' }}</td>
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; color: #4A5568;">Accentuation implies epidermal depth; non-accentuation suggests mixed or dermal depth.</td>
            </tr>
            <tr style="background-color: #F8FAFC;">
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Dermis Involvement</td>
                <td align="center" style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; color: #0E2B5C; font-weight: bold; font-size: 14px;">{{ !empty($scores['dermis_involvement']) && ($scores['dermis_involvement'] === 'yes' || $scores['dermis_involvement'] === true) ? 'Yes' : 'No' }}</td>
                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; color: #4A5568;">Indicates presence of deep dermal pigment components, affecting treatment selection.</td>
            </tr>
        </tbody>
    </table>

    <pagebreak />

    {{-- ── Clinical Working Impression ── --}}
    <div class="section-header">
        PRIMARY IMPRESSION: {{ $primaryDxLabel }}
    </div>

    <!-- Card -->
    <div class="section-card">

        <!-- Description -->
        <div class="section-label">DIAGNOSIS OVERVIEW</div>
        <div class="section-text" style="margin-bottom: 20px;">
            This primary diagnosis represents the dominant clinical impression based on AI-assisted scan indices and patient anamnesis.
        </div>

        <table width="100%">
            <tr>

                <!-- LEFT -->
                <td width="45%" valign="top">
                    <table>
                        <tr>
                            <td style="padding-bottom: 10px;">
                                <div class="section-label">CONFIDENCE SCORE</div>
                            </td>
                        </tr>
                    </table>
                    <table class="score-circle" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                        <tr>
                            <td align="center" valign="middle">
                                <div class="score-inner">
                                    {{ $confidence }}%
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:20px;">
                        <table>
                            <tr>
                                <td style="padding-bottom: 10px;">
                                    <div class="section-label">PIGMENTATION SCAN</div>
                                </td>
                            </tr>
                        </table>

                        <div class="img-box">
                            @php
                                $sortedImages = collect($record->images ?? []);
                                $firstImg = $sortedImages->first();
                            @endphp

                            @if($firstImg && !empty($firstImg['url']))
                                <img src="{{ $firstImg['url'] }}" style="width: 25%;">
                            @else
                                <div style="padding: 30px; color:#999;">NO IMAGE</div>
                            @endif
                        </div>
                    </div>

                </td>

                <!-- DIVIDER -->
                <td width="5%" class="divider"></td>

                <!-- RIGHT -->
                <td width="50%" valign="top">

                    <table>
                        <tr>
                            <td style="padding-bottom: 5px;"><div class="section-label" style="margin-top: 20px;">PIGMENTATION DEPTH</div></td>
                        </tr>
                        <tr>
                            <td style="padding-bottom: 10px;"><div class="section-text" style="margin-bottom: 20px; font-weight: bold; color: #0E2B5C;">{{ ucfirst($pigmentationType) }} Depth</div></td>
                        </tr>

                        @if(!empty($impression['clinical_pathway']))
                            <tr>
                                <td style="padding-bottom: 5px;"><div class="section-label" style="margin-top: 20px;">CLINICAL PATHWAY</div></td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 10px;"><div class="section-text" style="margin-bottom: 20px;">{{ $impression['clinical_pathway'] }}</div></td>
                            </tr>
                        @endif

                        @if(!empty($impression['pathophysiology_explanation']))
                            <tr>
                                <td style="padding-bottom: 5px;"><div class="section-label" style="margin-top: 20px;">PATHOPHYSIOLOGY EXPLANATION</div></td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 10px;"><div class="section-text" style="margin-bottom: 20px;">{{ $impression['pathophysiology_explanation'] }}</div></td>
                            </tr>
                        @endif
                    </table>
                </td>

            </tr>
        </table>

    </div>

    {{-- ── Differential Diagnosis Considerations ── --}}
    @if(!empty($alternatives) && count($alternatives) > 0)
        <pagebreak />
        <div class="section-header">
            DIFFERENTIAL DIAGNOSIS CONSIDERATIONS
        </div>
        <div class="section-card">
            <div class="section-label">ALTERNATIVE DIAGNOSES ELIMINATED OR UNDER REVIEW</div>
            <div class="section-text" style="margin-bottom: 20px;">
                The following conditions were evaluated as part of the differential analysis framework:
            </div>
            
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 15px; border: 1px solid #E2E8F0; border-collapse: collapse;">
                <thead>
                    <tr style="color: #0E2B5C;">
                        <th align="left" style="padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #C29F5D; border-right: 1px solid #E2E8F0;">Condition</th>
                        <th align="center" style="padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #C29F5D; border-right: 1px solid #E2E8F0;">Likelihood</th>
                        <th align="left" style="padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #C29F5D;">Clinical Basis / Rationale</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alternatives as $diff)
                        @if(is_array($diff))
                            @php
                                $dxName = $diff['dx'] ?? $diff['name'] ?? $diff['condition_name'] ?? 'N/A';
                                $dxLabel = ucwords(str_replace('_', ' ', $dxName));
                                $likelihood = $diff['likelihood'] ?? '—';
                                $basis = $diff['reconsider_when'] ?? $diff['rationale'] ?? '—';
                            @endphp
                            <tr style="background-color: {{ $loop->iteration % 2 == 0 ? '#F8FAFC' : '#FFFFFF' }};">
                                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">
                                    {{ $dxLabel }}
                                </td>
                                <td align="center" style="padding: 10px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; color: #0E2B5C; font-weight: bold; font-size: 11px;">
                                    {{ $likelihood }}
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; color: #4A5568; line-height: 1.4;">
                                    {{ $basis }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── Patient Scan Images ── --}}
    <pagebreak />
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">PATIENT SCAN IMAGES (BASELINE)</td>
        </tr>
    </table>

    <div class="section-card">
        <div class="section-label">CLINICAL PHOTOGRAPHIC DOCUMENTATION</div>
        <div class="section-text" style="margin-bottom: 20px;">
            The following diagnostic images were captured during the baseline scan using multiple light wavelengths (polarized, UV, and Wood's lamp) to map superficial and deep pigment distribution:
        </div>

        <table width="100%" cellpadding="0" cellspacing="0">
            @php
                $sortedImages = collect($record->images ?? []);
                $chunks = $sortedImages->chunk(2);
            @endphp
            @forelse($chunks as $chunk)
                <tr>
                    @foreach($chunk as $img)
                        <td width="48%" align="center" style="padding: 10px; border: 1px solid #E2E8F0; border-radius: 8px; background: #FFFFFF; vertical-align: top;">
                            <div class="img-box" style="border: none; padding: 0;">
                                @if(!empty($img['url']))
                                    <img src="{{ $img['url'] }}" style="max-height: 250px; max-width: 100%;">
                                @else
                                    <div style="padding: 50px; color: #999;">NO IMAGE</div>
                                @endif
                            </div>
                            <div class="info-label" style="margin-top: 8px; font-size: 10px;">{{ ucwords(str_replace('_', ' ', $img['name'] ?? 'Face Scan')) }}</div>
                        </td>
                        @if($loop->first && $chunk->count() == 1)
                            <td width="4%"></td>
                            <td width="48%"></td>
                        @elseif($loop->first)
                            <td width="4%"></td>
                        @endif
                    @endforeach
                </tr>
                @if(!$loop->last)
                    <tr><td height="15" colspan="3"></td></tr>
                @endif
            @empty
                <tr>
                    <td align="center" style="padding: 30px; color: #999;">No photographic scans available.</td>
                </tr>
            @endforelse
        </table>
    </div>

</div>

@endsection
