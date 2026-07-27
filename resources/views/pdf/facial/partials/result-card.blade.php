@php
$name=data_get($item,'parameter_name','Skin parameter');
$before=data_get($item,'before_treatment_score_or_label','-');
$after=data_get($item,'post_treatment_score_or_label','-');
$status=strtolower((string)data_get($item,'result','stable'));
$pillClass=$status==='improved'?'pill-green':($status==='declined'?'pill-red':'pill-gold');
$copy=(string)data_get($item,'score_explanation','');
if(strlen($copy)>116){$cut=substr($copy,0,113);$copy=rtrim(substr($cut,0,strrpos($cut,' ') ?: 113)).'...';}
@endphp
<table class="result-card" cellpadding="0" cellspacing="0" style="height:20mm;">
<tr style="height:20mm;">
<td width="58%"><div class="result-name">{{ $name }}</div><div class="result-copy">{{ $copy }}</div></td>
<td width="12%" style="text-align:center;vertical-align:middle;"><div class="mini-label">Before</div><div class="mini-value-lg">{{ $before }}</div></td>
<td width="8%" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.arrow')</td>
<td width="12%" style="text-align:center;vertical-align:middle;"><div class="mini-label">After</div><div class="mini-value-lg">{{ $after }}</div></td>
<td width="10%" style="text-align:center;vertical-align:middle;"><span class="pill {{ $pillClass }}">{{ $status }}</span></td>
</tr>
</table>
