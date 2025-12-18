<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use App\Filament\Resources\AvailabilityExceptions\AvailabilityExceptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Models\Clinic;
use App\Models\User;

class EditAvailabilityException extends EditRecord
{
    protected static string $resource = AvailabilityExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
            DeleteAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Availability Exception updated 🎉')
            ->body('Availability Exception details have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if($data['exceptionable_type'] == User::class && in_array($data['type'], ['leave_full_day', 'leave_partial']))
            AvailabilityExceptionResource::validateLeaveLimit($data);

        if($data['exceptionable_type'] == Clinic::class)
            $data['clinic_id'] = $data['exceptionable_id'];

        return $data;
    }
}
