@extends('pdf.pigmentation.master')
@section('content')

{{-- PAGE 1 --}}
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="18%" style="vertical-align:middle;"><img src="{{ $uiAssets['brand_icon'] }}" class="cover-logo" alt="AI Aesthetics"><div class="cover-brand-name" style="margin-top:1mm;">AI AESTHETICS</div><div class="cover-brand-byline">by Dr. Aakriti Mehra</div></td>
<td width="72%" style="vertical-align:middle;text-align:center;"><div class="cover-title">Your <span class="gold">Pigmentation</span> <span class="coral">Decode</span></div><div class="cover-kicker">AI-Powered Skin Diagnosis Passport</div><div class="cover-subtitle">Advanced Pigment Analysis <span class="cyan">&nbsp;•&nbsp;</span> Personalised Clinical Insights <span class="cyan">&nbsp;•&nbsp;</span> Patient Education</div></td>
<td width="10%" style="vertical-align:top;"><div class="cover-page-number">Page 1 of 7</div></td>
</tr>
</table>
<div class="header-rule"></div>
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="40%"><div class="image-box"><img src="{{ $assets['cover_main'] }}" width="286" height="374" style="width:75.7mm;height:99mm;" alt="White light image"></div></td>
<td width="3%"></td>
<td width="57%">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="48%" style="text-align:center;vertical-align:middle;"><img src="{{ $uiAssets['ring_8'] }}" width="176" height="176" style="width:46.6mm;height:46.6mm;" alt="Eight diagnostic markers"></td>
<td width="3%"></td>
<td width="49%" class="card card-gold pad" style="height:44mm;text-align:center;vertical-align:middle;"><div class="mini-label">Fitzpatrick Type</div><div class="metric-number gold" style="margin-top:3mm;">{{ $fitz }}</div><div class="metric-caption" style="margin-top:2mm;">Skin phototype</div></td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" class="card card-purple" style="margin-top:3mm;height:49mm;"><tr><td class="pad-lg" style="vertical-align:middle;"><table width="100%" cellpadding="0" cellspacing="0"><tr><td width="15%"><img src="{{ $uiAssets['icon_glow'] }}" class="icon-md" alt="Summary"></td><td width="85%"><div class="panel-title purple">Your Pigment Summary</div><div class="copy-lg" style="margin-top:1.5mm;">{{ $shortSummary }}</div></td></tr></table></td></tr></table>
</td>
</tr>
</table>
<div class="card" style="margin-top:3mm;padding:2.2mm;">
<div class="eyebrow" style="text-align:center;margin-bottom:1.4mm;">5-mode pigment analysis snapshot</div>
<table width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach($snapshotOrder as $mode)
<td width="20%" class="snapshot-cell" style="{{ !$loop->first ? 'border-left:0.22mm solid #223e53;' : '' }}">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td class="snapshot-label {{ $mode['colour'] }}">{{ $mode['title'] }}</td></tr>
<tr><td class="snapshot-box"><img src="{{ $assets['snapshots'][$mode['key']] }}" width="82" height="102" style="width:21.7mm;height:27mm;" alt="{{ $mode['title'] }}"></td></tr>
</table>
</td>
@endforeach
</tr></table>
</div>
<div class="eyebrow" style="text-align:center;margin-top:3mm;">Your top pigment priorities</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.3mm;"><tr>
@foreach($priorities as $priority)
<td width="32%" class="card card-{{ $priority['colour'] }} pad-sm"><table width="100%"><tr><td width="24%"><img src="{{ $uiAssets[$priority['icon']] }}" class="icon-md" alt="{{ $priority['short_title'] }}"></td><td width="76%"><div class="mini-value-lg {{ $priority['colour'] }}">{{ $priority['short_title'] }}</div><div class="copy" style="margin-top:0.7mm;">{{ $priority['short_copy'] }}</div></td></tr></table></td>
@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table>
<pagebreak />

