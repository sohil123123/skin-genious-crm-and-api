@extends('pdf.facial.master')
@section('content')
@php
// A presentation projection only: never write these fields back to assessment data.
$results=collect($reassessment ?? [])->map(function($item){
    $before=data_get($item,'before_treatment_score_or_label');
    $after=data_get($item,'post_treatment_score_or_label');
    if(data_get($item,'score_polarity')==='higher_is_better' && is_numeric($before) && is_numeric($after)) {
        $delta=(float)$after-(float)$before;
        $item['result']=$delta>2?'improved':'stable';
        if($delta<0){
            $item['post_treatment_score_or_label']=$before;
            $name=strtolower((string)data_get($item,'parameter_name',''));
            $isRedness=str_contains($name,'redness') || str_contains($name,'vascular');
            $item['score_explanation']=$isRedness
                ? 'Post-treatment redness can be temporary. The before score is retained in this summary; the clinic keeps the scan reading for review.'
                : 'The before score is retained in this summary. The clinic keeps the scan reading for review.';
            $item['transient_reactivity_note']=$isRedness ? 'Post-treatment redness can be temporary; persistent or worsening redness should be reviewed by your clinician.' : null;
        } elseif($delta>0 && $delta<=2) {
            $item['score_explanation']='Small measured increase of '.$delta.' point(s); broadly stable at the current reporting threshold.';
        }
    }
    return $item;
});
$composedName=trim((string)data_get($patient ?? [],'first_name','').' '.(string)data_get($patient ?? [],'last_name',''));
$patientName=data_get($patient ?? [],'full_name',data_get($patient ?? [],'name',$composedName ?: 'N/A'));
$patientAge=data_get($patient ?? [],'age','N/A');
$patientGender=strtoupper(substr((string)data_get($patient ?? [],'gender','N/A'),0,1));
$reportDateFormatted=!empty($report_date)?date('d/m/Y',strtotime($report_date)):date('d/m/Y');
$skinTypeItem=$results->get('skin_type',[]);
$skinProfile=data_get($skinTypeItem,'post_treatment_score_or_label',data_get($patient ?? [],'skin_type','N/A'));
$improvedItems=$results->filter(fn($item)=>strtolower((string)data_get($item,'result'))==='improved')->values();
$stableItems=$results->filter(fn($item)=>strtolower((string)data_get($item,'result'))==='stable')->values();
$numericResults=$results->except('skin_type')->values();
$scoreboardImproved=$improvedItems->take(4)->values();
$scoreboardOther=$numericResults->reject(fn($item)=>strtolower((string)data_get($item,'result'))==='improved')->values();
$additionalImprovementCount=max(0,$improvedItems->count()-$scoreboardImproved->count());
$leftResults=$numericResults->slice(0,7)->values();
$rightResults=$numericResults->slice(7,7)->values();
$modeIndex=[1=>'red',2=>'subsurface_polarized',3=>'surface_polarized',4=>'white',5=>'woods_uv'];
$beforeModeFor=fn($item)=>$modeIndex[max(1,min(5,(int)data_get($item,'before_image',4)))] ?? 'white';
$afterModeFor=fn($item)=>$modeIndex[max(1,min(5,(int)data_get($item,'post_treatment_image',4)))] ?? 'white';
$stableStrengths=$stableItems->filter(function($item){if(data_get($item,'parameter_name')==='Skin Type')return true;$score=data_get($item,'post_treatment_score_or_label');return is_numeric($score)&&(float)$score>=85;})->values();
$monitorItems=$stableItems->reject(fn($item)=>$stableStrengths->contains($item))->values();
$transientNotes=$results->pluck('transient_reactivity_note')->filter(fn($n)=>$n&&$n!=='none')->unique()->values();
$improvementPages=$improvedItems->count()<=4?collect([$improvedItems]):$improvedItems->chunk(6)->values();
if($improvementPages->isEmpty())$improvementPages=collect([collect()]);
$iconFor=function($name){$n=strtolower((string)$name);if(str_contains($n,'hydration'))return'icon_hydration';if(str_contains($n,'glow')||str_contains($n,'radiance'))return'icon_glow';if(str_contains($n,'pore')||str_contains($n,'texture'))return'icon_pores';if(str_contains($n,'barrier')||str_contains($n,'redness'))return'icon_barrier';if(str_contains($n,'eye')||str_contains($n,'orbital'))return'icon_eye';return'icon_target';};
$coverSummary='This report highlights improvements and stable display scores. Where a scan reads lower, the before score is retained; the clinic keeps the actual reading for review.';
@endphp

