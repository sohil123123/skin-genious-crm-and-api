<table class="comparison-row" cellpadding="0" cellspacing="0">
<tr style="height:66mm;">
<td width="23%" style="height:66mm;text-align:center;vertical-align:middle;">
@if($beforeImage)<img src="{{ $beforeImage }}" width="155" height="206" style="width:41mm;height:54.5mm;" alt="Baseline {{ $title }}">@else<div class="no-image">No baseline</div>@endif
<div class="mini-label" style="margin-top:0.8mm;">Baseline</div>
</td>
<td width="6%" style="text-align:center;vertical-align:middle;">@include('pdf.facial.partials.arrow')</td>
<td width="22%" style="height:66mm;text-align:center;vertical-align:middle;">
@if($afterImage)<img src="{{ $afterImage }}" width="155" height="206" style="width:41mm;height:54.5mm;" alt="Current {{ $title }}">@else<div class="no-image">No current</div>@endif
<div class="mini-label" style="margin-top:0.8mm;">Current</div>
</td>
<td width="48%" style="height:66mm;padding-left:4mm;vertical-align:middle;">
<div class="comparison-title">{{ $title }}</div>
<div class="comparison-copy">{{ $description }}</div>
@if(!empty($observation))<div class="section-copy" style="margin-top:2.5mm;">{{ $observation }}</div>@endif
<div style="margin-top:3mm;"><span class="pill pill-blue">Baseline</span> <span style="display:inline-block;width:6mm;text-align:center;">@include('pdf.facial.partials.arrow')</span> <span class="pill pill-green">Current</span></div>
</td>
</tr>
</table>
