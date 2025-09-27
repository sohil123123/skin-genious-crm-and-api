<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

// use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Filament\Resources\Users\Schemas\UserForm;

class CreateUser extends CreateRecord
{
    // use HasWizard;

    protected static string $resource = UserResource::class;

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
            Action::make('back')->label('Back to List')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    // protected function getFormActions(): array
    // {
    //     return []; // 👈 removes default Create / Create & create another / Cancel
    // }

    protected function afterCreate(): void
    {
        /** @var Order $order */
        $user = $this->record;

        Notification::make()
            ->title('New order')
            ->icon('heroicon-o-shopping-bag')
            ->body("**New customer ({$user->name}) has been added.**")
            ->actions([
                Action::make('View')->url(UserResource::getUrl('edit', ['record' => $user])),
            ])
            ->sendToDatabase($user);
    }
}
