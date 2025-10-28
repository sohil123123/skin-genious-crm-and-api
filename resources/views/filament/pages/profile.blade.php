<x-filament::page>
    <div class="space-y-6">
        {{ $this->schema }}
    </div>
    {{-- ✅ Manual Save button rendering (appears only in Edit tab) --}}
    @if ($activeTab == 2)
        <div class="mt-6 flex justify-end">
            <x-filament::button color="primary" wire:click="submit">
                Save Changes
            </x-filament::button>
        </div>
    @endif
</x-filament::page>
