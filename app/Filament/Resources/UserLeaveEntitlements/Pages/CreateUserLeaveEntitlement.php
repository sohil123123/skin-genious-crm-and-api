<?php

namespace App\Filament\Resources\UserLeaveEntitlements\Pages;

use App\Filament\Resources\UserLeaveEntitlements\UserLeaveEntitlementResource;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
// use Filament\Notifications\Notification;

class CreateUserLeaveEntitlement extends CreateRecord
{
    protected static string $resource = UserLeaveEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    // protected function getCreatedNotification(): ?Notification
    // {
    //     return Notification::make()
    //         ->title('Leave Entitlement added 🎉')
    //         ->body('The leave entitlement details have been successfully added.')
    //         ->success();
    // }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
