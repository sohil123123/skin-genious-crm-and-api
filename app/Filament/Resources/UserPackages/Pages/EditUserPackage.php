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

    protected function afterSave(): void
    {
        $record = $this->record;

        // Decode any JSON-encoded snapshots in items
        foreach ($record->items as $item) {
            if (is_string($item->service_snapshot)) {
                $item->update([
                    'service_snapshot' => json_decode($item->service_snapshot, true),
                ]);
            }
        }

        // Recalculate package totals from items
        $record->recalculateFromItems();
    }
}
