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
                info.el.style.cursor = 'pointer';
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
            Action::make('new_iv_assessment')
                    ->label('Create IV Assessment')
                    ->visible(fn ($record) => can_create_assessment($record))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->button()
                    ->action(function ($record) {
                        $assessmentUrl = new_assessment($record->client, 'iv', $record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),

            Action::make('new_assessment')
                    ->label('Create Assessment')
                    ->visible(fn ($record) => can_create_assessment($record))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->button()
                    ->action(function ($record) {
                        $assessmentUrl = new_assessment($record->client, 'assessment', $record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),

            Action::make('new_pigmentation_assessment')
                    ->label('Create Pigmentation Assessment')
                    ->visible(fn ($record) => can_create_assessment($record))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->button()
                    ->action(function ($record) {
                        $assessment = \App\Models\Assessment::create([
                            'user_id' => $record->client->id,
                            'assessment_type' => 'pigmentation',
                            'status' => \App\Enums\AssessmentStatus::InProgress,
                        ]);
                        $record->update(['assessment_id' => $assessment->id]);
                        $assessmentUrl = new_assessment($record->client, 'pigmentation', $record);
                        $assessmentUrl .= '&assessment_id=' . $assessment->id;
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
                        IconEntry::make('createdBy.first_name')->label('Created By')->placeholder('N/A'),
                        IconEntry::make('updatedBy.first_name')->label('Updated By')->placeholder('N/A'),
                        TextEntry::make('is_emergency')
                            ->label('Emergency Override')
                            ->badge()
                            ->color(fn (bool $state) => $state ? 'danger' : 'gray')
                            ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('notes')->placeholder('N/A'),

                    ])
                    ->columns(3),

                Section::make('Emergency Reason')
                    ->description('Emergency reason details')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn ($record) => ! empty($record->is_emergency))
                    ->schema([
                        TextEntry::make('emergency_reason.capacity')->label('Available Capacity')->numeric(),
                        TextEntry::make('emergency_reason.confirmed')->label('Confirmed Cases')->numeric(),
                        TextEntry::make('emergency_reason.violations')->label('Violations')->badge()->listWithLineBreaks()->color('danger'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Treatment Session Details')
                    ->description('assessment id and treatment session title.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        TextEntry::make('assessment.id')->label('Assessment Id')->placeholder('N/A'),
                        TextEntry::make('treatmentSession.title')->label('Treatment Session Title')->placeholder('N/A'),

                        TextEntry::make('start_session_note')
                            ->label('')
                            ->columnSpanFull()
                            ->getStateUsing(function ($record) {
                                $start = \Carbon\Carbon::parse($record->start_datetime)->subMinutes(15);
                                $now   = now();

                                if ($now->lt($start)) {
                                    $minutesLeft = (int) $now->diffInMinutes($start, false);
                                    return "⏰ The \"Start Session\" button will be available 15 minutes before the appointment (at {$start->format('h:i A')}). It will appear in approximately {$minutesLeft} minute(s).";
                                }

                                return null;
                            })
                            ->visible(fn ($record) => ! can_start_session($record))
                            ->html()
                            ->formatStateUsing(fn ($state) => $state
                                ? "<div style='background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;color:#92400e;font-size:0.85rem;display:flex;align-items:flex-start;gap:8px;'>"
                                    . "<svg xmlns='http://www.w3.org/2000/svg' style='width:18px;height:18px;flex-shrink:0;margin-top:2px;' fill='none' viewBox='0 0 24 24' stroke-width='1.8' stroke='#d97706'><path stroke-linecap='round' stroke-linejoin='round' d='M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z' /></svg>"
                                    . "<span>{$state}</span>"
                                    . "</div>"
                                : ''
                            ),
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
                $emergencyLabel = NULL;
                if($appointment->is_emergency){
                    $statusColor = '#92400E';
                    $emergencyLabel = 'Emergency';
                }
                $isEditable = $appointment->status->value !== 'completed';  // Key: Disable for completed
                return EventData::make()
                    ->id($appointment->id)
                    ->title(
                        "({$appointment->status->getLabel()})"
                        . ($emergencyLabel ? " ({$emergencyLabel})" : '')
                        . " {$appointment->client->name} w/ {$appointment->therapist->name}."
                    )
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
