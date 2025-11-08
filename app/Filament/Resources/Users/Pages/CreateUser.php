<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

// use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Models\Role;

use App\Filament\Resources\Users\Schemas\UserForm;

class CreateUser extends CreateRecord
{
    // use HasWizard;

    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record;

        if ($user->hasRole('therapist')) {
            $user->createDefaultLeaveEntitlementsIfTherapist();
        }

        if ($this->record && $this->data['role_id']) {
            $role = Role::find($this->data['role_id']);
            $this->record->syncRoles([$role->name]);
        }
    }

    // protected function getSteps(): array
    // {
    //     return [
    //         Step::make('Personal Information')
    //             ->schema([
    //                 Section::make()
    //                     ->schema(UserForm::getPersonalInformationComponents())
    //                     ->columns(),
    //             ]),

    //         Step::make('Contact Details')
    //             ->schema([
    //                 Section::make()
    //                     ->schema(UserForm::getContactDetailsComponents())
    //                     ->columns(),
    //             ]),
    //     ];
    // }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    // protected function getFormActions(): array
    // {
    //     return []; // 👈 removes default Create / Create & create another / Cancel
    // }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('User added 🎉')
            ->body('The user details have been successfully added.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