{{-- PAGE 2 --}}
@include('pdf.pigmentation.partials.header',['kicker'=>'Pigmentation at a glance','page'=>2])
<div class="eyebrow">Your current profile</div>
<div class="section-title"><span class="gold">Pigmentation</span> at a Glance</div>
<div class="page-subtitle">The most useful patient-facing measurements and the main visible patterns from five imaging modes.</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
<td width="48%" class="card card-purple pad-lg" style="height:72mm;vertical-align:middle;"><div class="panel-title purple">Your Results Summary</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr><td width="50%" style="text-align:center;border-right:0.24mm solid #29475d;"><div class="metric-number gold">{{ $melanin }}</div><div class="metric-caption">Melanin load /100</div><div class="mini-value-lg gold" style="margin-top:2mm;">{{ $melaninBand }}</div></td><td width="50%" style="text-align:center;"><div class="metric-number cyan">{{ $erythema }}</div><div class="metric-caption">Erythema load /100</div><div class="mini-value-lg cyan" style="margin-top:2mm;">{{ $erythemaBand }}</div></td></tr></table></td>
<td width="3%"></td>
<td width="49%"><table width="100%" cellpadding="0" cellspacing="0">
@foreach($profileRows as $row)
<tr>
@foreach($row as $item)
<td width="49%" class="card card-{{ $item['border'] }} pad-sm" style="height:21mm;"><table width="100%"><tr><td width="27%"><img src="{{ $uiAssets[$item['icon']] }}" class="icon-sm" alt="{{ $item['label'] }}"></td><td width="73%"><div class="mini-label">{{ $item['label'] }}</div><div class="mini-value-lg {{ $item['colour'] }}" style="margin-top:1mm;">{{ $item['value'] }}</div></td></tr></table></td>
@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr>
@if(!$loop->last)<tr><td colspan="3" style="height:2mm;"></td></tr>@endif
@endforeach
</table></td>
</tr></table>
<div class="card card-gold pad" style="margin-top:3mm;height:43mm;"><div class="panel-title purple">Main Findings</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($main as $finding)
<td width="32%" class="card-soft pad-sm"><div class="mini-value-lg {{ $loop->index===2 ? 'purple' : 'gold' }}">{{ $finding['short_title'] }}</div><div class="copy" style="margin-top:1mm;">{{ $finding['short_copy'] }}</div></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table></div>
<div class="card card-cyan pad" style="margin-top:3mm;height:40mm;"><div class="panel-title purple">What This Means</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($whatThisMeans as $mean)
<td width="32%" class="card-soft pad-sm"><div class="mini-value-lg {{ $mean['colour'] }}">{{ $mean['title'] }}</div><div class="copy" style="margin-top:1mm;">{{ $mean['copy'] }}</div></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table></div>
<pagebreak />

{{-- PAGE 3 --}}
@include('pdf.pigmentation.partials.header',['kicker'=>'Pigment opportunity map','page'=>3])
<div class="eyebrow">Where your skin needs focus</div>
<div class="section-title"><span class="gold">Pigment Opportunity</span> Map</div>
<div class="page-subtitle">The map is flattened onto the patient image before mPDF receives it. Patient left appears on viewer right.</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
<td width="20%" style="vertical-align:middle;"><table width="100%" cellpadding="0" cellspacing="0">
@foreach($mapLeft as $item)
<tr><td class="card card-{{ $item['colour'] }} pad-sm"><div class="mini-value-lg {{ $item['colour'] }}">{{ $item['title'] }}</div><div class="copy" style="margin-top:1mm;">{{ $item['copy'] }}</div></td></tr>@if(!$loop->last)<tr><td style="height:5mm;"></td></tr>@endif
@endforeach
</table></td>
<td width="3%"></td>
<td width="54%" style="text-align:center;"><div class="image-box"><img src="{{ $assets['map'] }}" width="398" height="506" style="width:105.3mm;height:133.9mm;" alt="Pigment opportunity map"></div></td>
<td width="3%"></td>
<td width="20%" style="vertical-align:middle;"><table width="100%" cellpadding="0" cellspacing="0">
@foreach($mapRight as $item)
<tr><td class="card card-{{ $item['colour'] }} pad-sm"><div class="mini-value-lg {{ $item['colour'] }}">{{ $item['title'] }}</div><div class="copy" style="margin-top:1mm;">{{ $item['copy'] }}</div></td></tr>@if(!$loop->last)<tr><td style="height:3mm;"></td></tr>@endif
@endforeach
</table></td>
</tr></table>
<div class="card pad" style="margin-top:3mm;"><div class="eyebrow" style="text-align:center;margin-bottom:2mm;">Map legend</div><table width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach($mapLegend as $legend)
<td width="24%" class="card-soft pad-sm"><div class="mini-value {{ $legend['colour'] }}">{{ $legend['title'] }}</div><div class="micro" style="margin-top:1mm;">{{ $legend['copy'] }}</div></td>@if(!$loop->last)<td width="1.3%"></td>@endif
@endforeach
</tr></table></div>
<div class="card card-blue pad-sm" style="margin-top:3mm;height:20mm;"><table width="100%"><tr><td width="12%" style="text-align:center;"><img src="{{ $uiAssets['icon_camera'] }}" class="icon-sm" alt="Orientation"></td><td width="88%"><div class="mini-value-lg blue">Orientation and precision note</div><div class="micro" style="margin-top:0.6mm;">This is a regional guide from JSON region codes, not a pixel-precise lesion boundary.</div></td></tr></table></div>
<pagebreak />

