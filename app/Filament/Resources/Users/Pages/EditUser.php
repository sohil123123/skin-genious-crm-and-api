<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

use App\Models\Role;

use Filament\Notifications\Notification;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $oldRoles = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record;

        $role = $user->roles()->first();
        $data['role_id'] = $role?->id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldRoles = $this->record->roles->pluck('name')->toArray();
        
        if (!auth()->user()->hasRole(config('project.roles.super_admin', 'super_admin'))) {
            $data['clinic_id'] = auth()->user()->clinic_id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $newRoles = $this->record->roles()->pluck('name')->toArray();

        if(in_array('therapist', $newRoles) && !in_array('therapist', $this->oldRoles)) {
            $this->record->createDefaultLeaveEntitlementsIfTherapist();
        }

        if ($this->record && $this->data['role_id']) {
            $role = Role::find($this->data['role_id']);
            $this->record->syncRoles([$role->name]);
        }
    }

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
            ->title('Client updated 🎉')
            ->body('The client details have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
