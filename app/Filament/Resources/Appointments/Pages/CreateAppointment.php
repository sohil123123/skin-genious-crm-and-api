<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;

// use Carbon\Carbon;

// use App\Models\Appointment;

class CreateAppointment extends CreateRecord
{
    use HasWizard;

    protected static string $resource = AppointmentResource::class;

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
            ->body('The client apointment have been successfully created.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Appointment Type')
                ->schema([
                    Section::make()
                        ->schema(AppointmentForm::getAppointmentTypeComponents())
                        // ->extraAttributes(['class' => 'flex justify-center']),
                        ->columns(),
                ])
                ->description('Select the appointment type.'),

            Step::make('Client, Therapist and Date')
                ->schema([
                    Section::make()->schema(AppointmentForm::getClientAndTherapistComponents())->columns(),
                ])
                ->description('Select the client, therapist and date.'),
        ];
    }

    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     dd('hi');
    //     $start = Carbon::parse($data['appointment_datetime']);
    //     $end = Carbon::parse($data['appointment_datetime'])->addMinutes(30);
    //     $result = Appointment::Overlapping($data['therapist_id'], $data['clinic_id'], $start, $end);
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
