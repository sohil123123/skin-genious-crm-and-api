<?php

namespace App\Filament\Resources\UserWeeklySchedules\Pages;

use App\Filament\Resources\UserWeeklySchedules\UserWeeklyScheduleResource;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Services\Scheduling\UserWeeklyScheduleService;

use App\Models\UserWeeklySchedule;

use Illuminate\Database\Eloquent\Model;

class CreateUserWeeklySchedule extends CreateRecord
{
    protected static string $resource = UserWeeklyScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Create therapist availability and working hours';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('User schedule created 🎉')
            ->body('User schedule have been successfully created.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     // dd($data);
    //     app(UserWeeklyScheduleService::class)->saveOrUpdate($data);

    //     return [];
    // }

    protected function handleRecordCreation(array $data): Model
    {
        app(UserWeeklyScheduleService::class)->saveOrUpdate($data);

        /**
         * VERY IMPORTANT:
         * Return a dummy model instance WITHOUT saving
         */
        return new UserWeeklySchedule();
    }
}
