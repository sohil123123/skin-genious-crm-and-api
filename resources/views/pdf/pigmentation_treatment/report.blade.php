@extends('pdf.pigmentation_treatment.master')
@section('content')

{{-- PAGE 1 --}}
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="18%" style="vertical-align:middle;"><img src="{{ $ui['brand_icon'] }}" class="cover-logo" alt="AI Aesthetics"><div class="cover-brand-name" style="margin-top:1mm;">AI AESTHETICS</div><div class="cover-brand-byline">by Dr. Aakriti Mehra</div></td>
<td width="72%" style="vertical-align:middle;text-align:center;"><div class="cover-title">Your <span class="gold">Pigmentation</span> <span class="coral">Treatment Roadmap</span></div><div class="cover-kicker">A Staged, Barrier-Aware Course</div><div class="cover-subtitle">Personalised Treatment Sequence <span class="cyan">&nbsp;•&nbsp;</span> Reassessment-Led Decisions <span class="cyan">&nbsp;•&nbsp;</span> Patient Education</div></td>
<td width="10%" style="vertical-align:top;"><div class="cover-page-number">Page 1 of 7</div></td>
</tr>
</table>
<div class="header-rule"></div>

<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="24%" class="card card-gold course-metric pad-sm">
  <img src="{{ $ui['icon_calendar'] }}" class="icon-md" alt="Duration">
  <div class="course-metric-number gold" style="font-size:16pt;margin-top:1.5mm;line-height:1.1;">{{ $courseDuration }}</div>
  <div class="course-metric-label" style="margin-top:1.5mm;">Estimated course</div>
</td>
<td width="1.33%"></td>
<td width="24%" class="card card-purple course-metric pad-sm">
  <img src="{{ $ui['icon_target'] }}" class="icon-md" alt="Sessions">
  <div class="course-metric-number purple" style="font-size:26pt;margin-top:1.5mm;">{{ $totalSessions }}</div>
  <div class="course-metric-label" style="margin-top:1.5mm;">Planned sessions</div>
</td>
<td width="1.33%"></td>
<td width="24%" class="card card-cyan course-metric pad-sm">
  <img src="{{ $ui['icon_camera'] }}" class="icon-md" alt="Reassessment">
  <div class="course-metric-number cyan" style="font-size:19pt;margin-top:2mm;">After {{ $reassessAfter }}</div>
  <div class="course-metric-label" style="margin-top:1.5mm;">Formal reassessment</div>
</td>
<td width="1.33%"></td>
<td width="24%" class="card card-coral course-metric pad-sm">
  <img src="{{ $ui['icon_stable'] }}" class="icon-md" alt="Adaptive plan">
  <div class="course-metric-number coral" style="font-size:19pt;margin-top:2mm;">Adaptive</div>
  <div class="course-metric-label" style="margin-top:1.5mm;">Plan changes with response</div>
</td>
</tr></table>

<div class="card card-purple pad-lg" style="margin-top:4mm;height:42mm;"><table width="100%"><tr><td width="12%" style="text-align:center;"><img src="{{ $ui['icon_glow'] }}" class="icon-lg" alt="Plan"></td><td width="88%"><div class="panel-title purple">{{ $planName }}</div><div class="copy-lg" style="margin-top:1.5mm;">{{ $clientExplanation }}</div></td></tr></table></div>

<div class="eyebrow" style="text-align:center;margin-top:4mm;">Your planned treatment mix</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;"><tr>
@foreach($treatmentMix as $mixItem)
<td width="32%" class="card card-{{ $mixItem['colour'] }} pad-lg" style="height:49mm;text-align:center;">
  <img src="{{ $ui[$mixItem['icon']] }}" class="icon-xl" alt="{{ $mixItem['label'] }}">
  <div class="metric-number {{ $mixItem['colour'] }}" style="font-size:25pt;margin-top:1mm;">{{ $mixItem['count'] }}</div>
  <div class="mini-value-lg {{ $mixItem['colour'] }}">{{ $mixItem['label'] }}</div>
  <div class="copy" style="margin-top:1.5mm;">{{ $mixItem['copy'] }}</div>
</td>
@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table>

