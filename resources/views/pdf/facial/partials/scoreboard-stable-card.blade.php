@php
$name=(string)data_get($item,'parameter_name','Skin parameter');
$before=data_get($item,'before_treatment_score_or_label','-');
$after=data_get($item,'post_treatment_score_or_label','-');
$status=strtolower((string)data_get($item,'result','stable'));
$copy=(string)data_get($item,'score_explanation','');
if(strlen($copy)>92){$cut=substr($copy,0,89);$copy=rtrim(substr($cut,0,strrpos($cut,' ') ?: 89)).'...';}
$pillClass=$status==='declined'?'pill-red':($status==='improved'?'pill-green':'pill-gold');
$border=$status==='declined'?'#875057':($status==='improved'?'#2f817c':'#29495f');
@endphp
<table class="scoreboard-stable-card" cellpadding="0" cellspacing="0" style="border-color:{{ $border }};">
<tr style="height:18.5mm;">
<td width="57%" style="vertical-align:middle;">
<div class="scoreboard-stable-name">{{ $name }}</div>
<div class="scoreboard-stable-copy">{{ $copy }}</div>
</td>
<td width="27%" style="text-align:center;vertical-align:middle;">
<span class="mini-value-lg" style="font-size:10.5pt;">{{ $before }}</span>
<span style="display:inline-block;width:5mm;text-align:center;">@include('pdf.facial.partials.arrow')</span>
<span class="mini-value-lg" style="font-size:10.5pt;{{ $status==='improved'?'color:#63d8cf;':'' }}">{{ $after }}</span>
</td>
<td width="16%" style="text-align:center;vertical-align:middle;"><span class="pill {{ $pillClass }}">{{ $status }}</span></td>
</tr>
</table>
