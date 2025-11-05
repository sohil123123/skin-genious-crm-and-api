@php
    $items = $getState();
@endphp

@if (blank($items))
    <p class="text-gray-500 italic">No preparation steps listed.</p>
@else
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-700 p-4 space-y-3">
    @foreach ($items as $item)
        <p class="text-sm leading-relaxed text-gray-800 dark:text-gray-200">
            <span class="text-success font-semibold mr-2 text-bold" >✓</span>
            {{ $item }}
        </p>
        <br>
    @endforeach
</div>
@endif
