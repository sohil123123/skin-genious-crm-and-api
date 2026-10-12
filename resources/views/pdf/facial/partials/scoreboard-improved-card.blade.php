@php
$name=(string)data_get($item,'parameter_name','Improved parameter');
$before=data_get($item,'before_treatment_score_or_label','-');
$after=data_get($item,'post_treatment_score_or_label','-');
$copy=(string)data_get($item,'score_explanation','');
if(strlen($copy)>104){$cut=substr($copy,0,101);$copy=rtrim(substr($cut,0,strrpos($cut,' ') ?: 101)).'...';}
$iconName=$iconFor($name);
@endphp
<table class="scoreboard-improved-card" cellpadding="0" cellspacing="0">
<tr style="height:33mm;">
<td width="15%" style="text-align:center;vertical-align:middle;">
@if(!empty($uiAssets[$iconName]))<img src="{{ $uiAssets[$iconName] }}" width="38" height="38" style="width:10mm;height:10mm;" alt="{{ $name }}">@endif
</td>
<td width="85%" style="vertical-align:middle;">
<div class="scoreboard-improved-name">{{ $name }}</div>
<div class="scoreboard-improved-copy">{{ $copy }}</div>
<table class="scoreboard-comparison" width="100%" cellpadding="0" cellspacing="0" style="margin-top:2mm;"><tr>
<td width="40%" style="text-align:center;"><div class="mini-label">Before /100</div><div class="change-number" style="font-size:14pt;">{{ $before }}</div></td>
<td width="20%" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.arrow')</td>
<td width="40%" style="text-align:center;"><div class="mini-label">After /100</div><div class="change-number" style="font-size:14pt;color:#63d8cf;">{{ $after }}</div></td>
</tr></table>
<div style="margin-top:1mm;"><span class="pill pill-green">Improved</span></div>
</td>
</tr>
</table>
