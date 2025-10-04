<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CreateHoliday extends CreateRecord
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Holiday added 🎉')
            ->body('The holiday details have been successfully added.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
