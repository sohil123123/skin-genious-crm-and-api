<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\URL;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(function () {
                    $user = auth()->user();

                    return URL::temporarySignedRoute(
                        'vue.sso',
                        now()->addMinutes(5), // ⏱ expires
                        [
                            'user_id'    => $user->id,
                            'clinic_id'  => $user->clinic_id,
                            'role'       => $user->getRoleNames()->first(),
                        ]
                    );

                }, shouldOpenInNewTab: true)
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