<div class="card card-gold pad-lg" style="margin-top:4mm;height:31mm;"><table width="100%"><tr><td width="14%" style="text-align:center;"><img src="{{ $ui['icon_sun'] }}" class="icon-lg" alt="Sun protection"></td><td width="86%"><div class="panel-title-sm gold">The course depends on daily sun and heat control</div><div class="copy-lg" style="margin-top:1mm;">Visible improvement can be gradual. Consistent sunscreen reapplication, gentle skincare and avoiding unnecessary irritation are central parts of the plan.</div></td></tr></table></div>
<pagebreak />

{{-- PAGE 2 --}}
@include('pdf.pigmentation_treatment.partials.header',['kicker'=>'Plan at a glance','page'=>2])
<div class="eyebrow">The full treatment sequence</div>
<div class="section-title">Your <span class="gold">{{ $totalSessions }}-Session</span> Journey</div>
<div class="page-subtitle">Sessions 1 to {{ $reassessAfter }} form the first detailed block. Sessions {{ $reassessAfter + 1 }} to {{ $totalSessions }} remain planned but can be adjusted after reassessment.</div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;">
@foreach(array_chunk($timeline,2) as $row)
<tr>
@foreach($row as $session)
<td width="49%" class="card card-{{ $session['colour'] }} pad-lg timeline-card">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td width="13%" style="text-align:center;vertical-align:top;">
        <div class="session-number {{ $session['colour'] }}">{{ $session['number'] }}</div>
      </td>
      <td width="87%" style="vertical-align:top;padding-left:2mm;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td width="55%" style="vertical-align:middle;">
              <div class="phase-label {{ $session['colour'] }}">{{ $session['phase'] }}</div>
            </td>
            <td width="45%" style="text-align:right;vertical-align:middle;">
              <div class="timing-label">{{ $session['timing'] }}</div>
            </td>
          </tr>
        </table>
        <div class="treatment-title" style="margin-top:1.5mm;">{{ implode(' + ', $session['treatments']) }}</div>
        <div class="focus-copy" style="margin-top:1.8mm;">{{ $session['focus'] }}</div>
      </td>
    </tr>
  </table>
</td>
@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr>
@if(!$loop->last)<tr><td colspan="3" style="height:3mm;"></td></tr>@endif
@endforeach
</table>

<div class="card card-purple pad-lg" style="margin-top:4mm;height:40mm;"><table width="100%"><tr><td width="12%" style="text-align:center;"><img src="{{ $ui['icon_target'] }}" class="icon-lg" alt="Sequence"></td><td width="88%"><div class="panel-title purple">Why the plan alternates treatment types</div><div class="copy-lg" style="margin-top:1.3mm;">Q-Switch sessions focus on overall sun-related tone and cheek speckling. Microneedling sessions support the mixed cheek pattern and skin quality. Under-eye treatment is deliberately staged because the area is dry and has a structural shadow component.</div></td></tr></table></div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:4mm;"><tr>
<td width="32%" class="card card-gold pad-sm"><div class="mini-value-lg gold">First priority</div><div class="copy" style="margin-top:1mm;">Begin conservative pigment treatment while preserving the mapped safety exclusions.</div></td><td width="2%"></td>
<td width="32%" class="card card-purple pad-sm"><div class="mini-value-lg purple">Second priority</div><div class="copy" style="margin-top:1mm;">Review tolerance and visible response before expanding the plan.</div></td><td width="2%"></td>
<td width="32%" class="card card-cyan pad-sm"><div class="mini-value-lg cyan">Ongoing priority</div><div class="copy" style="margin-top:1mm;">Maintain sun protection and support the under-eye barrier throughout the course.</div></td>
</tr></table>
<pagebreak />

{{-- PAGE 3 --}}
@include('pdf.pigmentation_treatment.partials.header',['kicker'=>'Treatment targets','page'=>3])
<div class="eyebrow">What this course is designed to improve</div>
<div class="section-title">Your <span class="gold">Main Treatment</span> Targets</div>
<div class="page-subtitle">The report shows the patient-facing purpose and realistic outcome of each treatment target, without internal protocol codes or provider settings.</div>

