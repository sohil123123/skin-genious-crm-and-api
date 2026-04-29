<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Filament\Resources\UserPackages\UserPackageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUserPackage extends EditRecord
{
    protected static string $resource = UserPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
            DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Package Updated ✅')
            ->body('The package has been updated successfully.')
            ->success();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Decode snapshot string to array if needed
        if (isset($data['service_snapshot']) && is_string($data['service_snapshot'])) {
            $data['service_snapshot'] = json_decode($data['service_snapshot'], true);
        }

        return $data;
    }
}
