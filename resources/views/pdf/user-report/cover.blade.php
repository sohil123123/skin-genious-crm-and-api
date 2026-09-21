@php
    use App\Services\UserReportPdfService as Report;

    $clinicName = $clinic?->name ?? config('app.name');
    $clinicAddress = collect([$clinic?->address_line1, $clinic?->address_line2, $clinic?->city, $clinic?->state, $clinic?->pincode])->filter()->implode(', ');

    $age = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->age : null;

    $conditions = collect([
        'has_diabetes' => 'Diabetes',
        'has_high_bp' => 'High BP',
        'has_cholesterol' => 'Cholesterol',
        'has_asthma' => 'Asthma',
        'has_heart_disease' => 'Heart Disease',
        'has_anaemia' => 'Anaemia',
        'has_pcos' => 'PCOS',
        'has_thyroid' => 'Thyroid',
    ])->filter(fn($label, $field) => (bool) $user->{$field})->values();

    $goals = collect([
        'goal_less_tired' => 'Look less tired',
        'goal_less_angry' => 'Look less angry',
        'goal_less_sad' => 'Look less sad',
        'goal_less_saggy' => 'Less sagging',
        'goal_youthful' => 'More youthful',
        'goal_attractive' => 'More attractive',
        'goal_soft_features' => 'Softer features',
        'goal_slim_face' => 'Slimmer face',
    ])->filter(fn($label, $field) => (bool) $user->{$field})->values();

    $address = collect([$user->address_line_1, $user->address_line_2, $user->city, config('project.indian_states.' . $user->state, $user->state), $user->pincode])->filter()->implode(', ');

    $profile = [
        'Full name' => $user->name,
        'Mobile' => $user->mobile,
        'Email' => $user->email,
        'Gender' => $user->gender ? ucfirst($user->gender) : null,
        'Date of birth' => $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d M Y') . ($age !== null ? " ({$age} yrs)" : '') : null,
        'Occupation' => $user->occupation,
        'Clinic' => $clinic?->name,
        'Client since' => $user->created_at?->format('d M Y'),
        'Referral code' => $user->referral_code,
        'Heard about us' => $user->how_did_you_hear,
        'Loyalty programme' => $user->opt_for_loyalty ? 'Enrolled' : 'Not enrolled',
        'Loyalty points' => $user->loyalty_points !== null ? number_format((int) $user->loyalty_points) : null,
        'Total referrals' => $user->total_referrals,
        'Referral earnings' => $user->referral_earnings !== null ? Report::money($user->referral_earnings) : null,
        'Status' => $user->is_active ? 'Active' : 'Inactive',
        'Address' => $address,
    ];

    $skin = [
        'Skin type' => $user->skin_type,
        'Skin quality' => $user->skin_quality,
        'Facials history' => $user->facials_history,
        'Skin improvement wish' => $user->skin_improvement,
        'Other diseases' => $user->other_diseases,
        'Current medications' => $user->current_medications,
        'Allergies' => $user->allergies,
    ];

    $pairs = fn(array $rows) => array_chunk(array_filter($rows, fn($v) => filled($v)), 2, true);
@endphp

{{-- Running header and footer --}}
<htmlpageheader name="reportHeader">
    <table class="page-header">
        <tr>
            <td style="width: 60%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height: 18px; vertical-align: middle;">&nbsp;
                @endif
                <span class="brand">{{ $clinicName }}</span>
            </td>
            <td class="doc">{{ $title }} &middot; {{ $user->name }}</td>
        </tr>
    </table>
</htmlpageheader>

<htmlpagefooter name="reportFooter">
    <table class="page-footer">
        <tr>
            <td style="width: 40%;">Generated {{ $generatedAt->format('d M Y, h:i A') }}@if ($generatedBy) by {{ $generatedBy }}@endif</td>
            <td style="width: 30%; text-align: center;">Confidential &middot; for client records</td>
            <td style="width: 30%; text-align: right;">Page {PAGENO} of {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>

<sethtmlpageheader name="reportHeader" value="on" show-this-page="0" />
<sethtmlpagefooter name="reportFooter" value="on" />

{{-- Hero --}}
<div class="hero">
    <table>
        <tr>
            @if ($logo)
                <td style="width: 70px;">
                    <div class="logo-box"><img src="{{ $logo }}" style="width: 56px;"></div>
                </td>
            @endif
            <td>
                <div class="eyebrow">{{ $clinicName }}</div>
                <div class="title">{{ $title }}</div>
                <div class="subtitle">{{ $user->name }}@if ($user->mobile) &nbsp;&middot;&nbsp; {{ $user->mobile }}@endif</div>
            </td>
            <td class="meta" style="width: 32%;">
                @if ($clinicAddress){{ $clinicAddress }}<br>@endif
                @if ($clinic?->phone)Phone: {{ $clinic->phone }}<br>@endif
                @if ($clinic?->email){{ $clinic->email }}<br>@endif
                @if ($clinic?->gst_number)GSTIN: {{ $clinic->gst_number }}<br>@endif
                {{ $generatedAt->format('d M Y') }}
            </td>
        </tr>
    </table>
</div>

{{-- Client profile --}}
<h2 class="block-title">Client Profile</h2>
<table class="info-grid">
    @foreach ($pairs($profile) as $row)
        <tr>
            @foreach ($row as $label => $value)
                <td class="label">{{ $label }}</td>
                <td class="value">{{ $value }}</td>
            @endforeach
            @if (count($row) === 1)
                <td class="label"></td><td class="value"></td>
            @endif
        </tr>
    @endforeach
</table>

<h2 class="block-title">Health &amp; Skin Background</h2>
<table class="info-grid">
    <tr>
        <td class="label">Medical conditions</td>
        <td class="value" colspan="3">
            @forelse ($conditions as $condition)
                <span class="chip chip-red">{{ $condition }}</span>
            @empty
                <span class="chip chip-green">None reported</span>
            @endforelse
        </td>
    </tr>
    <tr>
        <td class="label">Aesthetic goals</td>
        <td class="value" colspan="3">
            @forelse ($goals as $goal)
                <span class="chip chip-purple">{{ $goal }}</span>
            @empty
                <span class="muted">—</span>
            @endforelse
        </td>
    </tr>
    @foreach ($pairs($skin) as $row)
        <tr>
            @foreach ($row as $label => $value)
                <td class="label">{{ $label }}</td>
                <td class="value">{{ $value }}</td>
            @endforeach
            @if (count($row) === 1)
                <td class="label"></td><td class="value"></td>
            @endif
        </tr>
    @endforeach
</table>

{{-- At a glance --}}
<h2 class="block-title">At a Glance</h2>
@include('pdf.user-report.partials.summary')
