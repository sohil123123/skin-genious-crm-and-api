@php
$name=data_get($item,'parameter_name','Skin parameter');
$desc=(string)data_get($item,'client_description',data_get($item,'score_explanation',''));
if(strlen($desc)>125){$cut=substr($desc,0,122);$desc=rtrim(substr($cut,0,strrpos($cut,' ') ?: 122)).'...';}
@endphp
<table class="dashboard-card" cellpadding="0" cellspacing="0">
<tr>
<td width="70%"><div class="dashboard-name">{{ $name }}</div><div class="dashboard-copy">{{ $desc }}</div></td>
<td width="30%" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.score',['item'=>$item])</td>
</tr>
</table>