{{-- PAGE 1 - PROGRESS DASHBOARD --}}
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="34%" style="vertical-align:middle;">
<table cellpadding="0" cellspacing="0"><tr>
<td width="23mm" style="vertical-align:middle;">@if(!empty($uiAssets['brand_icon']))<img src="{{ $uiAssets['brand_icon'] }}" width="70" height="70" style="width:18.5mm;height:18.5mm;" alt="AI Aesthetics">@endif</td>
<td style="padding-left:2mm;vertical-align:middle;"><div class="brand-name" style="font-size:12.3pt;">A.I. AESTHETICS</div><div class="brand-byline">By Dr. Aakriti Mehra</div></td>
</tr></table>
</td>
<td width="66%" style="vertical-align:middle;text-align:right;"><div class="cover-title" style="font-size:26pt;">Your Skin <span style="color:#f1d29b;font-style:italic;">Progress Passport</span></div><div class="cover-subtitle">AI-Powered Facial Reassessment <span class="dot">|</span> Measured Scan Comparison <span class="dot">|</span> 5-Mode Comparison</div></td>
</tr></table>
<div class="header-rule" style="margin-top:2.5mm;"></div>

<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="26%" style="vertical-align:top;">
<div style="text-align:center;padding:0.5mm 1mm 2.8mm;">@if(!empty($uiAssets['ring_15']))<img src="{{ $uiAssets['ring_15'] }}" width="150" height="150" style="width:39.7mm;height:39.7mm;" alt="15 total parameters">@endif
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;height:9.5mm;"><tr><td width="50%"><div class="mini-value-lg" style="color:#63d8cf;">{{ $improvedItems->count() }}</div><div class="mini-label">Improved</div></td><td width="50%"><div class="mini-value-lg" style="color:#c1a9ff;">{{ $stableItems->count() }}</div><div class="mini-label">Stable</div></td></tr></table>
</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1mm;"><tr><td style="height:5mm;border-top:0.28mm solid #29475d;font-size:0;line-height:0;">&nbsp;</td></tr></table>
<div style="padding:2.8mm 2.5mm 3mm;background-color:#081a2b;"><div class="mini-label" style="color:#bda5ff;margin-bottom:2mm;line-height:1.2;">Key Improvements</div>
@foreach($improvedItems->take(4) as $item)
@php $icon=$iconFor(data_get($item,'parameter_name')); @endphp
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.4mm;"><tr><td width="17%">@if(!empty($uiAssets[$icon]))<img src="{{ $uiAssets[$icon] }}" width="28" height="28" style="width:7.4mm;height:7.4mm;" alt="Improvement">@endif</td><td width="49%" class="mini-value" style="vertical-align:middle;font-size:9pt;line-height:1.2;">{{ data_get($item,'parameter_name') }}</td><td width="34%" style="text-align:right;vertical-align:middle;"><span class="mini-value">{{ data_get($item,'before_treatment_score_or_label') }}</span><span style="display:inline-block;width:6mm;text-align:center;">@include('pdf.facial.partials.arrow')</span><span class="mini-value" style="color:#63d8cf;">{{ data_get($item,'post_treatment_score_or_label') }}</span></td></tr></table>
@endforeach
@if($improvedItems->count()>4)<div class="dashboard-copy" style="margin-top:1.5mm;">+ {{ $improvedItems->count()-4 }} more improvements shown on the detailed gains page.</div>@endif
</div>
</td>
<td width="2%"></td>
<td width="47%" style="vertical-align:top;"><div class="mini-label" style="text-align:center;margin-bottom:1.2mm;color:#63d8cf;">Current White Light</div><div class="hero-box">@if(!empty($reportAssets['post']['cover_tall']['white']))<img src="{{ $reportAssets['post']['cover_tall']['white'] }}" width="328" height="437" style="width:86.8mm;height:115.6mm;" alt="Post-treatment white light">@endif</div></td>
<td width="2%"></td>
<td width="23%" style="vertical-align:top;">
<div style="padding:0 1mm 2mm;"><div class="mini-label" style="color:#bda5ff;text-align:center;">Baseline White Light</div>@if(!empty($reportAssets['baseline']['baseline_small']['white']))<div style="text-align:center;margin-top:1.5mm;"><img src="{{ $reportAssets['baseline']['baseline_small']['white'] }}" width="137" height="183" style="width:36.2mm;height:48.4mm;" alt="Baseline white light"></div>@endif<div class="dashboard-copy" style="margin-top:1.5mm;text-align:center;line-height:1.35;">Same controlled mode for a like-for-like comparison.</div></div>
<div style="margin-top:3mm;padding:2.5mm;background-color:#081a2b;"><table width="100%" cellpadding="0" cellspacing="0"><tr><td width="24%" style="vertical-align:middle;">@if(!empty($uiAssets['icon_glow']))<img src="{{ $uiAssets['icon_glow'] }}" width="38" height="38" style="width:10mm;height:10mm;" alt="Summary">@endif</td><td width="76%" style="vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">AI Summary</div></td></tr></table><div style="margin-top:2mm;font-size:8.6pt;line-height:1.42;color:#dce8ee;">{{ $coverSummary }}</div></div>
</td>
</tr></table>

