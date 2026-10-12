{{-- $photos: list of ['path', 'name'], $title --}}
@if (! empty($photos))
    <div class="keep">
    <div class="sub-title">{{ $title }}</div>
    <table class="photos">
        @foreach (array_chunk($photos, 4) as $row)
            <tr>
                @foreach ($row as $photo)
                    <td>
                        <img src="{{ $photo['path'] }}" style="max-width: 40mm; max-height: 40mm;">
                        <div class="caption">{{ $photo['name'] }}</div>
                    </td>
                @endforeach
                @for ($i = count($row); $i < 4; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
    </div>
@endif
