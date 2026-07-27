@php
$before=data_get($item,'before_treatment_score_or_label','-');
$after=data_get($item,'post_treatment_score_or_label','-');
$name=data_get($item,'parameter_name','Improvement');
$copy=(string)data_get($item,'score_explanation','');
$limit=$compact?105:165;
if(strlen($copy)>$limit){$cut=substr($copy,0,$limit-3);$copy=rtrim(substr($cut,0,strrpos($cut,' ') ?: ($limit-3))).'...';}
$imgW=$compact?88:119; $imgH=$compact?117:159; $mmW=$compact?'23.3mm':'31.5mm'; $mmH=$compact?'31mm':'42mm';
@endphp
<table class="{{ $compact?'improvement-card-compact':'improvement-card-large' }}" cellpadding="0" cellspacing="0">
<tr style="height:{{ $compact ? '58mm' : '99mm' }};">
<td width="60%" style="height:{{ $compact ? '58mm' : '99mm' }};padding:3mm;text-align:center;vertical-align:middle;">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="43%" style="text-align:center;">@if($beforeImage)<img src="{{ $beforeImage }}" width="{{ $imgW }}" height="{{ $imgH }}" style="width:{{ $mmW }};height:{{ $mmH }};" alt="Before {{ $name }}">@endif<div class="mini-label">Before</div></td>
<td width="14%" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.arrow')</td>
<td width="43%" style="text-align:center;">@if($afterImage)<img src="{{ $afterImage }}" width="{{ $imgW }}" height="{{ $imgH }}" style="width:{{ $mmW }};height:{{ $mmH }};" alt="After {{ $name }}">@endif<div class="mini-label">After</div></td>
</tr></table>
</td>
<td width="40%" style="height:{{ $compact ? '58mm' : '99mm' }};padding:3.6mm;vertical-align:middle;">
<div class="improve-title">{{ $name }}</div>
<div style="margin-top:2mm;"><span class="change-number">{{ $before }}</span><span style="display:inline-block;width:9mm;text-align:center;">@include('pdf.facial.partials.arrow')</span><span class="change-number" style="color:#63d8cf;">{{ $after }}</span></div>
<div class="improve-copy">{{ $copy }}</div>
<div style="margin-top:2mm;"><span class="pill pill-green">{{ strtoupper((string)data_get($item,'response_strength','Improved')) }}</span></div>
</td>
</tr>
</table>