<div class="card card-teal" style="margin-top:3mm;padding:3mm;"><div class="mini-label" style="color:#63d8cf;margin-bottom:1mm;">Comparison at a glance</div><table width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach($improvedItems->take(4) as $item)
@php $icon=$iconFor(data_get($item,'parameter_name')); @endphp
<td width="25%" style="{{ !$loop->first?'border-left:0.25mm solid #29475d;':'' }}padding:1.2mm 2mm;"><table cellpadding="0" cellspacing="0"><tr><td>@if(!empty($uiAssets[$icon]))<img src="{{ $uiAssets[$icon] }}" width="34" height="34" style="width:9mm;height:9mm;" alt="Metric">@endif</td><td style="padding-left:1.5mm;vertical-align:middle;"><div class="mini-value">{{ data_get($item,'parameter_name') }}</div><div><span class="mini-value-lg">{{ data_get($item,'before_treatment_score_or_label') }}</span><span style="display:inline-block;width:7mm;text-align:center;">@include('pdf.facial.partials.arrow')</span><span class="mini-value-lg" style="color:#63d8cf;">{{ data_get($item,'post_treatment_score_or_label') }}</span></div></td></tr></table></td>
@endforeach
</tr></table></div>

<div class="card" style="margin-top:3mm;padding:2mm;"><div class="mini-label" style="color:#bda5ff;margin-bottom:1mm;">5-Mode Comparative Analysis</div><table width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach(['white'=>'Visible','surface_polarized'=>'Surface','subsurface_polarized'=>'Subsurface','red'=>'Red Mode','woods_uv'=>'Woods UV'] as $mode=>$label)
<td width="20%" style="text-align:center;{{ !$loop->first?'border-left:0.25mm solid #223e53;':'' }}"><div class="mini-label" style="color:#63d8cf;font-size:6.5pt;">{{ $label }}</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1mm;"><tr><td width="49%">@if(!empty($reportAssets['baseline']['mode_pair'][$mode]))<img src="{{ $reportAssets['baseline']['mode_pair'][$mode] }}" width="55" height="73" style="width:14.6mm;height:19.3mm;" alt="Before {{ $label }}">@endif<div class="mini-label">Before</div></td><td width="2%"></td><td width="49%">@if(!empty($reportAssets['post']['mode_pair'][$mode]))<img src="{{ $reportAssets['post']['mode_pair'][$mode] }}" width="55" height="73" style="width:14.6mm;height:19.3mm;" alt="After {{ $label }}">@endif<div class="mini-label">After</div></td></tr></table></td>
@endforeach
</tr></table></div>

<table width="100%" cellpadding="0" cellspacing="0" class="card-soft" style="margin-top:3mm;"><tr>
<td width="28%" class="pad-sm"><table cellpadding="0" cellspacing="0"><tr><td>@if(!empty($uiAssets['icon_user']))<img src="{{ $uiAssets['icon_user'] }}" width="34" height="34" style="width:9mm;height:9mm;" alt="Patient">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label">Patient Name</div><div class="mini-value">{{ $patientName }}</div></td></tr></table></td>
<td width="24%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><div class="mini-label">Report Type</div><div class="mini-value">Facial Reassessment</div></td>
<td width="25%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><table cellpadding="0" cellspacing="0"><tr><td>@if(!empty($uiAssets['icon_camera']))<img src="{{ $uiAssets['icon_camera'] }}" width="32" height="32" style="width:8.5mm;height:8.5mm;" alt="Analysis">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label">Analysis Type</div><div class="mini-value">5-Mode Comparison</div></td></tr></table></td>
<td width="23%" class="pad-sm" style="border-left:0.25mm solid #29475d;"><table cellpadding="0" cellspacing="0"><tr><td>@if(!empty($uiAssets['icon_calendar']))<img src="{{ $uiAssets['icon_calendar'] }}" width="32" height="32" style="width:8.5mm;height:8.5mm;" alt="Date">@endif</td><td style="padding-left:2mm;vertical-align:middle;"><div class="mini-label">Report Date</div><div class="mini-value">{{ $reportDateFormatted }}</div></td></tr></table></td>
</tr></table>
<pagebreak />

