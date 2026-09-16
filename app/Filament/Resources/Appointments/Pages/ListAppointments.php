<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentsResource;
use App\Jobs\SendTodayAppointmentsWhatsAppJob;
use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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

            // Sits beside New appointment rather than above the table. It acts
            // on today's appointments as a whole, not on the rows in view, so
            // the table toolbar — where every other control filters, sorts or
            // selects rows — was the wrong place to read it.
            Action::make('send_today_whatsapp_reminders')
                ->label("Send Today's WhatsApp Reminders")
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->modalHeading("Send Today's Appointment WhatsApp Reminders")
                ->modalDescription("This will dispatch queued WhatsApp template messages for all clients with appointments scheduled for today.")
                ->form([
                    Placeholder::make('today_appointments_count')
                        ->label("Today's Appointments")
                        ->content(function () {
                            $count = Appointment::whereDate('start_datetime', Carbon::today())
                                ->where('status', '!=', 'cancelled')
                                ->count();
                            return "{$count} active appointment(s) scheduled for today.";
                        }),
                    Toggle::make('force')
                        ->label('Force send even if already sent today')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $force = (bool) ($data['force'] ?? false);

                    SendTodayAppointmentsWhatsAppJob::dispatch(
                        Carbon::today()->format('Y-m-d'),
                        null,
                        null,
                        $force
                    );

                    Notification::make()
                        ->title("WhatsApp Reminders Queued 🚀")
                        ->body("The queue job for today's appointment WhatsApp reminders has been dispatched.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
