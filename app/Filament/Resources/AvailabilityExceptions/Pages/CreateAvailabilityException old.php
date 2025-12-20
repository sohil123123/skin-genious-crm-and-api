<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use App\Filament\Resources\AvailabilityExceptions\AvailabilityExceptionResource;
use App\Filament\Resources\AvailabilityExceptions\Schemas\AvailabilityExceptionForm;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;

use App\Models\Clinic;
use App\Models\User;

class CreateAvailabilityException extends CreateRecord
{
    // use HasWizard;

    protected static string $resource = AvailabilityExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Create therapist availability';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Availability Exception added 🎉')
            ->body('Availability Exception have been successfully added.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // dd($data);
        if($data['exceptionable_type'] == User::class && in_array($data['type'], ['leave_full_day', 'leave_partial']))
            AvailabilityExceptionResource::validateLeaveLimit($data);

        if($data['exceptionable_type'] == Clinic::class)
            $data['clinic_id'] = $data['exceptionable_id'];

        return $data;
    }

    // protected function getSteps(): array
    // {
    //     return [
    //         Step::make('Applies To')
    //             ->schema([
    //                 Section::make()
    //                     ->schema(AvailabilityExceptionForm::getTypeComponents())
    //                     // ->extraAttributes(['class' => 'flex justify-center']),
    //                     ->columns(),
    //             ])
    //             ->description('Applies To therapist or clinic.'),

    //         Step::make('Availability Exception Details')
    //             ->schema([
    //                 Section::make()->schema(AvailabilityExceptionForm::getClinicAndTherapistComponents())->columns(),
    //             ])
    //             ->description('Select the clinic, therapist and date time etc...'),
    //     ];
    // }
}