{{-- PAGE 2 - PRIORITISED SCOREBOARD --}}
@include('pdf.facial.partials.header',['kicker'=>'Before / After Scoreboard'])
<div class="eyebrow">Results at a glance</div>
<div class="section-title">How every parameter <span class="accent">changed</span></div>
<div class="page-subtitle">All numeric scores use 1-100, with higher scores indicating better skin health. Stable includes retained before scores when a scan reads lower; the clinic keeps actual readings for review.</div>

<table class="scoreboard-skin-card" cellpadding="0" cellspacing="0" style="margin-top:3mm;">
<tr style="height:25mm;">
<td width="49%"><div class="mini-value-lg" style="font-size:11.5pt;">Skin Type</div><div class="section-copy" style="margin-top:1mm;font-size:7.5pt;">{{ data_get($skinTypeItem,'score_explanation','Your skin type pattern is unchanged today.') }}</div></td>
<td width="17%" style="text-align:center;vertical-align:middle;"><div class="mini-label">Before</div><div class="mini-value" style="font-size:9pt;">{{ data_get($skinTypeItem,'before_treatment_score_or_label',$skinProfile) }}</div></td>
<td width="6%" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.arrow')</td>
<td width="17%" style="text-align:center;vertical-align:middle;"><div class="mini-label">After</div><div class="mini-value" style="font-size:9pt;">{{ data_get($skinTypeItem,'post_treatment_score_or_label',$skinProfile) }}</div></td>
<td width="11%" style="text-align:center;vertical-align:middle;"><span class="pill pill-gold">Stable</span></td>
</tr>
</table>

<div class="eyebrow" style="margin-top:3mm;">What improved</div>
@if($scoreboardImproved->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.4mm;">
@foreach($scoreboardImproved->chunk(2) as $row)
<tr>
@foreach($row as $item)
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}"><table width="100%" class="card-soft pad" cellpadding="0" cellspacing="0"><tr><td style="padding:3mm;">
<div class="mini-value">{{ data_get($item,'parameter_name') }}</div>
<div class="section-copy" style="margin-top:1mm;">Before: {{ data_get($item,'before_treatment_score_or_label') }} | After: {{ data_get($item,'post_treatment_score_or_label') }}</div>
<div class="mini-label" style="margin-top:1mm;">{{ ucfirst(data_get($item,'result','stable')) }}</div>
<div class="section-copy" style="margin-top:1mm;">{{ data_get($item,'score_explanation') }}</div>
</td></tr></table>
</td>
@endforeach
@if($row->count()===1)<td width="49%" style="border-left:3mm solid #061421;"><table class="scoreboard-improved-card card-purple" cellpadding="0" cellspacing="0"><tr style="height:33mm;"><td style="vertical-align:middle;text-align:center;"><div class="mini-label" style="color:#bda5ff;">Treatment response</div><div class="mini-value-lg" style="margin-top:1.5mm;">{{ $improvedItems->count() }} measurable improvement{{ $improvedItems->count()===1?'':'s' }}</div></td></tr></table></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:2.5mm;"></td></tr>@endif
@endforeach
</table>
@else
<div class="card-soft pad" style="margin-top:1.4mm;">No improved parameters were returned in this reassessment.</div>
@endif

<div class="eyebrow" style="margin-top:3mm;">Maintained and monitored</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.4mm;">
@foreach($scoreboardOther->chunk(2) as $row)
<tr>
@foreach($row as $item)
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}"><table width="100%" class="card-soft pad" cellpadding="0" cellspacing="0"><tr><td style="padding:3mm;">
<div class="mini-value">{{ data_get($item,'parameter_name') }}</div>
<div class="section-copy" style="margin-top:1mm;">Before: {{ data_get($item,'before_treatment_score_or_label') }} | After: {{ data_get($item,'post_treatment_score_or_label') }}</div>
<div class="mini-label" style="margin-top:1mm;">{{ ucfirst(data_get($item,'result','stable')) }}</div>
<div class="section-copy" style="margin-top:1mm;">{{ data_get($item,'score_explanation') }}</div>
</td></tr></table>
</td>
@endforeach
@if($row->count()===1)<td width="49%"></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:1.4mm;"></td></tr>@endif
@endforeach
</table>

