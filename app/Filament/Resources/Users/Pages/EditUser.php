<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

use Filament\Notifications\Notification;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $oldRoles = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldRoles = $this->record->roles->pluck('name')->toArray();
        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $newRoles = $this->record->roles()->pluck('name')->toArray();

        if(in_array('therapist', $newRoles) && !in_array('therapist', $this->oldRoles)) {
            $this->record->createDefaultLeaveEntitlementsIfTherapist();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->url(static::getResource()::getUrl('index'))->color('gray'),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('User updated 🎉')
            ->body('The user details have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
