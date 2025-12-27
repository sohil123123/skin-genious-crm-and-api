<?php

namespace App\Filament\Widgets;

// use Filament\Widgets\Widget;
use App\Models\Appointment;  // Adjust to your model
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Illuminate\Database\Eloquent\Model;

use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

use Saade\FilamentFullCalendar\Actions\CreateAction;
// use Saade\FilamentFullCalendar\Actions\EditAction;
// use Saade\FilamentFullCalendar\Actions\DeleteAction;

// use Filament\Widgets\Concerns\InteractsWithPageFilters;
// use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class AppointmentsCalendar extends FullCalendarWidget
{
    // use InteractsWithPageFilters, HasWidgetShield;
    // protected string $view = 'filament.widgets.appointments-calendar';
    // Optional: Link events to your Filament resource for editing
    public string | Model | null $model = Appointment::class;

    // protected ?string $heading = 'Appointments Calendar';

    // Optional: Visual cue for locked (confirmed) events
    public function eventDidMount(): string
    {
        return <<<JS
            function(info) {
                info.el.setAttribute("x-tooltip", "tooltip");
                info.el.setAttribute("x-data", "{ tooltip: '"+info.event.title+"' }");
                if (info.event.extendedProps.isLocked) {
                    // info.el.innerHTML = 'Completed ' + info.el.innerHTML;  // Add lock emoji to title
                    info.el.style.opacity = '0.7';  // Gray out visually
                    info.el.style.cursor = 'not-allowed';  // No-drag cursor
                }
            }
        JS;
    }

    protected function headerActions(): array
    {
        return [
            CreateAction::make()->visible(false),
        ];
    }

    protected function modalActions(): array
    {
        return [
            // 🔹 Start Assessment
            Action::make('new_assessment')
                    ->label('Create Assessment')
                    ->visible(fn ($record) => can_create_assessment($record))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->button()
                    ->action(function ($record) {
                        $assessmentUrl = new_assessment($record->client, $record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),

            // 🔹 Start Treatment Session
            Action::make('start_session')
                ->label('Start Session')
                ->visible(fn ($record) => can_start_session($record))
                ->icon('heroicon-o-plus')
                ->color('warning')
                ->button()
                ->action(function ($record) {
                    $startSessionUrl = start_session($record);
                    return redirect($startSessionUrl);
                })
                ->requiresConfirmation(),

            // Existing actions (optional)
            // EditAction::make()->visible(false),
            // DeleteAction::make()->visible(false),
        ];
    }

    public function getFormSchema(): array
    {
        return [
                Section::make('Basic Information')
                    ->description('Core details about the appointment.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('type'),
                        TextEntry::make('clinic.name')->label('Clinic Name'),
                        TextEntry::make('client.first_name')->label('Client Name'),
                        TextEntry::make('therapist.first_name')->label('therapist Name'),

                        TextEntry::make('start_datetime')->dateTime('d M Y, h:i A')->badge()->color('warning'),
                        TextEntry::make('end_datetime')->dateTime('d M Y, h:i A')->badge()->color('warning'),
                        TextEntry::make('status')->placeholder('N/A'),
                        IconEntry::make('created_by.first_name')->label('Created By')->placeholder('N/A'),
                        IconEntry::make('updated_by.first_name')->label('Updated By')->placeholder('N/A'),
                        TextEntry::make('notes')->placeholder('N/A')->columnSpanFull(),

                    ])
                    ->columns(3),

                Section::make('Treatment Session Details')
                    ->description('assessment id and treatment session title.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        TextEntry::make('assessment.id')->label('Assessment Id')->placeholder('N/A'),
                        TextEntry::make('treatmentSession.title')->label('Treatment Session Title')->placeholder('N/A'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->type->value == 'treatment')
                    ->collapsible(),

                Section::make('Record Information')
                    ->description('Timestamps for creation, update, deletion and billed.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')->label('Created At')->dateTime('d M Y, h:i A'),
                        TextEntry::make('updated_at')->label('Updated At')->dateTime('d M Y, h:i A'),
                        TextEntry::make('deleted_at')->label('Deleted At')->dateTime('d M Y, h:i A')->placeholder('Not deleted'),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->collapsible(),
        ];
    }

    // Fetch events based on the visible date range (e.g., today or selected period)
    public function fetchEvents(array $fetchInfo): array
    {
        // \Log::info('Fetching appointments from ' . $fetchInfo['start'] . ' to ' . $fetchInfo['end']);
        // Filter for today's appointments (or the fetched range for broader views)
        $start = $fetchInfo['start'];
        $end = $fetchInfo['end'];

        return Appointment::query()
            ->whereDate('start_datetime', '>=', $start)
            ->whereDate('end_datetime', '<=', $end)
            ->get()
            ->map(function (Appointment $appointment) {
                $statusColor = match ($appointment->status->value) {
                    'confirmed' => '#10B981',  // Green
                    'pending' => '#F59E0B',    // Yellow
                    'completed' => '#3B82F6',  // Blue
                    'cancelled' => '#EF4444',  // Red
                    default => '#3B82F6',
                };
                $isEditable = $appointment->status->value !== 'completed';  // Key: Disable for completed
                return EventData::make()
                    ->id($appointment->id)
                    // ->title($appointment->client->first_name ?? 'Appointment')  // Customize title (e.g., client name)
                    // ->title("{$appointment->client->name} w/ {$appointment->therapist->name}.")
                    ->title("({$appointment->status->getLabel()}) {$appointment->client->name} w/ {$appointment->therapist->name}.")
                    ->start($appointment->start_datetime)
                    ->end($appointment->end_datetime)  // If no end time
                    // ->url(
                    //     url: AppointmentResource::getUrl('edit', ['record' => $appointment]),
                    //     shouldOpenUrlInNewTab: true  // Open edit in new tab
                    // )
                    ->backgroundColor($statusColor)
                    ->borderColor($statusColor === '#EF4444' ? '#DC2626' : $statusColor)
                    // ->textColor('white')
                    ->extraProperties([  // Fixed: Inject 'editable' here
                        'editable' => false,
                        'display' => 'block',  // Optional: Full-width events; use 'auto' for compact
                        'status' => $appointment->status->value,
                        'client_id' => $appointment->client_id,
                        'isLocked' => !$isEditable,  // For visual cues in hooks
                    ])
                    ->extendedProps([  // Custom data for hooks
                        'status' => $appointment->status->value,
                        'client_id' => $appointment->user_id,
                    ]);
            })
            ->toArray();
    }

    public function config(): array
    {
        return [
            // 'selectable' => false,
            // 'selectMirror' => false,

            'initialDate' => now()->format('Y-m-d'),
            'initialView' => 'timeGridDay',
            'firstDay' => 1,
            'allDaySlot' => false,
            // 'headerToolbar' => [
            //     'left' => '',
            //     'center' => 'title',
            //     'right' => 'today,dayGridWeek,timeGridDay, prev,next',
            // ],
            'headerToolbar' => [
                'left' => 'prev,next,today',
                'center' => 'title',
                'right' => 'dayGridMonth,dayGridWeek,timeGridDay',
            ],

            // ✅ HARD constraint
            // 'selectConstraint' => 'businessHours',

            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '20:00:00',
            'slotDuration' => '00:15:00',

            // 'slotLabelFormat' => [
            //     'hour' => '2-digit',
            //     'minute' => '2-digit',
            //     'omitZeroMinute' => false,
            //     'meridiem' => false,
            //     'hour12' => false
            // ],
            // 'eventTimeFormat' => [ // for event times
            //     'hour' => '2-digit',
            //     'minute' => '2-digit',
            //     'meridiem' => false, 
            //     'hour12' => false
            // ],

            // 'businessHours' => [
            //     [
            //         'daysOfWeek' => [1,2,3,4,5],
            //         'startTime' => '08:00',
            //         'endTime' => '12:00',
            //     ],
            //     [
            //         'daysOfWeek' => [1,2,3,4,5],
            //         'startTime' => '13:00',
            //         'endTime' => '18:00',
            //     ],
            // ],

        ];
    }



}
