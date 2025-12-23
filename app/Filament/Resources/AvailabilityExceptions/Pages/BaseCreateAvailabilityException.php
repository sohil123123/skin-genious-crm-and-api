<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\User;
use App\Models\Clinic;
use App\Filament\Resources\AvailabilityExceptions\AvailabilityExceptionResource;

abstract class BaseCreateAvailabilityException extends CreateRecord
{
    protected static string $resource = AvailabilityExceptionResource::class;

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

    public function getSubheading(): ?string
    {
        return 'Create therapist or clinic availability exception';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Availability Exception Added 🎉')
            ->body('The availability exception has been saved successfully.')
            ->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if(check_role('therapist') || check_role('clinic_manager')){
            $data['clinic_id'] = auth()->user()->clinic_id;
        }
        if(check_role('therapist')){
            $data['exceptionable_type'] = User::class;
            $data['exceptionable_id'] = auth()->id();
        }
        if(check_role('clinic_manager') && $data['exceptionable_type'] === Clinic::class){
            $data['exceptionable_id'] = auth()->user()->clinic_id;
        }

        if($data['exceptionable_type'] === User::class && in_array($data['type']->value, ['leave_full_day', 'leave_partial'])) {
            AvailabilityExceptionResource::validateLeaveLimit($data);
        }

        if ($data['exceptionable_type'] === Clinic::class) {
            $data['type'] = 'leave_full_day';
        }

        dd($data);
        return $data;
    }

    // protected function afterCreate(): void
    // {
    //     $record = $this->record;

    //     if($record->exceptionable instanceof User){

    //         $recipients = User::role('super_admin')->get();
    //         $clinic_manager = $record->exceptionable->clinic?->manager;
    //         if($clinic_manager)
    //             $recipients->push($clinic_manager);
    //         // dd($recipients);

    //         Notification::make()
    //             ->title('New Leave Request')
    //             ->icon('heroicon-o-rectangle-stack')
    //             ->iconColor('success')
    //             ->body("{$record->exceptionable?->name} requested {$record->leave_days} days {$record->leave_type->value} holiday")
    //             ->actions([
    //                 Action::make('view')
    //                     ->button()
    //                     // ->url(HolidayResource::getUrl('view', ['record' => $holiday]))
    //                     // ->url(fn () => $this->getResource()::getUrl('view', ['record' => $holiday]))
    //                     ->markAsRead()
    //             ])
    //             ->sendToDatabase($recipients);

    //     }
    // }
}
