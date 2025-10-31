<?php

namespace App\Filament\Resources\UserLeaveEntitlements\Pages;

use App\Filament\Resources\UserLeaveEntitlements\UserLeaveEntitlementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;


class EditUserLeaveEntitlement extends EditRecord
{
    protected static string $resource = UserLeaveEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->url(static::getResource()::getUrl('index'))->color('gray'),
            DeleteAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Leave Entitlement updated 🎉')
            ->body('The leave entitlement details have been successfully updated.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
