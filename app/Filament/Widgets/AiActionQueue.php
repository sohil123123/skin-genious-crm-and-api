<?php

namespace App\Filament\Widgets;

use App\Models\AiActionLog;
use App\Services\AiActionService;

use Filament\Widgets\TableWidget;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Enums\FiltersLayout;

use Illuminate\Database\Eloquent\Builder;

use Carbon\Carbon;

class AiActionQueue extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    /** Temporary notes storage for outcome recording */
    public string $outcomeNotes = '';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();

                $query = AiActionLog::query()
                    ->forToday()
                    ->with(['client', 'clinic', 'relatedAppointment', 'relatedPackage', 'relatedAssessment'])
                    ->orderByDesc('priority_score');

                // Clinic scope: super_admin sees all, others see only their clinic
                if ($user && !$user->hasRole('super_admin') && $user->clinic_id) {
                    $query->forClinic($user->clinic_id);
                }

                return $query;
            })
            ->columns([
                View::make('filament.tables.ai-action-card')
                    ->components([
                        // Hidden searchable columns for table search functionality
                        TextColumn::make('client.first_name')
                            ->searchable()
                            ->hidden(),
                        TextColumn::make('client.last_name')
                            ->searchable()
                            ->hidden(),
                        TextColumn::make('client.mobile')
                            ->searchable()
                            ->hidden(),
                    ]),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([12, 24, 48])
            ->defaultSort('priority_score', 'desc')
            ->filters([
                SelectFilter::make('action_category')
                    ->label('Category')
                    ->options(AiActionLog::categoryLabels())
                    ->placeholder('All Categories'),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'completed') {
                            $query->withOutcome();
                        } elseif ($data['value'] === 'pending') {
                            $query->active()->pending();
                        }
                    })
                    ->default('pending')
                    ->placeholder('All Statuses'),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->headerActions([
                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $service = app(AiActionService::class);
                        $user = auth()->user();
                        $clinicIds = null;

                        if ($user && !$user->hasRole('super_admin') && $user->clinic_id) {
                            $clinicIds = [$user->clinic_id];
                        }

                        $count = $service->generateForToday($clinicIds);

                        Notification::make()
                            ->title("Generated {$count} AI actions for today")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('called')
                        ->label('Called')
                        ->icon('heroicon-m-phone')
                        ->color('info')
                        ->action(fn(AiActionLog $record) => $this->quickOutcome($record->id, 'called'))
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),

                    Action::make('no_answer')
                        ->label('No Answer')
                        ->icon('heroicon-m-phone-x-mark')
                        ->color('gray')
                        ->action(fn(AiActionLog $record) => $this->quickOutcome($record->id, 'no_answer'))
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),

                    Action::make('whatsapp_sent')
                        ->label('WhatsApp Sent')
                        ->icon('heroicon-m-chat-bubble-left-right')
                        ->color('success')
                        ->action(fn(AiActionLog $record) => $this->quickOutcome($record->id, 'whatsapp_sent'))
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),

                    Action::make('booked')
                        ->label('Booked')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(fn(AiActionLog $record) => $this->quickOutcome($record->id, 'booked'))
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),

                    Action::make('not_interested')
                        ->label('Not Interested')
                        ->icon('heroicon-m-x-mark')
                        ->color('danger')
                        ->action(fn(AiActionLog $record) => $this->quickOutcome($record->id, 'not_interested'))
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),

                    Action::make('call_later')
                        ->label('Call Later')
                        ->icon('heroicon-m-clock')
                        ->color('warning')
                        ->action(fn(AiActionLog $record) => $this->quickOutcome($record->id, 'call_later'))
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),

                    Action::make('add_notes')
                        ->label('+ Notes')
                        ->icon('heroicon-m-pencil-square')
                        ->color('gray')
                        ->form([
                            Textarea::make('outcome_notes')
                                ->label('Staff Notes')
                                ->placeholder('e.g. Client requested callback on Monday after 2 PM…')
                                ->rows(3),
                            Select::make('outcome')
                                ->label('Outcome')
                                ->options(AiActionLog::outcomeOptions())
                                ->required(),
                        ])
                        ->action(function (AiActionLog $record, array $data) {
                            $this->outcomeNotes = $data['outcome_notes'] ?? '';
                            $this->recordOutcome($record->id, $data['outcome']);
                        })
                        ->visible(fn(AiActionLog $record) => !$record->staff_outcome),
                ])
                    ->label('Record Outcome')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('primary')
                    ->button()
                    ->visible(fn(AiActionLog $record) => !$record->staff_outcome),
            ])
            ->emptyStateHeading('No actions found')
            ->emptyStateDescription('Click "Regenerate" to analyse CRM data and build today\'s action queue.')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->poll('30s');
    }

    /**
     * Record an outcome for an action.
     */
    public function recordOutcome(int $actionId, string $outcome): void
    {
        $action = AiActionLog::find($actionId);

        if (!$action) {
            Notification::make()
                ->title('Action not found')
                ->danger()
                ->send();
            return;
        }

        $action->update([
            'staff_outcome' => $outcome,
            'outcome_at' => Carbon::now(),
            'outcome_notes' => $this->outcomeNotes ?: null,
            'is_active' => false,
        ]);

        $this->outcomeNotes = '';

        $outcomeLabel = AiActionLog::outcomeOptions()[$outcome] ?? $outcome;

        Notification::make()
            ->title("Outcome recorded: {$outcomeLabel}")
            ->success()
            ->send();
    }

    /**
     * Quick outcome — single click without notes.
     */
    public function quickOutcome(int $actionId, string $outcome): void
    {
        $this->outcomeNotes = '';
        $this->recordOutcome($actionId, $outcome);
    }
}
