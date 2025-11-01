<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Resources\Pages\CreateRecord;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Models\User;

class CreateHoliday extends CreateRecord
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Holiday added 🎉')
            ->body('The holiday details have been successfully added.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        HolidayResource::validateLeaveLimit($data);
        return $data;
    }

    protected function afterCreate(): void
    {
        $holiday = $this->record;

        // Get all admin and clinic manager users
        // $recipients = User::whereHas('roles', function ($q) {
        //     $q->whereIn('name', ['super_admin', 'clinic_manager']);
        // })->get();

        $recipients = User::role('super_admin')->get();
        $clinic_manager = $holiday->user->clinic?->manager;
        $recipients->push($clinic_manager);

        Notification::make()
            ->title('New Leave Request')
            ->icon('heroicon-o-rectangle-stack')
            ->iconColor('success')
            ->body("{$holiday->user?->name} requested {$holiday->days} days {$holiday->type->value} holiday")
            ->actions([
                Action::make('view')
                    ->button()
                    // ->url(HolidayResource::getUrl('view', ['record' => $holiday]))
                    // ->url(fn () => $this->getResource()::getUrl('view', ['record' => $holiday]))
                    ->markAsRead()
            ])
            ->sendToDatabase($recipients);
    }
}
