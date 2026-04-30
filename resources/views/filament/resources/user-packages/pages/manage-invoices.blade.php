<x-filament-panels::page>
    @if($this->record->invoice)
        {{ $this->infolist }}
    @else
        <div class="fi-empty-state flex flex-col items-center justify-center p-6 mx-auto text-center bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 max-w-lg mt-6">
            <div class="fi-empty-state-icon-ctn mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-500/20">
                <x-heroicon-o-banknotes class="fi-empty-state-icon text-gray-500 dark:text-gray-400" style="width: 2rem; height: 2rem;" />
            </div>
            <h2 class="fi-empty-state-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                No Invoice Generated
            </h2>
            <p class="fi-empty-state-description mt-1 text-sm text-gray-500 dark:text-gray-400">
                This package does not have an invoice yet. Click 'Generate Invoice' to create one automatically.
            </p>
        </div>
    @endif
</x-filament-panels::page>
