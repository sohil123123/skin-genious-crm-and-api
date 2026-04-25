<?php

namespace App\Filament\Resources\ConsumableTransfers\Pages;

use App\Filament\Resources\ConsumableTransfers\ConsumableTransferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class EditConsumableTransfer extends EditRecord
{
    protected static string $resource = ConsumableTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction::make(),
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Transfer updated 🎉')
            ->body('The transfer details have been successfully updated.')
            ->success();
    }
}
