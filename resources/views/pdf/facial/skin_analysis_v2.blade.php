@extends('pdf.facial.master')
@section('content')
@php
$report=collect(data_get($diagnosis ?? [],'diagnosis_report',[]));
$concernSummary=collect(data_get($diagnosis ?? [],'treatable_concerns_summary.parameters_with_abnormal_scores',[]));
$concerns=collect(data_get($diagnosis ?? [],'treatable_concerns',[]));
if ($concerns->isEmpty() && $concernSummary->isNotEmpty()) {
    $parameterToConcernMap = [
        'Barrier Health + Sensitivity (Combined Score)' => 'mild_barrier_reactivity_redness',
        'Barrier Health + Sensitivity' => 'mild_barrier_reactivity_redness',
        'barrier_health_sensitivity' => 'mild_barrier_reactivity_redness',
        'Vascularity / Redness Score' => 'mild_barrier_reactivity_redness',
        'Vascularity / Redness' => 'mild_barrier_reactivity_redness',
        'vascularity_redness_profiling' => 'mild_barrier_reactivity_redness',
        'Skin Hydration Score' => 'hydration_optimization_dehydration_lines',
        'Skin Hydration' => 'hydration_optimization_dehydration_lines',
        'skin_hydration_score' => 'hydration_optimization_dehydration_lines',
        'Superficial Wrinkles Score' => 'hydration_optimization_dehydration_lines',
        'superficial_wrinkles_scoring' => 'hydration_optimization_dehydration_lines',
        'Texture & Open Pores Score' => 'pore_texture_refinement',
        'Texture & Open Pores Scoring' => 'pore_texture_refinement',
        'Texture & Open Pores' => 'pore_texture_refinement',
        'Texture open pores scoring' => 'pore_texture_refinement',
        'texture_open_pores_scoring' => 'pore_texture_refinement',
        'Textural Radiance Index' => 'pore_texture_refinement',
        'textural_radiance_index' => 'pore_texture_refinement',
        'Peri-Orbital Health Score' => 'mild_under_eye_darkness_puffiness',
        'Peri-Orbital Health' => 'mild_under_eye_darkness_puffiness',
        'periorbital_health' => 'mild_under_eye_darkness_puffiness',
        'Superficial Pigmentation Score' => 'tone_evenness_maintenance',
        'Superficial Pigmentation' => 'tone_evenness_maintenance',
        'superficial_pigmentation_score' => 'tone_evenness_maintenance',
        'Lip Pigmentation' => 'tone_evenness_maintenance',
        'Lip Pigmentation Score' => 'tone_evenness_maintenance',
        'lip_pigmentation' => 'tone_evenness_maintenance',
    ];

    $builtConcerns = [];
    foreach ($concernSummary as $paramRow) {
        $paramName = data_get($paramRow, 'parameter');
        $concernKey = $parameterToConcernMap[$paramName] ?? null;
        
        if ($concernKey) {
            if (!isset($builtConcerns[$concernKey])) {
                $isPrimary = filter_var(data_get($paramRow, 'is_primary_concern'), FILTER_VALIDATE_BOOLEAN);
                $builtConcerns[$concernKey] = [
                    'concern' => $concernKey,
                    'priority' => $isPrimary ? 'high' : 'medium',
                    'related_parameters' => []
                ];
            }
            $builtConcerns[$concernKey]['related_parameters'][] = $paramName;
            if (filter_var(data_get($paramRow, 'is_primary_concern'), FILTER_VALIDATE_BOOLEAN)) {
                $builtConcerns[$concernKey]['priority'] = 'high';
            }
        } else {
            $fakeKey = \Illuminate\Support\Str::slug($paramName, '_');
            $isPrimary = filter_var(data_get($paramRow, 'is_primary_concern'), FILTER_VALIDATE_BOOLEAN);
            $builtConcerns[$fakeKey] = [
                'concern' => $fakeKey,
                'concern_name' => $paramName,
                'priority' => $isPrimary ? 'high' : 'medium',
                'related_parameters' => [$paramName]
            ];
        }
    }
    $concerns = collect(array_values($builtConcerns));
}
$composedName=trim((string)data_get($patient ?? [],'first_name','').' '.(string)data_get($patient ?? [],'last_name',''));
$patientName=data_get($patient ?? [],'full_name',data_get($patient ?? [],'name',$composedName ?: 'N/A'));
$patientAge=data_get($patient ?? [],'age',data_get($data ?? [],'age','N/A'));
$patientGender=strtoupper(substr((string)data_get($patient ?? [],'gender','N/A'),0,1));
$createdAt=data_get($data ?? [],'created_at');
$reportDate=$createdAt?date('d/m/Y',strtotime($createdAt)):date('d/m/Y');
$skinProfile=data_get($report->get('skin_type',[]),'score_or_label',data_get($patient ?? [],'skin_type','N/A'));
$analysisSummary=trim(strip_tags((string)data_get($diagnosis ?? [],'script','No assessment summary is available.')));
$analysisSummary=preg_replace('/\*\*(.*?)\*\*/s','$1',$analysisSummary) ?? $analysisSummary;
$analysisSummary=preg_replace('/[_`#]+/','',$analysisSummary) ?? $analysisSummary;
if(strlen($analysisSummary)>500){$cut=substr($analysisSummary,0,497);$analysisSummary=rtrim(substr($cut,0,strrpos($cut,' ') ?: 497)).'...';}
$modeIndex=[1=>'red',2=>'subsurface_polarized',3=>'surface_polarized',4=>'white',5=>'woods_uv'];
$modeTitles=['white'=>'White Light','red'=>'Red Light','subsurface_polarized'=>'Subsurface Polarised','surface_polarized'=>'Surface Polarised','woods_uv'=>'Woods UV'];
$modeCopy=['white'=>'Natural tone, glow and visible balance','red'=>'Redness and vascular signals','subsurface_polarized'=>'Sub-surface tone patterns','surface_polarized'=>'Pores, texture and fine lines','woods_uv'=>'Fluorescence and superficial changes'];
$concernLabels=[
'mild_barrier_reactivity_redness'=>'Barrier Calm & Redness',
'hydration_optimization_dehydration_lines'=>'Hydration & Plumpness',
'pore_texture_refinement'=>'Texture & Pores',
'mild_under_eye_darkness_puffiness'=>'Under-Eye Support',
'tone_evenness_maintenance'=>'Tone Maintenance',
];
$concernIcons=[
'mild_barrier_reactivity_redness'=>'icon_barrier',
'hydration_optimization_dehydration_lines'=>'icon_hydration',
'pore_texture_refinement'=>'icon_pores',
'mild_under_eye_darkness_puffiness'=>'icon_eye',
'tone_evenness_maintenance'=>'icon_glow',
];
$primaryConcerns=$concerns->where('priority','high')->take(3)->values();
$secondaryConcerns=$concerns->whereIn('priority',['medium','low'])->values();
$parameterCount=$report->count();
$numericItems=$report->filter(fn($item)=>is_numeric(data_get($item,'score_or_label')))->values();
$leftDashboard=$numericItems->slice(0,7)->values();
$rightDashboard=$numericItems->slice(7,7)->values();
$groups=[
['title'=>'Balance, Hydration & Glow','subtitle'=>'Skin type, oil balance, moisture support and visible luminosity.','keys'=>['skin_type','skin_sebum_content','skin_hydration_score','skin_luminosity_glow_index']],
['title'=>'Texture, Congestion & Barrier','subtitle'=>'Breakout activity, pores, surface clarity and barrier resilience.','keys'=>['visual_acne_grading','texture_open_pores_scoring','textural_radiance_index','barrier_health_sensitivity']],
['title'=>'Redness, Lines & Structure','subtitle'=>'Vascular reactivity, fine lines, firmness and lower-face definition.','keys'=>['vascularity_redness_profiling','superficial_wrinkles_scoring','skin_firmness_elasticity_index','jawline_sagging_score']],
['title'=>'Pigmentation & Eye Area','subtitle'=>'Under-eye health, lip tone and superficial pigmentation distribution.','keys'=>['periorbital_health','lip_pigmentation','superficial_pigmentation_score']],
];
@endphp