{{-- PAGE 4 --}}
@include('pdf.pigmentation.partials.header',['kicker'=>'Main pigment findings','page'=>4])
<div class="eyebrow">What we see in your skin</div>
<div class="section-title">Your <span class="gold">Main Pigment</span> Findings</div>
<div class="page-subtitle">Three patient-facing patterns are shown without internal confidence percentages or workflow labels.</div>
@foreach($main as $finding)
<table width="100%" cellpadding="0" cellspacing="0" class="card card-{{ $finding['border'] }}" style="margin-top:3mm;height:47mm;"><tr>
<td width="13%" class="pad-sm" style="text-align:center;vertical-align:middle;"><img src="{{ $uiAssets[$finding['icon']] }}" class="icon-lg" alt="{{ $finding['short_title'] }}"><div class="metric-number {{ $finding['colour'] }}" style="font-size:18pt;margin-top:1mm;">{{ $loop->iteration }}</div></td>
<td width="62%" class="pad"><div class="panel-title-sm {{ $finding['colour'] }}">{{ $finding['title'] }}</div><div class="copy" style="margin-top:1.2mm;">{{ $finding['copy'] }}</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr><td width="48%"><div class="mini-label {{ $finding['colour'] }}">Where we see it</div><div class="micro" style="margin-top:0.7mm;">{{ $finding['where'] }}</div></td><td width="4%"></td><td width="48%"><div class="mini-label {{ $finding['colour'] }}">Why it matters</div><div class="micro" style="margin-top:0.7mm;">{{ $finding['why'] }}</div></td></tr></table></td>
<td width="25%" class="pad-sm" style="text-align:center;vertical-align:middle;"><img src="{{ $finding['image'] }}" width="155" height="163" style="width:41mm;height:43.1mm;" alt="{{ $finding['short_title'] }}"></td>
</tr></table>
@endforeach
<div class="card card-purple pad" style="margin-top:3mm;height:34mm;"><div class="panel-title purple">What Is Likely Driving This?</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($drivers as $driver)
<td width="32%" class="card-soft pad-sm"><div class="mini-value-lg {{ $driver['colour'] }}">{{ $driver['title'] }}</div><div class="micro" style="margin-top:0.8mm;">{{ $driver['copy'] }}</div></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table></div>
<pagebreak />

{{-- PAGE 5 --}}
@include('pdf.pigmentation.partials.header',['kicker'=>'Modifiers and cautions','page'=>5])
<div class="eyebrow">What can influence results</div>
<div class="section-title">Important <span class="gold">Modifiers</span> &amp; <span class="coral">Cautions</span></div>
<div class="page-subtitle">These findings explain why every dark area should not be treated in the same way.</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;">
@foreach(array_chunk($cautions,2) as $row)
<tr>
@foreach($row as $item)
<td width="49%" class="card card-{{ $item['border'] }} pad-lg" style="height:64mm;"><table width="100%"><tr><td width="20%"><img src="{{ $uiAssets[$item['icon']] }}" class="icon-lg" alt="{{ $item['title'] }}"></td><td width="80%"><div class="mini-label {{ $item['colour'] }}">{{ $item['number'] }}</div><div class="panel-title-sm {{ $item['colour'] }}" style="margin-top:0.8mm;">{{ $item['title'] }}</div></td></tr></table><div class="rule" style="margin-top:2.5mm;"></div><div class="copy-lg" style="margin-top:3mm;">{{ $item['copy'] }}</div></td>
@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr>
@if(!$loop->last)<tr><td colspan="3" style="height:3mm;"></td></tr>@endif
@endforeach
</table>
<div class="card card-purple pad" style="margin-top:3mm;height:44mm;"><div class="panel-title purple">How This Changes Your Care</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($careCards as $item)
<td width="32%" class="card-soft pad-sm"><table width="100%"><tr><td width="24%"><img src="{{ $uiAssets[$item['icon']] }}" class="icon-md" alt="{{ $item['title'] }}"></td><td width="76%"><div class="mini-value-lg {{ $item['colour'] }}">{{ $item['title'] }}</div><div class="micro" style="margin-top:0.8mm;">{{ $item['copy'] }}</div></td></tr></table></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table></div>
<pagebreak />