<table class="scoreboard-summary-strip" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr style="height:16mm;">
<td width="13%" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets['icon_check']))<img src="{{ $uiAssets['icon_check'] }}" width="38" height="38" style="width:10mm;height:10mm;" alt="Overall result">@endif</td>
<td width="62%" style="vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">Overall treatment response</div><div class="mini-value" style="margin-top:0.8mm;font-size:9.2pt;">{{ $improvedItems->count() }} improved | {{ $stableItems->count() }} stable</div></td>
<td width="25%" style="text-align:right;vertical-align:middle;padding-right:3mm;"><span class="pill pill-green">Comparison complete</span></td>
</tr></table>
@if($additionalImprovementCount>0)<div class="dashboard-copy" style="margin-top:1mm;text-align:right;">+ {{ $additionalImprovementCount }} additional improvement{{ $additionalImprovementCount===1?' is':'s are' }} detailed on the next page.</div>@endif
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:44mm;"><tr style="height:44mm;">
<td width="18%" class="pad" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets['icon_check']))<img src="{{ $uiAssets['icon_check'] }}" width="58" height="58" style="width:15.3mm;height:15.3mm;" alt="Response summary">@endif<div class="mini-value-lg" style="margin-top:1.5mm;color:#63d8cf;font-size:12pt;">{{ $improvedItems->count() }} gains</div></td>
<td width="55%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">What this means</div><div class="section-copy-lg" style="margin-top:1.5mm;font-size:9pt;line-height:1.45;">Measured improvements are highlighted here. Small changes may remain broadly stable at the reporting threshold. Stable includes retained before scores; it does not always mean the measured reading was unchanged.</div></td>
<td width="27%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #3b4769;"><div class="mini-label">Result legend</div><div style="margin-top:1.5mm;"><span class="pill pill-green">Improved</span></div><div style="margin-top:1.5mm;"><span class="pill pill-gold">Stable</span></div></td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-teal" style="margin-top:3mm;height:42mm;"><tr style="height:42mm;">
<td width="33%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#63d8cf;">Higher is better</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Hydration and glow</div><div class="section-copy" style="margin-top:1mm;font-size:7.4pt;line-height:1.35;">A higher post-treatment score indicates a healthier immediate result.</div></td>
<td width="34%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #29475d;"><div class="mini-label" style="color:#bda5ff;">Higher is better</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Pores, redness and concern scores</div><div class="section-copy" style="margin-top:1mm;font-size:7.4pt;line-height:1.35;">A higher health score indicates less visible concern burden.</div></td>
<td width="33%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #29475d;"><div class="mini-label" style="color:#e5c477;">Balance target</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Skin sebum content</div><div class="section-copy" style="margin-top:1mm;font-size:7.4pt;line-height:1.35;">The health score increases as the measured oil state approaches its balance target.</div></td>
</tr></table>
<pagebreak />

{{-- ADAPTIVE IMPROVEMENT PAGE(S) --}}
@foreach($improvementPages as $improvementPageIndex=>$pageItems)
@include('pdf.facial.partials.header',['kicker'=>'Key Improvements'])
<div class="eyebrow">What moved in the right direction</div>
<div class="section-title">{{ $improvementPageIndex===0?'Your clearest':'More areas that' }} <span class="accent">{{ $improvementPageIndex===0?'treatment gains':'improved' }}</span></div>
<div class="page-subtitle" style="margin-bottom:3mm;">Each comparison uses the imaging mode referenced by the reassessment output for that parameter.</div>
@if($pageItems->isEmpty())
<div class="card-soft pad-lg">No improved parameters were returned in this reassessment.</div>
@elseif($pageItems->count()<=4)
<table width="100%" cellpadding="0" cellspacing="0">
@foreach($pageItems->chunk(2) as $row)
<tr>
@foreach($row as $item)
@php $bMode=$beforeModeFor($item);$aMode=$afterModeFor($item);$bImg=data_get($reportAssets,"baseline.result_large.$bMode");$aImg=data_get($reportAssets,"post.result_large.$aMode"); @endphp
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}">@include('pdf.facial.partials.improvement-card',['item'=>$item,'beforeImage'=>$bImg,'afterImage'=>$aImg,'compact'=>false])</td>
@endforeach
@if($row->count()===1)<td width="49%" class="card-soft pad-lg" style="border-left:3mm solid #061421;vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">Overall Treatment Response</div><div class="section-copy-lg" style="margin-top:2mm;">{{ $pageItems->count() }} measurable improvement{{ $pageItems->count()===1?' was':'s were' }} identified in this session.</div></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:3mm;"></td></tr>@endif
@endforeach
</table>
@else
<table width="100%" cellpadding="0" cellspacing="0">
@foreach($pageItems->chunk(2) as $row)
<tr>
@foreach($row as $item)
@php $bMode=$beforeModeFor($item);$aMode=$afterModeFor($item);$bImg=data_get($reportAssets,"baseline.result_compact.$bMode");$aImg=data_get($reportAssets,"post.result_compact.$aMode"); @endphp
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}">@include('pdf.facial.partials.improvement-card',['item'=>$item,'beforeImage'=>$bImg,'afterImage'=>$aImg,'compact'=>true])</td>
@endforeach
@if($row->count()===1)<td width="49%" class="card-soft pad" style="border-left:3mm solid #061421;vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">Overall Treatment Response</div><div class="section-copy-lg" style="margin-top:2mm;">{{ $pageItems->count() }} improvements are shown on this page. The remaining position is intentionally used as a summary panel.</div></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:3mm;"></td></tr>@endif
@endforeach
</table>
@endif
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:42mm;"><tr style="height:42mm;"><td width="14%" class="pad" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets['icon_target']))<img src="{{ $uiAssets['icon_target'] }}" width="52" height="52" style="width:13.8mm;height:13.8mm;" alt="Response summary">@endif</td><td width="61%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">Overall session response</div><div class="mini-value-lg" style="margin-top:1mm;font-size:11.5pt;">{{ $pageItems->count() }} measurable treatment gain{{ $pageItems->count()===1?'':'s' }} on this page</div><div class="section-copy" style="margin-top:1.2mm;font-size:8.3pt;line-height:1.4;">The strongest visible shifts are summarised above. These changes should be interpreted alongside controlled imaging, treatment-day context and clinician review.</div></td><td width="25%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #3b4769;"><div class="mini-label" style="color:#63d8cf;">Score direction</div><div class="section-copy" style="margin-top:1mm;font-size:7.4pt;line-height:1.35;">All displayed numeric health scores improve upward, including pores, redness and radiance.</div></td></tr></table>
<pagebreak />
@endforeach