{{-- PAGE 1 - PASSPORT DASHBOARD --}}
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="34%" style="vertical-align:middle;">
<table cellpadding="0" cellspacing="0"><tr>
<td width="23mm" style="vertical-align:middle;">@if(!empty($uiAssets['brand_icon']))<img src="{{ $uiAssets['brand_icon'] }}" width="70" height="70" style="width:18.5mm;height:18.5mm;" alt="AI Aesthetics">@endif</td>
<td style="padding-left:2mm;vertical-align:middle;"><div class="brand-name" style="font-size:12.3pt;">A.I. AESTHETICS</div><div class="brand-byline">By Dr. Aakriti Mehra</div></td>
</tr></table>
</td>
<td width="66%" style="vertical-align:middle;text-align:right;">
<div class="cover-title" style="font-size:27pt;">Your <span class="ai">AI</span> <span style="color:#f1d29b;font-style:italic;">Skin Passport</span></div>
<div class="cover-subtitle">Advanced 5-Mode Skin Analysis <span class="dot">|</span> Personalised Skin Profile <span class="dot">|</span> Outcome Tracking</div>
</td>
</tr>
</table>
<div class="header-rule" style="margin-top:2.5mm;"></div>

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="40%" style="vertical-align:top;">
<div class="hero-box">@if(!empty($reportAssets['baseline']['cover_large']['white']))<img src="{{ $reportAssets['baseline']['cover_large']['white'] }}" width="288" height="384" style="width:76.2mm;height:101.6mm;" alt="White-light facial image">@else<div class="no-image" style="padding:40mm 1mm;">White-light image unavailable</div>@endif</div>
</td>
<td width="3%"></td>
<td width="57%" style="vertical-align:top;">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="47%" style="text-align:center;vertical-align:middle;">@if($parameterCount===15 && !empty($uiAssets['ring_15']))<img src="{{ $uiAssets['ring_15'] }}" width="180" height="180" style="width:47.6mm;height:47.6mm;" alt="15 skin parameters analysed">@else<div class="card card-teal pad"><div class="metric-number">{{ $parameterCount }}</div><div class="metric-label">Skin parameters analysed</div></div>@endif</td>
<td width="3%"></td>
<td width="50%" class="card card-teal pad" style="vertical-align:middle;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td width="25%" style="vertical-align:middle;">@if(!empty($uiAssets['icon_skin_type']))<img src="{{ $uiAssets['icon_skin_type'] }}" width="48" height="48" style="width:12.7mm;height:12.7mm;" alt="Skin type">@endif</td><td width="75%" style="vertical-align:middle;"><div class="mini-label">Skin Type</div><div class="mini-value-lg" style="color:#63d8cf;margin-top:1mm;">{{ $skinProfile }}</div></td></tr></table>
</td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:55mm;"><tr><td class="pad-lg" style="vertical-align:middle;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td width="13%" style="vertical-align:top;">@if(!empty($uiAssets['icon_glow']))<img src="{{ $uiAssets['icon_glow'] }}" width="45" height="45" style="width:12mm;height:12mm;" alt="Summary">@endif</td><td width="87%"><div class="mini-value-lg" style="color:#bda5ff;">Your Skin Summary</div><div class="section-copy-lg" style="margin-top:2mm;font-size:9.5pt;line-height:1.48;">{{ $analysisSummary }}</div></td></tr></table>
</td></tr></table>
</td>
</tr>
</table>

