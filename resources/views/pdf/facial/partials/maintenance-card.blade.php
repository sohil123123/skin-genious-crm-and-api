@php
$accentColor=$tone==='teal'?'#63d8cf':'#e5c477';
$borderClass=$tone==='teal'?'card-teal':'card-gold';
@endphp
<table class="maintenance-card-v54 {{ $borderClass }}" cellpadding="0" cellspacing="0">
<tr style="height:72mm;">
<td style="height:72mm;vertical-align:middle;">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td width="24%" style="vertical-align:middle;text-align:center;">@if(!empty($uiAssets[$icon]))<img src="{{ $uiAssets[$icon] }}" width="66" height="66" style="width:17.5mm;height:17.5mm;" alt="{{ $title }}">@endif</td>
<td width="76%" style="vertical-align:middle;"><div class="metric-number" style="font-size:24pt;color:{{ $accentColor }};">{{ $number }}</div><div class="maintenance-title" style="font-size:13pt;">{{ $title }}</div></td>
</tr></table>
<div class="maintenance-copy" style="margin-top:3mm;font-size:9.4pt;line-height:1.45;">{{ $copy }}</div>
</td>
</tr>
</table>