{{-- STABLE PAGE --}}
@include('pdf.facial.partials.header',['kicker'=>'Stable Strengths and Areas Monitored'])
<div class="eyebrow">Stable does not mean unchanged care</div>
<div class="section-title">What remained <span class="accent">stable</span></div>
<div class="page-subtitle">Stable results separate maintained strengths from parameters that may need more time, repeated sessions or continued home care.</div>

<div class="eyebrow" style="margin-top:3mm;">Stable strengths</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;"><tr>
@forelse($stableStrengths->take(3) as $item)
<td width="32%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}">
<table class="stable-strength-card-v54" cellpadding="0" cellspacing="0"><tr style="height:31mm;"><td width="20%" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets['icon_stable']))<img src="{{ $uiAssets['icon_stable'] }}" width="42" height="42" style="width:11.1mm;height:11.1mm;" alt="Stable">@endif</td><td width="80%" style="vertical-align:middle;"><div class="mini-value" style="font-size:9pt;">{{ data_get($item,'parameter_name') }}</div><div class="dashboard-copy" style="margin-top:1mm;font-size:6.9pt;line-height:1.28;">{{ data_get($item,'score_explanation') }}</div></td></tr></table>
</td>
@empty<td class="card-soft pad">No stable-strength items were identified.</td>@endforelse
</tr></table>

<div class="eyebrow" style="margin-top:3mm;">Continue to monitor</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;">
@foreach($monitorItems->chunk(2) as $row)
<tr>
@foreach($row as $item)
@php $copy=(string)data_get($item,'score_explanation','');if(strlen($copy)>155){$cut=substr($copy,0,152);$copy=rtrim(substr($cut,0,strrpos($cut,' ') ?: 152)).'...';} @endphp
<td width="49%" style="{{ !$loop->first?'border-left:3mm solid #061421;':'' }}">
<table class="monitor-card-v54" cellpadding="0" cellspacing="0"><tr style="height:30mm;"><td width="15%" style="text-align:center;vertical-align:middle;"><div class="change-number" style="font-size:18pt;color:#c1a9ff;">{{ data_get($item,'post_treatment_score_or_label') }}</div></td><td width="85%" style="vertical-align:middle;"><div class="monitor-title-v54">{{ data_get($item,'parameter_name') }}</div><div class="monitor-copy-v54">{{ $copy }}</div></td></tr></table>
</td>
@endforeach
@if($row->count()===1)<td width="49%"></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="2" style="height:2mm;"></td></tr>@endif
@endforeach
</table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-gold" style="margin-top:3mm;height:28mm;"><tr style="height:28mm;"><td width="12%" class="pad" style="vertical-align:middle;text-align:center;">@if(!empty($uiAssets['icon_barrier']))<img src="{{ $uiAssets['icon_barrier'] }}" width="50" height="50" style="width:13.2mm;height:13.2mm;" alt="Treatment context">@endif</td><td width="88%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#e5c477;">Treatment-day context</div><div class="section-copy" style="margin-top:1mm;font-size:8.2pt;">{{ $transientNotes->isNotEmpty()?$transientNotes->implode(' | '):'Your clinic will interpret treatment-day changes and advise whether a repeat scan is needed.' }}</div></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-teal" style="margin-top:3mm;height:39mm;"><tr style="height:39mm;"><td width="33%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#63d8cf;">Maintained strengths</div><div class="section-copy" style="margin-top:1mm;font-size:7.7pt;line-height:1.38;">Stable display scores include maintained readings and retained before scores. Actual scan readings remain available to your clinic.</div></td><td width="34%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #29475d;"><div class="mini-label" style="color:#bda5ff;">Gradual-change areas</div><div class="section-copy" style="margin-top:1mm;font-size:7.7pt;line-height:1.38;">Firmness, pigmentation and structural changes often need repeated sessions before a meaningful score shift appears.</div></td><td width="33%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #29475d;"><div class="mini-label" style="color:#e5c477;">Next comparison</div><div class="section-copy" style="margin-top:1mm;font-size:7.7pt;line-height:1.38;">Continued home care and consistent imaging conditions make the next reassessment more informative.</div></td></tr></table>
<pagebreak />