<div class="card" style="margin-top:3mm;padding:2.5mm;">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach(['woods_uv','white','surface_polarized','subsurface_polarized','red'] as $mode)
<td width="20%" style="text-align:center;{{ !$loop->first?'border-left:0.25mm solid #223e53;':'' }}vertical-align:top;">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td style="height:29mm;text-align:center;vertical-align:middle;">@if(!empty($reportAssets['baseline']['mode_strip'][$mode]))<img src="{{ $reportAssets['baseline']['mode_strip'][$mode] }}" width="80" height="106" style="width:21.2mm;height:28mm;" alt="{{ $modeTitles[$mode] }}">@endif</td></tr>
<tr><td class="cover-mode-label" style="height:7.5mm;padding-top:1.4mm;vertical-align:top;">{{ $modeTitles[$mode] }}</td></tr>
</table>
</td>
@endforeach
</tr></table>
</div>

<div class="eyebrow" style="text-align:center;margin-top:4mm;font-size:7.4pt;">Your Top Skin Priorities</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;"><tr>
@foreach($primaryConcerns as $i=>$concern)
@php $key=data_get($concern,'concern',data_get($concern,'concern_key')); $label=$concernLabels[$key] ?? data_get($concern,'concern_name','Priority'); $icon=$concernIcons[$key] ?? 'icon_target'; @endphp
<td width="32%" class="card {{ $i===0?'card-teal':($i===1?'card-purple':'card-gold') }} pad-sm" style="{{ $i>0?'border-left:3mm solid #061421;':'' }}vertical-align:middle;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td width="28%" style="vertical-align:middle;">@if(!empty($uiAssets[$icon]))<img src="{{ $uiAssets[$icon] }}" width="50" height="50" style="width:13.2mm;height:13.2mm;" alt="{{ $label }}">@endif</td><td width="72%" style="vertical-align:middle;"><div class="mini-value-lg">{{ $label }}</div><div class="dashboard-copy" style="margin-top:0.8mm;">{{ \Illuminate\Support\Str::limit(trim((string)(data_get($concern,'short_description') ?: data_get($concern,'client_description') ?: data_get($concern,'treatment_opportunity') ?: '')), 80) }}</div></td></tr></table>
</td>
@endforeach
</tr></table>

