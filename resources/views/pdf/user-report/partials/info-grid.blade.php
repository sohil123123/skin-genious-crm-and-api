{{-- $rows: label => value (HTML-escaped here; pass HtmlString for markup). Empty values are skipped. --}}
<table class="info-grid">
    @foreach (array_chunk(array_filter($rows, fn($v) => filled($v)), 2, true) as $row)
        <tr>
            @foreach ($row as $label => $value)
                <td class="label">{{ $label }}</td>
                <td class="value">{{ $value }}</td>
            @endforeach
            @if (count($row) === 1)
                <td class="label"></td><td class="value"></td>
            @endif
        </tr>
    @endforeach
</table>
