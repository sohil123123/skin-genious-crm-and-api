@php use App\Services\UserReportPdfService as Report; @endphp

@isset($summary['packages'])
    @php $s = $summary['packages']; @endphp
    <div class="keep">
    <div class="sub-title">Packages</div>
    @include('pdf.user-report.partials.stats', ['stats' => [
        ['Packages', $s['count'], $s['active'] . ' active', 'amber'],
        ['Sessions used', $s['sessions_used'] . ' / ' . $s['sessions_total'], max(0, $s['sessions_total'] - $s['sessions_used']) . ' remaining', 'teal'],
        ['Package value', Report::money($s['value']), null, 'indigo'],
        ['Outstanding', Report::money($s['outstanding']), Report::money($s['paid']) . ' paid', $s['outstanding'] > 0 ? 'red' : 'green'],
    ]])
    </div>
@endisset

@isset($summary['assessments'])
    @php $s = $summary['assessments']; @endphp
    <div class="keep">
    <div class="sub-title">Assessments</div>
    @include('pdf.user-report.partials.stats', ['stats' => [
        ['Assessments', $s['count'], $s['completed'] . ' completed', 'indigo'],
        ['Types', count($s['by_type']) ?: '—', collect($s['by_type'])->map(fn($n, $t) => "$t: $n")->implode(', ') ?: null, 'slate'],
        ['Latest', $s['latest']?->format('d M Y') ?? '—', $s['latest']?->diffForHumans(), 'teal'],
    ]])
    </div>
@endisset

@isset($summary['sessions'])
    @php $s = $summary['sessions']; @endphp
    <div class="keep">
    <div class="sub-title">Sessions</div>
    @include('pdf.user-report.partials.stats', ['stats' => [
        ['Treatment sessions', $s['count'], $s['completed'] . ' completed', 'teal'],
        ['Pending / upcoming', $s['pending'], null, 'amber'],
        ['Treatment time', $s['minutes'] . ' min', round($s['minutes'] / 60, 1) . ' hours', 'indigo'],
        ['Package sessions used', $s['package_sessions_used'], 'from packages', 'green'],
    ]])
    </div>
@endisset

@isset($summary['invoices'])
    @php $s = $summary['invoices']; @endphp
    <div class="keep">
    <div class="sub-title">Billing</div>
    @include('pdf.user-report.partials.stats', ['stats' => [
        ['Invoices', $s['count'], null, 'green'],
        ['Total billed', Report::money($s['total']), 'GST ' . Report::money($s['gst']), 'indigo'],
        ['Paid', Report::money($s['paid']), null, 'teal'],
        ['Balance due', Report::money($s['due']), null, $s['due'] > 0 ? 'red' : 'green'],
    ]])
    </div>
@endisset
