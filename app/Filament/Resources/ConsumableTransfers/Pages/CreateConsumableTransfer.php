<?php

namespace App\Filament\Resources\ConsumableTransfers\Pages;

use App\Filament\Resources\ConsumableTransfers\ConsumableTransferResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CreateConsumableTransfer extends CreateRecord
{
    protected static string $resource = ConsumableTransferResource::class;

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
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Transfer Added 🎉')
            ->body('The transfer has been saved successfully.')
            ->success();
    }
}