<table width="100%" cellpadding="0" cellspacing="0" class="card-soft" style="margin-top:3mm;"><tr>
<td width="28%" class="pad-sm"><table cellpadding="0" cellspacing="0"><tr><td style="vertical-align:middle;">@if(!empty($uiAssets['icon_user']))<img src="{{ $uiAssets['icon_user'] }}" width="36" height="36" style="width:9.5mm;height:9.5mm;" alt="Patient">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label">Patient</div><div class="mini-value" style="font-size:9pt;">{{ $patientName }}</div></td></tr></table></td>
<td width="24%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><div class="mini-label">Assessment Type</div><div class="mini-value" style="font-size:9pt;">Facial Skin Analysis</div></td>
<td width="24%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><table cellpadding="0" cellspacing="0"><tr><td style="vertical-align:middle;">@if(!empty($uiAssets['icon_camera']))<img src="{{ $uiAssets['icon_camera'] }}" width="34" height="34" style="width:9mm;height:9mm;" alt="Analysis">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label">Analysis Method</div><div class="mini-value" style="font-size:9pt;">5-Mode Analysis</div></td></tr></table></td>
<td width="24%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><table cellpadding="0" cellspacing="0"><tr><td style="vertical-align:middle;">@if(!empty($uiAssets['icon_calendar']))<img src="{{ $uiAssets['icon_calendar'] }}" width="34" height="34" style="width:9mm;height:9mm;" alt="Date">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label">Assessment Date</div><div class="mini-value" style="font-size:9pt;">{{ $reportDate }}</div></td></tr></table></td>
</tr></table>
<pagebreak />

{{-- PAGE 2 - SCORE DASHBOARD --}}
@include('pdf.facial.partials.header',['kicker'=>'Skin at a Glance'])
<div class="eyebrow">Your current profile</div>
<div class="section-title">Every score, <span class="accent">in one clear view</span></div>
<div class="page-subtitle">All scores use a 1-100 health scale, where 100 represents ideal skin for that parameter. Sebum is scored against a balance target rather than ever-lower oil.</div>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:19mm;"><tr><td width="40%" class="pad-sm"><div class="mini-label">Skin Type</div><div class="mini-value-lg">{{ $skinProfile }}</div></td><td width="30%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><div class="mini-label">Priority Areas</div><div class="mini-value-lg">{{ $primaryConcerns->count() }} high priority</div></td><td width="30%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><div class="mini-label">Evidence Modes</div><div class="mini-value-lg">5 imaging views</div></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr><td width="49%">
@foreach($leftDashboard as $item) @include('pdf.facial.partials.dashboard-card',['item'=>$item]) @endforeach
</td><td width="2%"></td><td width="49%">
@foreach($rightDashboard as $item) @include('pdf.facial.partials.dashboard-card',['item'=>$item]) @endforeach
</td></tr></table>
<pagebreak />

