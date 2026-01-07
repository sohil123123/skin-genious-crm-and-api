<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentsResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

use App\Services\Availability\AvailabilityService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class EditAppointments extends EditRecord
{
    protected static string $resource = AppointmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Appointment updated 🎉')
            ->body('The appointment details have been successfully updated.')
            ->success();
    }

    // protected function getRedirectUrl(): string
    // {
    //     return $this->getResource()::getUrl('index');
    // }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record?->start_datetime) {
            $start = $record->start_datetime instanceof Carbon
                ? $record->start_datetime
                : Carbon::parse($record->start_datetime);

            $data['start_date'] = $start->format('Y-m-d');
            $data['start_time'] = $start->format('H:i');
        }

        if ($record?->end_datetime) {
            $end = $record->end_datetime instanceof Carbon
                ? $record->end_datetime
                : Carbon::parse($record->end_datetime);

            $data['end_time'] = $end->format('H:i');
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_emergency'] = false;
        $data['emergency_reason'] = NULL;

        try {
            // 1️⃣ Build Carbon start & end from form fields
            $start = $data['start_date'] . ' ' . $data['start_time'];
            $end = $data['start_date'] . ' ' . $data['end_time'];
            $status = $data['status'] ?? null;

            // 2️⃣ Call availability service
            $warning = app(AvailabilityService::class)->assertBookable(
                clinicId: $data['clinic_id'],
                therapistId: $data['therapist_id'],
                start: Carbon::parse($start),
                end: Carbon::parse($end),
                status: $status,
                ignoreAppointmentId: $this->getRecord()->id // IMPORTANT for edit
            );
            
            // 3️⃣ Optional: show warning (super admin override)
            if (!empty($warning)) {
                $emergencyData = collect($warning)->whereNotNull('emergency')->pluck('emergency')->values()->all();
                if ($status == 'confirmed' && !empty($emergencyData)) {
                    $data['is_emergency'] = true;
                    $data['emergency_reason'] = $emergencyData;

                    activity()
                        ->useLog('appointment')
                        ->performedOn($this->getRecord())
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'emergency_reason' => $emergencyData,
                        ])
                        ->event('emergency_override')
                        ->log('Emergency Override');
                }

                $messages = collect($warning)->pluck('message')->filter()->implode(', ');
                Notification::make()
                    ->title($messages ?? 'Availability warning')
                    ->warning()
                    ->send();
            }

        } catch (ValidationException $e) {

            $error = $e->errors()['availability'][1] ?? null;

            Notification::make()
                ->title($error ?? 'Appointment not available')
                ->danger()
                ->send();

            // ⛔ STOP SAVE
            $this->halt();
        }

        // 4️⃣ Combine datetime fields for DB
        $data['start_datetime'] = $start;
        $data['end_datetime']   = $end;

        // 5️⃣ Cleanup virtual fields
        unset($data['start_date'], $data['start_time'], $data['end_time']);

        return $data;
    }
}