@foreach($targets as $target)
<table width="100%" cellpadding="0" cellspacing="0" class="card card-{{ $target['colour'] }}" style="margin-top:3mm;height:50mm;"><tr>
<td width="14%" class="pad-sm" style="text-align:center;vertical-align:middle;"><img src="{{ $ui[$target['icon']] }}" class="icon-xl" alt="{{ $target['title'] }}"></td>
<td width="30%" class="pad"><div class="panel-title-sm {{ $target['colour'] }}">{{ $target['title'] }}</div><div class="mini-label" style="margin-top:2mm;">Where it is seen</div><div class="micro" style="margin-top:0.7mm;">{{ $target['location'] }}</div></td>
<td width="3%"></td>
<td width="25%" class="pad"><div class="mini-label {{ $target['colour'] }}">Planned approach</div><div class="copy" style="margin-top:1mm;">{{ $target['approach'] }}</div></td>
<td width="3%"></td>
<td width="25%" class="pad"><div class="mini-label {{ $target['colour'] }}">Realistic outcome</div><div class="copy" style="margin-top:1mm;">{{ $target['expected'] }}</div></td>
</tr></table>
@endforeach

<div class="card card-blue pad-lg" style="margin-top:4mm;height:34mm;"><table width="100%"><tr><td width="12%" style="text-align:center;"><img src="{{ $ui['icon_camera'] }}" class="icon-md" alt="Expectation"></td><td width="88%"><div class="panel-title-sm blue">What a good response looks like</div><div class="copy-lg" style="margin-top:1mm;">More even background tone, reduced contrast of cheek spots and partial improvement in the pigment component of under-eye darkness. Complete clearance is not promised, especially where structural shadow contributes.</div></td></tr></table></div>
<pagebreak />

{{-- PAGE 4 --}}
@include('pdf.pigmentation_treatment.partials.header',['kicker'=>'Current detailed block','page'=>4])
<div class="eyebrow">Detailed session breakdown</div>
<div class="section-title">Your <span class="gold">Current Detailed</span> Sessions</div>
<div class="page-subtitle">These are the current detailed sessions. Technical settings and provider execution steps remain clinician-facing and are not shown in this patient report.</div>

@foreach($firstSessions as $session)
<div class="card card-{{ $session['colour'] }} pad-lg" style="margin-top:3mm;height:79mm;">
<table width="100%"><tr>
<td width="13%" style="text-align:center;"><img src="{{ $ui[$session['icon']] }}" class="icon-xl" alt="Session {{ $session['number'] }}"><div class="session-number {{ $session['colour'] }}" style="margin-top:1.5mm;">{{ $session['number'] }}</div><div class="mini-label">{{ $session['timing'] }}</div></td>
<td width="87%"><div class="panel-title {{ $session['colour'] }}">{{ $session['headline'] }}</div><div class="copy-lg" style="margin-top:1.2mm;">{{ $session['what'] }}</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
<td width="31%" class="card-soft pad-sm"><div class="mini-label {{ $session['colour'] }}">Treatment focus</div><div class="copy" style="margin-top:0.8mm;">{{ $session['target'] }}</div></td><td width="2%"></td>
<td width="31%" class="card-soft pad-sm"><div class="mini-label coral">Areas not treated directly</div><div class="copy" style="margin-top:0.8mm;">{{ $session['avoid'] }}</div></td><td width="2%"></td>
<td width="34%" class="card-soft pad-sm"><div class="mini-label cyan">Immediate care</div><div class="micro" style="margin-top:0.8mm;">@foreach($session['aftercare'] as $item)• {{ $item }}@if(!$loop->last)<br>@endif @endforeach</div></td>
</tr></table>
</td>
</tr></table>
</div>
@endforeach

<div class="card card-gold pad-sm" style="margin-top:3mm;height:23mm;"><table width="100%"><tr><td width="10%" style="text-align:center;"><img src="{{ $ui['icon_sun'] }}" class="icon-sm" alt="Preparation"></td><td width="90%"><div class="mini-value-lg gold">Before every session</div><div class="micro" style="margin-top:0.5mm;">The skin is checked for new irritation, recent sun exposure and whether the protected areas remain unsuitable for direct treatment.</div></td></tr></table></div>
<pagebreak />

{{-- PAGE 5 --}}
@include('pdf.pigmentation_treatment.partials.header',['kicker'=>'Reassessment gate','page'=>5])
<div class="eyebrow">The plan is reviewed after session {{ $reassessAfter }}</div>
<div class="section-title">Your <span class="gold">Reassessment</span> Gate</div>
<div class="page-subtitle">The next block is not followed blindly. Five-mode imaging, skin comfort and treatment response determine whether the course continues unchanged or is adjusted.</div>