{{-- PAGE 3 - OPPORTUNITIES --}}
@include('pdf.facial.partials.header',['kicker'=>'Personalised Opportunity Map'])
<div class="eyebrow">Where treatment can make the most difference</div>
<div class="section-title">Your three main <span class="accent">skin opportunities</span></div>
<div class="page-subtitle">These priorities come directly from the treatable-concern output. Targets show an indicative direction, not a guaranteed result.</div>
@foreach($primaryConcerns as $i=>$concern)
@php
$key=data_get($concern,'concern',data_get($concern,'concern_key'));
$label=$concernLabels[$key] ?? data_get($concern,'concern_name','Priority');
$icon=$concernIcons[$key] ?? 'icon_target';
$relatedNames=collect(data_get($concern,'related_parameters',data_get($concern,'linked_parameters',data_get($concern,'parameters',[]))));
$linked=$concernSummary->filter(fn($row)=>$relatedNames->contains(data_get($row,'parameter')))->values();
$lead=$linked->first(fn($row)=>(string)data_get($row,'is_primary_concern')==='true') ?? $linked->first() ?? [];
@endphp
<table class="priority-card {{ $i===0?'card-teal':($i===1?'card-gold':'card-purple') }}" cellpadding="0" cellspacing="0"><tr>
<td width="15%" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets[$icon]))<img src="{{ $uiAssets[$icon] }}" width="58" height="58" style="width:15.3mm;height:15.3mm;" alt="{{ $label }}">@endif<div class="metric-number" style="font-size:19pt;color:#63d8cf;margin-top:1mm;">{{ $i+1 }}</div></td>
<td width="55%"><div class="priority-title">{{ $label }}</div><div class="priority-copy">{{ data_get($lead,'short_description',data_get($concern,'client_description',data_get($concern,'treatment_opportunity',''))) }}</div><div class="priority-why"><b>Why this matters:</b> {{ data_get($lead,'reason_for_selection',data_get($concern,'why_selected','')) }}</div></td>
<td width="30%" style="border-left:0.25mm solid #29475d;"><div class="mini-label" style="color:#e5c477;">Indicative single-session target</div>
@foreach($linked as $parameter)
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr><td><div class="mini-label">{{ data_get($parameter,'parameter','Parameter') }}</div><div style="margin-top:0.7mm;"><span class="mini-value-lg">{{ data_get($parameter,'current_score','-') }}</span><span style="display:inline-block;width:9mm;text-align:center;">@include('pdf.facial.partials.arrow')</span><span class="mini-value-lg" style="color:#63d8cf;">{{ data_get($parameter,'target_single_session_score','-') }}</span></div></td></tr></table>
@endforeach
</td>
</tr></table>
@endforeach
@if($secondaryConcerns->isNotEmpty())
<div class="eyebrow" style="margin-top:3mm;">Secondary focus</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;"><tr>
@foreach($secondaryConcerns->take(2) as $concern)
@php $key=data_get($concern,'concern',data_get($concern,'concern_key')); $label=$concernLabels[$key] ?? data_get($concern,'concern_name','Secondary focus'); $icon=$concernIcons[$key] ?? 'icon_target'; @endphp
<td width="49%" class="card-soft pad-sm" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}"><table cellpadding="0" cellspacing="0"><tr><td>@if(!empty($uiAssets[$icon]))<img src="{{ $uiAssets[$icon] }}" width="40" height="40" style="width:10.6mm;height:10.6mm;" alt="{{ $label }}">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label {{ data_get($concern,'priority')==='medium'?'accent':'gold' }}">{{ strtoupper((string)data_get($concern,'priority')) }}</div><div class="mini-value-lg">{{ $label }}</div></td></tr></table></td>
@endforeach
</tr></table>
@endif
<pagebreak />

{{-- PAGE 4 - FIVE MODES --}}
@include('pdf.facial.partials.header',['kicker'=>'Five Controlled Imaging Modes'])
<div class="eyebrow">Evidence behind the scores</div>
<div class="section-title">Your skin, viewed through <span class="accent">five different lenses</span></div>
<div class="page-subtitle">Each mode supports a different part of the assessment. Images are presented without cosmetic retouching.</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
<td width="42%" style="vertical-align:top;"><div class="image-box">@if(!empty($reportAssets['baseline']['mode_main']['white']))<img src="{{ $reportAssets['baseline']['mode_main']['white'] }}" width="300" height="400" style="width:79.4mm;height:105.8mm;" alt="White Light">@endif</div><div class="mode-title" style="margin-top:1.5mm;">White Light</div><div class="mode-copy">{{ $modeCopy['white'] }}</div></td>
<td width="3%"></td>
<td width="55%" style="vertical-align:top;">
<table width="100%" cellpadding="0" cellspacing="0">
@foreach([['red','subsurface_polarized'],['surface_polarized','woods_uv']] as $row)
<tr>
@foreach($row as $mode)
<td width="49%" class="mode-tile pad-sm" style="{{ !$loop->first?'border-left:2.5mm solid #061421;':'' }}text-align:center;vertical-align:top;">
@if(!empty($reportAssets['baseline']['mode_grid'][$mode]))<img src="{{ $reportAssets['baseline']['mode_grid'][$mode] }}" width="135" height="180" style="width:35.7mm;height:47.6mm;" alt="{{ $modeTitles[$mode] }}">@endif
<div class="mode-title" style="margin-top:1.5mm;">{{ $modeTitles[$mode] }}</div><div class="mode-copy">{{ $modeCopy[$mode] }}</div>
</td>
@endforeach
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:3mm;"></td></tr>@endif
@endforeach
</table>
</td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card-soft" style="margin-top:3mm;"><tr><td class="pad"><div class="mini-label" style="color:#bda5ff;">How these views work together</div><div class="section-copy" style="margin-top:1mm;">White light shows the visible skin profile. Polarised modes help assess surface texture and sub-surface tone. Red mode supports vascular interpretation, while Woods UV adds fluorescence and superficial-change context.</div></td></tr></table>
<pagebreak />

