<?php

namespace App\Filament\Resources\UserWeeklySchedules\Pages;

use App\Filament\Resources\UserWeeklySchedules\UserWeeklyScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

class EditUserWeeklySchedule extends EditRecord
{
    protected static string $resource = UserWeeklyScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->outlined()->url(static::getResource()::getUrl('index'))->color('gray'),
            DeleteAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Appointment updated 🎉')
            ->body('The therapist schedule have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