{{-- PAGE 6 --}}
@include('pdf.pigmentation.partials.header',['kicker'=>'Five-mode imaging review','page'=>6])
<div class="eyebrow">What each imaging mode reveals</div>
<div class="section-title">5-Mode <span class="gold">Imaging</span> <span class="coral">Review</span></div>
<div class="page-subtitle">Visualising pigment depths across the 5 light modes.</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
@foreach($modes as $mode)
<td width="20%" class="snapshot-cell" style="{{ !$loop->first ? 'border-left:0.25mm solid #223e53;' : '' }}">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td class="snapshot-label {{ $mode['colour'] }}">{{ $mode['title'] }}</td></tr>
<tr><td class="snapshot-box"><img src="{{ $mode['image'] }}" width="138" height="164" style="width:36.5mm;height:43.4mm;" alt="{{ $mode['title'] }}"></td></tr>
</table>
</td>
@endforeach
</tr></table>
<div class="card pad" style="margin-top:3mm;"><div class="panel-title purple">What Each Mode Helps Reveal in This Case</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;">
@foreach($modes as $mode)
<tr><td width="29%" class="card-soft pad-sm"><div class="mini-value-lg {{ $mode['colour'] }}">{{ $mode['title'] }}</div><div class="micro" style="margin-top:0.5mm;">{{ $mode['caption'] }}</div></td><td width="2%"></td><td width="69%" class="card-soft pad-sm"><div class="copy">- {{ $mode['lines'][0] }}<br>- {{ $mode['lines'][1] }}</div></td></tr>
@if(!$loop->last)<tr><td colspan="3" style="height:1.8mm;"></td></tr>@endif
@endforeach
</table></div>
<div class="card card-cyan pad" style="margin-top:3mm;height:38mm;"><div class="panel-title purple">What Stood Out Most</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($standouts as $item)
<td width="32%" class="card-soft pad-sm"><div class="mini-value-lg {{ $item['colour'] }}">{{ $item['title'] }}</div><div class="micro" style="margin-top:0.8mm;">{{ $item['copy'] }}</div></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table></div>
<pagebreak />

{{-- PAGE 7 --}}
@include('pdf.pigmentation.partials.header',['kicker'=>'Summary and next step','page'=>7])
<div class="eyebrow">Bringing everything together</div>
<div class="section-title">Your Pigment <span class="gold">Summary</span> &amp; <span class="coral">Next Step</span></div>
<div class="page-subtitle">A patient-friendly summary of what was found and what will matter most for progress.</div>
<div class="card card-purple pad-lg" style="margin-top:3mm;height:48mm;"><table width="100%"><tr><td width="11%" style="text-align:center;"><img src="{{ $uiAssets['icon_glow'] }}" class="icon-lg" alt="Summary"></td><td width="89%"><div class="panel-title purple">Your Pigmentation Summary</div><div class="copy-lg" style="margin-top:1.5mm;">{{ $summary }}</div></td></tr></table></div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
@foreach($summaryColumns as $item)
<td width="32%" class="card card-{{ $item['border'] }} pad-lg" style="height:72mm;"><table width="100%"><tr><td width="24%"><img src="{{ $uiAssets[$item['icon']] }}" class="icon-md" alt="{{ $item['title'] }}"></td><td width="76%"><div class="panel-title-sm {{ $item['colour'] }}">{{ $loop->iteration }}. {{ $item['title'] }}</div></td></tr></table><div style="margin-top:3mm;">@foreach($item['points'] as $point)<table class="check-table" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:2.3mm;"><tr><td width="10%"><img src="{{ $uiAssets['icon_check'] }}" class="check-icon" alt="Check"></td><td width="90%" class="copy">{{ $point }}</td></tr></table>@endforeach</div></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table>
<div class="eyebrow" style="margin-top:3mm;">Your next-step journey</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($journey as $item)
<td width="24%" class="card-soft pad-sm" style="height:39mm;text-align:center;"><img src="{{ $uiAssets[$item['icon']] }}" class="icon-md" alt="{{ $item['title'] }}"><div class="mini-value {{ $item['colour'] }}" style="margin-top:1.5mm;">{{ $loop->iteration }}. {{ $item['title'] }}</div><div class="micro" style="margin-top:1mm;">{{ $item['copy'] }}</div></td>@if(!$loop->last)<td width="1.33%"></td>@endif
@endforeach
</tr></table>
<div class="card card-gold pad-lg" style="margin-top:3mm;height:29mm;"><table width="100%"><tr><td width="13%" style="text-align:center;"><img src="{{ $uiAssets['icon_target'] }}" class="icon-md" alt="Next step"></td><td width="62%"><div class="copy-lg">This report explains the visible pigmentation pattern so that care can be planned with greater clarity. The treatment roadmap is generated separately after the diagnosis is reviewed.</div></td><td width="25%" style="text-align:center;vertical-align:middle;"><div style="font-family:playfairreportv36,dejavuserif,serif;font-style:italic;font-size:15pt;line-height:1.1;color:#efc26f;">Your skin.<br><span class="purple">Our expertise.</span><br><span class="coral">Real results.</span></div></td></tr></table></div>

@endsection