{{-- COMPARISON PAGE 1 --}}
@include('pdf.facial.partials.header',['kicker'=>'Controlled Image Comparison'])
<div class="eyebrow">Like-for-like imaging</div>
<div class="section-title">White and <span class="accent">polarised views</span></div>
<div class="page-subtitle" style="margin-bottom:3mm;">Baseline and current images are shown using the same mode for a fair visual comparison.</div>
@include('pdf.facial.partials.comparison-row',['title'=>'White Light','description'=>'Natural tone, glow and visible balance','beforeImage'=>data_get($reportAssets,'baseline.compare_large.white'),'afterImage'=>data_get($reportAssets,'post.compare_large.white'),'observation'=>'Best for visible brightness, hydration, tone balance and overall treatment-day appearance.'])
@include('pdf.facial.partials.comparison-row',['title'=>'Surface Polarised','description'=>'Pores, texture and fine lines','beforeImage'=>data_get($reportAssets,'baseline.compare_large.surface_polarized'),'afterImage'=>data_get($reportAssets,'post.compare_large.surface_polarized'),'observation'=>'Supports closer comparison of surface clarity, pore visibility and fine textural change.'])
@include('pdf.facial.partials.comparison-row',['title'=>'Subsurface Polarised','description'=>'Sub-surface tone patterns','beforeImage'=>data_get($reportAssets,'baseline.compare_large.subsurface_polarized'),'afterImage'=>data_get($reportAssets,'post.compare_large.subsurface_polarized'),'observation'=>'Adds context for deeper tone distribution and less-visible sub-surface patterns.'])
<div class="card-soft pad-sm" style="margin-top:2mm;"><div class="mini-label" style="color:#bda5ff;">Interpretation rule</div><div class="section-copy" style="margin-top:0.7mm;">Like-for-like comparisons are meaningful only when the same imaging mode, crop and positioning are used.</div></div>
<pagebreak />

{{-- COMPARISON PAGE 2 --}}
@include('pdf.facial.partials.header',['kicker'=>'Controlled Image Comparison'])
<div class="eyebrow">Specialised evidence views</div>
<div class="section-title">Red and <span class="accent">Woods UV views</span></div>
<div class="page-subtitle" style="margin-bottom:3mm;">These modes help contextualise redness, vascular signals, fluorescence and superficial changes.</div>
@include('pdf.facial.partials.comparison-row',['title'=>'Red Light','description'=>'Redness and vascular signals','beforeImage'=>data_get($reportAssets,'baseline.compare_large.red'),'afterImage'=>data_get($reportAssets,'post.compare_large.red'),'observation'=>'Useful for interpreting reactive redness and distinguishing a temporary treatment-day flush from a broader pattern.'])
@include('pdf.facial.partials.comparison-row',['title'=>'Woods UV','description'=>'Fluorescence and superficial changes','beforeImage'=>data_get($reportAssets,'baseline.compare_large.woods_uv'),'afterImage'=>data_get($reportAssets,'post.compare_large.woods_uv'),'observation'=>'Supports assessment of fluorescence, surface dryness and superficial distribution changes.'])
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr><td width="49%" class="card card-purple pad-lg"><div class="mini-label" style="color:#bda5ff;">How to interpret these views</div><div class="section-copy-lg" style="margin-top:2mm;">Red mode and Woods UV should be interpreted together with the clinical assessment and the other three controlled views.</div></td><td width="2%"></td><td width="49%" class="card card-gold pad-lg"><div class="mini-label" style="color:#e5c477;">Treatment-day context</div><div class="section-copy-lg" style="margin-top:2mm;">Your clinic will interpret treatment-day changes and advise whether a repeat scan is needed.</div></td></tr></table>
<pagebreak />