<div class="card card-purple pad-lg" style="margin-top:3mm;height:45mm;"><div class="panel-title purple">Five imaging modes are repeated</div><table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
@foreach($reviewImages as $image)
<td width="19%" class="card-soft pad-sm" style="text-align:center;"><img src="{{ $ui['icon_camera'] }}" class="icon-sm" alt="{{ $image }}"><div class="mini-value {{ $loop->index===0 ? 'gold' : ($loop->index===1 ? 'cyan' : ($loop->index===2 ? 'purple' : ($loop->index===3 ? 'coral' : 'blue'))) }}" style="margin-top:1mm;">{{ $image }}</div></td>@if(!$loop->last)<td width="1.25%"></td>@endif
@endforeach
</tr></table></div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
<td width="49%" class="card card-gold pad-lg" style="height:72mm;"><div class="panel-title-sm gold">What is reviewed</div><div style="margin-top:2mm;">@foreach($reviewItems as $item)<table class="check-table" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:2.4mm;"><tr><td width="10%"><img src="{{ $ui['icon_check'] }}" class="check-icon" alt="Check"></td><td width="90%" class="copy">{{ $item }}</td></tr></table>@endforeach</div></td>
<td width="2%"></td>
<td width="49%" class="card card-cyan pad-lg" style="height:72mm;"><div class="panel-title-sm cyan">How the plan may change</div><div style="margin-top:2mm;">@foreach($decisionRules as $rule)<table class="check-table" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:2.4mm;"><tr><td width="10%"><img src="{{ $ui['icon_renewal'] }}" class="check-icon" alt="Adjust"></td><td width="90%" class="copy">{{ $rule }}</td></tr></table>@endforeach</div></td>
</tr></table>

<div class="eyebrow" style="margin-top:4mm;">Possible decisions after reassessment</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;"><tr>
<td width="24%" class="card card-gold pad-sm"><div class="mini-value gold">Continue</div><div class="micro" style="margin-top:1mm;">Keep the planned sequence if response and tolerance are good.</div></td><td width="1.33%"></td>
<td width="24%" class="card card-purple pad-sm"><div class="mini-value purple">Adjust</div><div class="micro" style="margin-top:1mm;">Reduce coverage, change intervals or alter the sequence if needed.</div></td><td width="1.33%"></td>
<td width="24%" class="card card-cyan pad-sm"><div class="mini-value cyan">Defer Under-Eye</div><div class="micro" style="margin-top:1mm;">Keep under-eye procedures on hold while dryness or sensitivity remains.</div></td><td width="1.33%"></td>
<td width="24%" class="card card-coral pad-sm"><div class="mini-value coral">Maintain Exclusions</div><div class="micro" style="margin-top:1mm;">Continue avoiding the chin spot and any active red patch.</div></td>
</tr></table>
<pagebreak />

{{-- PAGE 6 --}}
@include('pdf.pigmentation_treatment.partials.header',['kicker'=>'Safety and realistic expectations','page'=>6])
<div class="eyebrow">Different dark areas need different care</div>
<div class="section-title">What Is <span class="gold">Not Treated</span> the Same Way</div>
<div class="page-subtitle">Some visible darkness is not a straightforward pigment target. These distinctions protect the skin and keep expectations realistic.</div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;">
@foreach(array_chunk($notTargets,2) as $row)
<tr>
@foreach($row as $item)
<td width="49%" class="card card-{{ $item['colour'] }} pad-lg" style="height:48mm;"><table width="100%"><tr><td width="18%"><img src="{{ $ui[$item['icon']] }}" class="icon-lg" alt="{{ $item['title'] }}"></td><td width="82%"><div class="panel-title-sm {{ $item['colour'] }}">{{ $item['title'] }}</div><div class="copy" style="margin-top:1.4mm;">{{ $item['copy'] }}</div></td></tr></table></td>
@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
@if(count($row)===1)<td width="2%"></td><td width="49%" class="card card-blue pad-lg" style="height:48mm;"><table width="100%"><tr><td width="18%"><img src="{{ $ui['icon_user'] }}" class="icon-lg" alt="Clinical check"></td><td width="82%"><div class="panel-title-sm blue">Suitability is checked each time</div><div class="copy" style="margin-top:1.4mm;">A planned treatment is performed only on skin that is calm, suitable and appropriately protected on the day.</div></td></tr></table></td>@endif
</tr>
@if(!$loop->last)<tr><td colspan="3" style="height:3mm;"></td></tr>@endif
@endforeach
</table>

