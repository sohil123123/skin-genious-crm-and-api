<x-filament::page>
    <div class="space-y-6">
        {{ $this->schema }}
    </div>
    @if ($activeTab === 2)
        <div class="mt-6">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\Users\UserResource::getUrl('holidays', ['record' => $user]) }}">
                Manage Holidays
            </x-filament::button>
        </div>
    @endif
</x-filament::page>