{{-- FINAL PAGE --}}
@include('pdf.facial.partials.header',['kicker'=>'Protecting Your Results'])
<div class="eyebrow">Maintenance plan</div>
<div class="section-title">Keep the progress <span class="accent">working for you</span></div>
<div class="page-subtitle">The following guidance is carried forward from the current patient-facing reassessment report.</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:4mm;"><tr>
<td width="49%">@include('pdf.facial.partials.maintenance-card',['number'=>'01','title'=>'Gentle cleansing','copy'=>'Cleanse the face twice daily using a gentle cleanser that maintains the natural moisture barrier.','icon'=>'icon_cleanse','tone'=>'teal'])</td><td width="2%"></td>
<td width="49%">@include('pdf.facial.partials.maintenance-card',['number'=>'02','title'=>'Daily sun protection','copy'=>'Apply a broad-spectrum SPF 30+ sunscreen every morning, including on cloudy days.','icon'=>'icon_sun','tone'=>'teal'])</td>
</tr><tr><td colspan="3" style="height:3mm;"></td></tr><tr>
<td width="49%">@include('pdf.facial.partials.maintenance-card',['number'=>'03','title'=>'Hydration support','copy'=>'Use a hydrating serum containing hyaluronic acid to maintain moisture levels.','icon'=>'icon_hydration','tone'=>'gold'])</td><td width="2%"></td>
<td width="49%">@include('pdf.facial.partials.maintenance-card',['number'=>'04','title'=>'Controlled renewal','copy'=>'Apply a retinoid product 2-3 times per week in the evening to support cell turnover, where clinically appropriate.','icon'=>'icon_renewal','tone'=>'gold'])</td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-teal" style="margin-top:4mm;height:47mm;"><tr style="height:47mm;"><td width="14%" class="pad" style="text-align:center;vertical-align:middle;">@if(!empty($uiAssets['icon_calendar']))<img src="{{ $uiAssets['icon_calendar'] }}" width="58" height="58" style="width:15.3mm;height:15.3mm;" alt="Next milestone">@endif</td><td width="64%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#e5c477;">Your next milestone</div><div class="mini-value-lg" style="margin-top:1.2mm;font-size:12pt;">Continue with your approved plan</div><div class="section-copy" style="margin-top:1.5mm;font-size:8.6pt;line-height:1.42;">Future reassessment should follow the interval recommended by your clinician and treatment plan. Consistent home care makes subsequent comparisons more meaningful.</div></td><td width="22%" class="pad" style="text-align:center;vertical-align:middle;"><span class="pill pill-green" style="font-size:7pt;padding:1.5mm 2.5mm;">Book next review</span></td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:38mm;"><tr style="height:38mm;">
<td width="25%" class="pad" style="vertical-align:middle;"><div class="mini-label" style="color:#bda5ff;">Morning</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Cleanse + sunscreen</div><div class="dashboard-copy" style="margin-top:0.8mm;font-size:6.8pt;">Protect hydration and limit new UV-related stress.</div></td>
<td width="25%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #3b4769;"><div class="mini-label" style="color:#63d8cf;">During the day</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Reapply protection</div><div class="dashboard-copy" style="margin-top:0.8mm;font-size:6.8pt;">Especially after sweating, travel or prolonged exposure.</div></td>
<td width="25%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #3b4769;"><div class="mini-label" style="color:#e5c477;">Evening</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Cleanse + hydrate</div><div class="dashboard-copy" style="margin-top:0.8mm;font-size:6.8pt;">Support comfort and the moisture barrier overnight.</div></td>
<td width="25%" class="pad" style="vertical-align:middle;border-left:0.25mm solid #3b4769;"><div class="mini-label" style="color:#bda5ff;">2-3x weekly</div><div class="mini-value" style="margin-top:1mm;font-size:9pt;">Controlled renewal</div><div class="dashboard-copy" style="margin-top:0.8mm;font-size:6.8pt;">Only where clinically appropriate and tolerated.</div></td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card-soft" style="margin-top:3mm;height:18mm;"><tr style="height:18mm;"><td class="pad" style="vertical-align:middle;"><div class="mini-label">Clinical note</div><div class="section-copy" style="margin-top:0.8mm;font-size:7.8pt;">This patient-facing decision-support summary does not replace clinician review or an in-person medical diagnosis.</div></td><td width="22%" class="pad" style="text-align:right;vertical-align:middle;"><span class="pill pill-purple">Doctor review</span></td></tr></table>
@endsection
