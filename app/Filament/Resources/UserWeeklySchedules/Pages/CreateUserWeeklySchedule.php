<?php

namespace App\Filament\Resources\UserWeeklySchedules\Pages;

use App\Filament\Resources\UserWeeklySchedules\UserWeeklyScheduleResource;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CreateUserWeeklySchedule extends CreateRecord
{
    protected static string $resource = UserWeeklyScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Appointment created 🎉')
            ->body('The new therapist schedule have been successfully created.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     dd($data);
    // }
}
