<x-filament-panels::page>
    <form wire:submit="sendBroadcast">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button
                type="submit"
                icon="heroicon-o-paper-airplane"
                size="lg"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="sendBroadcast">Send Broadcast</span>
                <span wire:loading wire:target="sendBroadcast">Sending...</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
