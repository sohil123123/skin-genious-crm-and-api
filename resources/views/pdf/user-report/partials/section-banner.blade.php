{{-- $key, $subtitle --}}
@if (count($sections) > 1)
    <pagebreak />
@endif
<div class="section-banner banner-{{ $key }}">
    <div class="title">{{ \App\Services\UserReportPdfService::SECTIONS[$key] }}</div>
    @if (! empty($subtitle))<div class="subtitle">{{ $subtitle }}</div>@endif
</div>
