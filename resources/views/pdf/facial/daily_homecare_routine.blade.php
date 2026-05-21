@extends('pdf.master')

@section('content')

<style>
    .page-title-section {
        margin-bottom: 25px;
    }

    .section-title {
        font-size: 15px;
        font-weight: bold;
        color: #0E2B5C;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-top: 25px;
        margin-bottom: 15px;
        border-bottom: 2px solid #C29F5D;
        padding-bottom: 5px;
    }

    .routine-header-table {
        width: 100%;
        margin-top: 25px;
        margin-bottom: 15px;
        border-radius: 6px;
    }

    .routine-header-title {
        font-size: 14px;
        font-weight: bold;
        color: #FFFFFF;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        padding: 10px 15px;
    }

    .morning-header-bg {
        background-color: #C29F5D;
    }

    .evening-header-bg {
        background-color: #0E2B5C;
    }

    .step-badge {
        font-weight: bold;
        text-align: center;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        line-height: 28px;
        font-size: 14px;
    }

    .morning-badge-bg {
        color: #C29F5D;
    }

    .evening-badge-bg {
        color: #0E2B5C;
    }

    .product-title {
        font-size: 13px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 3px;
    }

    .routine-label {
        font-size: 12px;
        font-weight: bold;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .routine-value {
        font-size: 12px;
        color: #2D3748;
        line-height: 1.5;
    }

    .session-badge {
        background-color: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 6px;
        padding: 10px 14px;
        margin-bottom: 25px;
    }

    .session-badge-title {
        font-size: 9px;
        font-weight: bold;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .session-badge-value {
        font-size: 13px;
        font-weight: bold;
        color: #0E2B5C;
    }

    .care-note-box {
        border: 1px solid #E2E8F0;
        border-left: 4px solid #C29F5D;
        background-color: #F8FAFC;
        padding: 15px;
        margin-top: 30px;
        border-radius: 4px;
        page-break-inside: avoid;
    }

    .care-note-title {
        font-size: 12px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .care-note-text {
        font-size: 11px;
        color: #4A5568;
        line-height: 1.5;
    }
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">DAILY HOMECARE ROUTINE</div>
        <div class="report-subtitle">Personalized skin care routine for post-treatment recovery & enhancement</div>
    </div>

    {{-- ── Patient Info Cards ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">PATIENT NAME</div>
                    <div class="info-value">{{ $patient['name'] ?? 'N/A' }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">AGE / GENDER</div>
                    <div class="info-value">{{ $patient['age'] ?? 'N/A' }} / {{ isset($patient['gender']) ? strtoupper(substr($patient['gender'], 0, 1)) : 'N/A' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 25px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Report Date</div>
                    <div class="info-value">{{ date('d/m/Y', strtotime($report_date)) ?? 'N/A' }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">SKIN PROFILE</div>
                    <div class="info-value">{{ $patient['skin_type'] ?? 'N/A' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Session Info ── --}}
    @if(isset($session))
        <div class="session-badge">
            <div class="session-badge-title">GENERATED FOR TREATMENT SESSION</div>
            <div class="session-badge-value">
                Session {{ $session['session_number'] ?? 'N/A' }} (Week {{ $session['week'] ?? 'N/A' }}): {{ $session['title'] ?? 'N/A' }}
            </div>
        </div>
    @endif

    @php
        $routine = $session['daily_home_care_routine'] ?? [];
        if (is_string($routine)) {
            $routine = json_decode($routine, true);
        }
        $morning = $routine['morning'] ?? [];
        $evening = $routine['evening'] ?? [];
    @endphp

    @if(empty($morning) && empty($evening))
        <div style="text-align: center; padding: 40px 0; border: 1px dashed #CBD5E1; border-radius: 8px; background-color: #F8FAFC;">
            <div style="font-size: 14px; font-weight: bold; color: #64748B; margin-bottom: 5px;">No Routine Prescribed</div>
            <div style="font-size: 12px; color: #94A3B8;">A personalized daily routine has not been specified for this treatment session.</div>
        </div>
    @else
        {{-- ☀️ Morning Routine Section --}}
        @if(!empty($morning))
            <table class="routine-header-table morning-header-bg" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="routine-header-title">MORNING ROUTINE</td>
                </tr>
            </table>

            @foreach($morning as $step)
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px; border: 1px solid #E2E8F0; border-radius: 8px; background-color: #FFFFFF; page-break-inside: avoid;">
                    <tr>
                        <!-- Left color stripe / Step Number -->
                        <td width="8%" valign="middle" align="center" style="background-color: #FAFaf9; border-right: 1px solid #E2E8F0; padding: 15px 10px; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                            <div class="step-badge morning-badge-bg">{{ $step['step_number'] ?? $loop->iteration }}</div>
                        </td>

                        <!-- Details -->
                        <td width="92%" valign="top" style="padding: 15px; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                            <div class="product-title">{{ $step['product_name'] ?? 'Unspecified Product' }}</div>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8px;">
                                <tr>
                                    <td width="50%" valign="top" style="padding-right: 12px;">
                                        <div class="routine-label">HOW TO USE</div>
                                        <div class="routine-value">{{ $step['how_to_use'] ?? 'N/A' }}</div>
                                    </td>
                                    @if(!empty($step['clinical_purpose']))
                                        <td width="50%" valign="top" style="padding-left: 12px; border-left: 1px solid #EDF2F7;">
                                            <div class="routine-label">CLINICAL PURPOSE</div>
                                            <div class="routine-value">{{ $step['clinical_purpose'] }}</div>
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endforeach
        @endif

        {{-- 🌙 Evening Routine Section --}}
        @if(!empty($evening))
            @if(!empty($morning))
                <div style="page-break-before: always;"></div>
            @endif

            <table class="routine-header-table evening-header-bg" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="routine-header-title">EVENING ROUTINE</td>
                </tr>
            </table>

            @foreach($evening as $step)
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px; border: 1px solid #E2E8F0; border-radius: 8px; background-color: #FFFFFF; page-break-inside: avoid;">
                    <tr>
                        <!-- Left color stripe / Step Number -->
                        <td width="8%" valign="middle" align="center" style="background-color: #F8FAFC; border-right: 1px solid #E2E8F0; padding: 15px 10px; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                            <div class="step-badge evening-badge-bg">{{ $step['step_number'] ?? $loop->iteration }}</div>
                        </td>

                        <!-- Details -->
                        <td width="92%" valign="top" style="padding: 15px; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                            <div class="product-title">{{ $step['product_name'] ?? 'Unspecified Product' }}</div>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8px;">
                                <tr>
                                    <td width="50%" valign="top" style="padding-right: 12px;">
                                        <div class="routine-label">HOW TO USE</div>
                                        <div class="routine-value">{{ $step['how_to_use'] ?? 'N/A' }}</div>
                                    </td>
                                    @if(!empty($step['clinical_purpose']))
                                        <td width="50%" valign="top" style="padding-left: 12px; border-left: 1px solid #EDF2F7;">
                                            <div class="routine-label">CLINICAL PURPOSE</div>
                                            <div class="routine-value">{{ $step['clinical_purpose'] }}</div>
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endforeach
        @endif

        {{-- General Care Note --}}
        <!-- <div class="care-note-box">
            <div class="care-note-title">Important Recovery Guidelines</div>
            <div class="care-note-text">
                Please follow the step sequencing exactly as prescribed. If you experience unexpected redness, persistent stinging, or excessive peeling, temporarily pause any active products (such as serums containing exfoliants or acids) and focus solely on your cleanser, moisturizer, and sunscreen.
            </div>
        </div> -->
    @endif
</div>

@endsection