<div class="card card-gold pad-lg" style="margin-top:4mm;height:43mm;"><table width="100%"><tr><td width="12%" style="text-align:center;"><img src="{{ $ui['icon_target'] }}" class="icon-lg" alt="Realistic outcome"></td><td width="88%"><div class="panel-title gold">Realistic result, not a promise of complete clearance</div><div class="copy-lg" style="margin-top:1.3mm;">The aim is a visible, gradual improvement in background tone and cheek-spot contrast, with partial improvement in under-eye pigment. Recurrence and residual darkness remain possible, especially with sun exposure, heat, dryness or fixed structural shadow.</div></td></tr></table></div>
<pagebreak />

{{-- PAGE 7 --}}
@include('pdf.pigmentation_treatment.partials.header',['kicker'=>'Homecare and progress','page'=>7])
<div class="eyebrow">What you do between sessions matters</div>
<div class="section-title">Your <span class="gold">Homecare</span> &amp; Progress Plan</div>
<div class="page-subtitle">The clinic course and the daily routine work together. The plan remains gentle while active irritation or under-eye dryness is present.</div>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:3mm;"><tr>
<td width="32%" class="card card-gold pad-lg" style="height:69mm;"><table width="100%"><tr><td width="22%"><img src="{{ $ui['icon_sun'] }}" class="icon-md" alt="Morning"></td><td width="78%"><div class="panel-title-sm gold">Morning</div></td></tr></table><div style="margin-top:2.5mm;">@foreach($morning as $item)<table class="check-table" width="100%" style="margin-bottom:2.2mm;"><tr><td width="11%"><img src="{{ $ui['icon_check'] }}" class="check-icon" alt="Check"></td><td width="89%" class="copy">{{ $item }}</td></tr></table>@endforeach</div></td>
<td width="2%"></td>
<td width="32%" class="card card-purple pad-lg" style="height:69mm;"><table width="100%"><tr><td width="22%"><img src="{{ $ui['icon_hydration'] }}" class="icon-md" alt="Evening"></td><td width="78%"><div class="panel-title-sm purple">Evening</div></td></tr></table><div style="margin-top:2.5mm;">@foreach($evening as $item)<table class="check-table" width="100%" style="margin-bottom:2.2mm;"><tr><td width="11%"><img src="{{ $ui['icon_check'] }}" class="check-icon" alt="Check"></td><td width="89%" class="copy">{{ $item }}</td></tr></table>@endforeach</div></td>
<td width="2%"></td>
<td width="32%" class="card card-cyan pad-lg" style="height:69mm;"><table width="100%"><tr><td width="22%"><img src="{{ $ui['icon_stable'] }}" class="icon-md" alt="Sun and heat"></td><td width="78%"><div class="panel-title-sm cyan">Sun &amp; Heat Control</div></td></tr></table><div style="margin-top:2.5mm;">@foreach($sunHeat as $item)<table class="check-table" width="100%" style="margin-bottom:2.2mm;"><tr><td width="11%"><img src="{{ $ui['icon_check'] }}" class="check-icon" alt="Check"></td><td width="89%" class="copy">{{ $item }}</td></tr></table>@endforeach</div></td>
</tr></table>

<div class="eyebrow" style="margin-top:4mm;">Progress milestones</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:1.5mm;"><tr>
@foreach($milestones as $milestone)
<td width="32%" class="card card-{{ $milestone['colour'] }} pad-lg" style="height:51mm;text-align:center;"><img src="{{ $ui['icon_calendar'] }}" class="icon-md" alt="{{ $milestone['title'] }}"><div class="mini-value-lg {{ $milestone['colour'] }}" style="margin-top:1.5mm;">{{ $milestone['title'] }}</div><div class="copy" style="margin-top:1.3mm;">{{ $milestone['copy'] }}</div></td>@if(!$loop->last)<td width="2%"></td>@endif
@endforeach
</tr></table>

<div class="card card-purple pad-lg" style="margin-top:4mm;height:39mm;"><table width="100%"><tr><td width="12%" style="text-align:center;"><img src="{{ $ui['icon_glow'] }}" class="icon-lg" alt="Next step"></td><td width="63%"><div class="copy-lg">This roadmap explains the planned course in patient-facing language. Final suitability, treatment coverage and any changes are confirmed in clinic before each session.</div></td><td width="25%" style="text-align:center;vertical-align:middle;"><div class="quote">Treat carefully.<br><span class="purple">Review honestly.</span><br><span class="coral">Progress steadily.</span></div></td></tr></table></div>

@endsection
