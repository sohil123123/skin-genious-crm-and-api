{{-- $stats: list of [label, value, note, color] --}}
<table class="stats">
    <tr>
        @foreach ($stats as $i => [$label, $value, $note, $color])
            @if ($i > 0)<td class="gap"></td>@endif
            <td class="stat stat-{{ $color }}" style="width: {{ floor(100 / count($stats)) - 1 }}%;">
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value">{{ $value }}</div>
                @if ($note)<div class="stat-note">{{ $note }}</div>@endif
            </td>
        @endforeach
    </tr>
</table>