{{-- PAGES 5-8 - DETAILED GRIDS --}}
@foreach($groups as $gIndex=>$group)
@include('pdf.facial.partials.header',['kicker'=>'Detailed Skin Profile'])
<div class="eyebrow">Parameter detail</div>
<div class="section-title">{{ $group['title'] }}</div>
<div class="page-subtitle" style="margin-bottom:3mm;">{{ $group['subtitle'] }}</div>
@php $items=collect($group['keys'])->map(fn($key)=>['key'=>$key,'item'=>$report->get($key,[])])->filter(fn($x)=>!empty($x['item']))->values(); @endphp
@if($gIndex<3)
<table width="100%" cellpadding="0" cellspacing="0">
@foreach($items->chunk(2) as $row)
<tr>
@foreach($row as $entry)
@php $item=$entry['item']; $mode=$modeIndex[max(1,min(5,(int)data_get($item,'affected_area_image',4)))] ?? 'white'; $imageUrl=data_get($reportAssets,"baseline.parameter.$mode"); @endphp
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}">@include('pdf.facial.partials.parameter-grid-card',['item'=>$item,'imageUrl'=>$imageUrl])</td>
@endforeach
@if($row->count()===1)<td width="49%"></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:3mm;"></td></tr>@endif
@endforeach
</table>
@else
<table width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach($items->take(2) as $entry)
@php $item=$entry['item']; $mode=$modeIndex[max(1,min(5,(int)data_get($item,'affected_area_image',4)))] ?? 'white'; $imageUrl=data_get($reportAssets,"baseline.parameter.$mode"); @endphp
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}">@include('pdf.facial.partials.parameter-grid-card',['item'=>$item,'imageUrl'=>$imageUrl])</td>
@endforeach
</tr></table>
@php $entry=$items->get(2); @endphp
@if($entry)
@php $item=$entry['item']; $mode=$modeIndex[max(1,min(5,(int)data_get($item,'affected_area_image',4)))] ?? 'white'; $imageUrl=data_get($reportAssets,"baseline.parameter_wide.$mode"); @endphp
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:66mm;"><tr><td width="25%" class="pad-sm" style="text-align:center;">@if($imageUrl)<img src="{{ $imageUrl }}" width="155" height="205" style="width:41mm;height:54.2mm;" alt="{{ data_get($item,'parameter_name') }}">@endif</td><td width="55%" class="pad"><div class="parameter-name">{{ data_get($item,'parameter_name') }}</div><div class="parameter-copy">{{ data_get($item,'client_description',data_get($item,'score_explanation','')) }}</div>@if(collect(data_get($item,'possible_causes',[]))->filter()->isNotEmpty())<div class="parameter-causes"><b>Common contributors</b><br>{{ collect(data_get($item,'possible_causes',[]))->filter()->take(2)->implode(' | ') }}</div>@endif</td><td width="20%" class="pad" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.score',['item'=>$item])</td></tr></table>
@endif
<table width="100%" cellpadding="0" cellspacing="0" class="card card-gold" style="margin-top:3mm;height:48mm;"><tr><td width="15%" class="pad" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets['icon_target']))<img src="{{ $uiAssets['icon_target'] }}" width="52" height="52" style="width:13.8mm;height:13.8mm;" alt="Next steps">@endif</td><td width="65%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#e5c477;">What happens next</div><div class="mini-value-lg" style="margin-top:1mm;">Clinician review and personalised treatment selection</div><div class="section-copy" style="margin-top:1mm;">Your clinician can combine these findings with your history and treatment-suitability checks to finalise the most appropriate plan.</div></td><td width="20%" class="pad" style="text-align:center;vertical-align:middle;"><span class="pill pill-gold">Doctor review</span></td></tr></table>
@endif
@if(!$loop->last)<pagebreak />@endif
@endforeach
@endsection
