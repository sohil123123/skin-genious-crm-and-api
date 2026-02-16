<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Models\StockTransaction;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Invoice Added 🎉')
            ->body('The invoice has been saved successfully.')
            ->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create stock transaction for created items (Observer handles stock decrement)
        $this->record->items->each(function ($item) {
            if ($item->product_id) {
                StockTransaction::create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity, // Observer decrements for 'sale'
                    'type' => 'sale',
                    'note' => "Invoice #{$this->record->id}",
                ]);
            }
        });
    }
}
