<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Models\StockTransaction;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Invoice updated 🎉')
            ->body('The invoice details have been successfully updated.')
            ->success();
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
    
    protected function beforeSave(): void
    {
        // Restore stock via adjustment
        $this->record->items->each(function ($item) {
            if ($item->product_id) {
                StockTransaction::create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity, // Observer increments for 'adjustment_add'
                    'type' => 'adjustment_add',
                    'note' => "Invoice #{$this->record->id} Update (Restock)",
                ]);
            }
        });
    }

    protected function afterSave(): void
    {
        // Deduct new stock via sale
        $this->record->refresh();
        $this->record->items->each(function ($item) {
            if ($item->product_id && $item->product && $item->product->type !== 'service') {
                StockTransaction::create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity, // Observer decrements for 'sale'
                    'type' => 'sale',
                    'note' => "Invoice #{$this->record->id} Update (Consume)",
                ]);
            }
        });
    }
}
