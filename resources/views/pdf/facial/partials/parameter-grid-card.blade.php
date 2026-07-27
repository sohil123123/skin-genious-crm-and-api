@php
$isNumeric=is_numeric(data_get($item,'score_or_label'));
$name=data_get($item,'parameter_name','Skin parameter');
$desc=(string)data_get($item,'client_description',data_get($item,'score_explanation',''));
$causes=collect(data_get($item,'possible_causes',[]))->filter()->take(2)->values();
@endphp
<table class="parameter-grid-card" cellpadding="0" cellspacing="0">
<tr style="height:101mm;">
<td width="40%" style="height:101mm;text-align:center;vertical-align:middle;">
@if($imageUrl)<img src="{{ $imageUrl }}" width="144" height="192" style="width:38mm;height:50.7mm;" alt="{{ $name }}">@else<div class="no-image">Image unavailable</div>@endif
<div style="margin-top:2mm;text-align:center;">
@if($isNumeric) @include('pdf.facial.partials.score',['item'=>$item]) @else <div class="mini-value-lg">{{ data_get($item,'score_or_label','N/A') }}</div><div class="score-caption">Profile classification</div> @endif
</div>
</td>
<td width="60%" style="height:101mm;vertical-align:middle;">
<div class="parameter-name">{{ $name }}</div>
<div class="parameter-copy">{{ $desc }}</div>
@if($causes->isNotEmpty())
<div class="parameter-causes"><b>Common contributors</b></div>
@foreach($causes as $cause)<div class="parameter-causes">- {{ $cause }}</div>@endforeach
@endif
</td>
</tr>
</table>
