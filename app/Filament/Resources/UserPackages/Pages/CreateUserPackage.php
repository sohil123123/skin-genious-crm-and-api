<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Filament\Resources\UserPackages\UserPackageResource;
use App\Filament\Resources\UserPackages\Schemas\UserPackageForm;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUserPackage extends CreateRecord
{
    protected static string $resource = UserPackageResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Package Created 🎉')
            ->body('The package has been saved successfully.')
            ->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (!isset($data['clinic_id']) && !auth()->user()->hasRole('super_admin')) {
            $data['clinic_id'] = auth()->user()->clinic_id;
        }

        return $data;
    }

    protected function afterCreate(): void
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
