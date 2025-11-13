<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use Carbon\Carbon;

use App\Models\Appointment;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->outlined()->url(static::getResource()::getUrl('index'))->color('gray'),
            DeleteAction::make()->icon('heroicon-o-trash'),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Appointment updated 🎉')
            ->body('The client apointment have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // protected function mutateFormDataBeforeSave(array $data): array
    // {
    //     $start = Carbon::parse($data['appointment_datetime']);
    //     $end = Carbon::parse($data['appointment_datetime'])->addMinutes(30);
    //     $result = Appointment::overlapping($data['therapist_id'], $data['clinic_id'], $start, $end, $this->record->id);
    //     if ($result->exists()) {
    //             Notification::make()
    //                 ->title('Duplicate slot')
    //                 ->body("This time slot is already booked for the selected therapist and clinic")
    //                 ->danger()
    //                 // ->persistent()
    //                 ->send();
    //         $this->halt();
    //     }
    //     return $data;
    // }
}
