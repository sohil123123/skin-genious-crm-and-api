<?php

namespace App\Filament\Resources\UserWeeklySchedules\Pages;

use App\Filament\Resources\UserWeeklySchedules\UserWeeklyScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Services\Scheduling\UserWeeklyScheduleService;

use App\Models\UserWeeklySchedule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Filament\Support\Exceptions\Halt;

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

    public function getSubheading(): ?string
    {
        return 'Update therapist availability and working hours';
    }


    public function getBreadcrumbs(): array
    {
        return [
            UserWeeklyScheduleResource::getUrl() => 'Weekly Schedules',
            '' => 'Edit',
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('User schedule updated 🎉')
            ->body('User schedule have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        $clinicId = $record->clinic_id;
        $userId   = $record->user_id;

        $schedules = UserWeeklySchedule::where([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
        ])
        ->orderBy('day_of_week')
        ->orderBy('start_time')
        ->get();

        $days = [];

        foreach ($schedules as $schedule) {
            $day = $schedule->day_of_week;

            $days[$day]['shifts'][] = [
                'id' => $schedule->id,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'is_active' => $schedule->is_active,
            ];
        }

        return [
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'days' => $days,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // try {
            app(UserWeeklyScheduleService::class)->saveOrUpdate($data);
        // } catch (ValidationException $e) {
        //     foreach ($e->errors() as $field => $messages) {
        //         dd($field);
        //         foreach ($messages as $message) {
        //             $this->addError('data.' . $field, $message);
        //         }
        //     }
        //     throw new Halt();
        // }

        return $record;
    }
}
