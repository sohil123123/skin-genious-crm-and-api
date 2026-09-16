<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\LeadActionLog;
use App\Models\LeadCustomField;
use App\Services\Lead\LeadActionService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Meta lead action queue.
 *
 * Deliberately mirrors AiActionQueue's interaction model — same card grid, same
 * outcome buttons, same Regenerate action — so staff move between the Patients
 * and Leads tabs without relearning anything.
 */
class LeadActionQueue extends TableWidget
{
    use HasWidgetShield;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Lead Action Queue';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    /** Temporary notes storage for outcome recording */
    public string $outcomeNotes = '';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();

                $query = LeadActionLog::query()
                    ->forToday()
                    ->with([
                        'lead.fieldValues.customField', 'clinic', 'matchedUser',
                        // Same reason as the patient queue: the analysis is on
                        // the card, so it is loaded with the page.
                        'relatedCall.currentAnalysis',
                    ])
                    ->orderByDesc('priority_score');

                if ($user && ! $user->hasRole('super_admin') && $user->clinic_id) {
                    $query->forClinic($user->clinic_id);
                }

                return $query;
            })
            ->columns([
                View::make('filament.tables.lead-action-card'),
            ])
            // Searching has to be declared on the table, not as hidden columns
            // inside the card layout: Filament skips hidden columns when it
            // builds the search constraint, so those never match anything.
            ->searchable([
                fn (Builder $query, string $search): Builder => $query->whereHas(
                    'lead',
                    fn (Builder $lead): Builder => $lead->where(
                        fn (Builder $match): Builder => $match
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', '%' . static::phoneNeedle($search) . '%')
                            ->orWhere('phone_raw', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('campaign_name', 'like', "%{$search}%")
                            // Staff search by what the lead asked for as often as
                            // by who they are, so the answers are searchable too.
                            ->orWhereHas(
                                'fieldValues',
                                fn (Builder $answers): Builder => $answers
                                    ->where('value', 'like', "%{$search}%")
                                    ->orWhere('value_normalized', 'like', "%{$search}%")
                            )
                    )
                ),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([12, 24, 48])
            ->defaultSort('priority_score', 'desc')
            ->filters([
                SelectFilter::make('action_trigger')
                    ->label('Trigger')
                    ->options(LeadActionLog::triggerLabels())
                    ->placeholder('All triggers'),

                SelectFilter::make('action_category')
                    ->label('Category')
                    ->options(LeadActionLog::categoryLabels())
                    ->placeholder('All categories'),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (($data['value'] ?? null) === 'completed') {
                            $query->withOutcome();
                        } elseif (($data['value'] ?? null) === 'pending') {
                            $query->active()->pending();
                        }
                    })
                    ->default('pending')
                    ->placeholder('All statuses'),

                // Filtering by what the lead said about timing is the most
                // useful cut of this queue, so it is a first-class filter.
                SelectFilter::make('visit_timing')
                    ->label('Visit timing')
                    ->options(fn (): array => static::visitTimingOptions())
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return;
                        }

                        $query->whereHas('lead.fieldValues', function (Builder $inner) use ($value): void {
                            $inner->where('value', $value)
                                ->whereHas('customField', fn (Builder $f) => $f->where('key', 'when_would_you_like_to_visit'));
                        });
                    })
                    ->placeholder('Any timing'),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->headerActions([
                Action::make('regenerateLeads')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (): void {
                        $user = auth()->user();
                        $clinicIds = ($user && ! $user->hasRole('super_admin') && $user->clinic_id)
                            ? [$user->clinic_id]
                            : null;

                        $count = app(LeadActionService::class)->generateForToday($clinicIds);

                        Notification::make()
                            ->title("Generated {$count} lead actions for today")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make($this->outcomeActions())
                    ->label('Record Outcome')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('primary')
                    ->button()
                    ->visible(fn (LeadActionLog $record): bool => ! $record->staff_outcome),
            ])
            ->emptyStateHeading('No lead actions')
            ->emptyStateDescription('Click "Regenerate" to build today\'s queue from your imported Meta leads.')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->poll('30s');
    }

    /**
     * The one-click outcome buttons, matching the patient queue exactly.
     *
     * @return array<int, Action>
     */
    protected function outcomeActions(): array
    {
        $quick = [
            'called' => ['Called', 'heroicon-m-phone', 'info'],
            'no_answer' => ['No Answer', 'heroicon-m-phone-x-mark', 'gray'],
            'whatsapp_sent' => ['WhatsApp Sent', 'heroicon-m-chat-bubble-left-right', 'success'],
            'booked' => ['Booked', 'heroicon-m-check-circle', 'success'],
            'not_interested' => ['Not Interested', 'heroicon-m-x-mark', 'danger'],
            'call_later' => ['Call Later', 'heroicon-m-clock', 'warning'],
        ];

        $actions = [];

        foreach ($quick as $outcome => [$label, $icon, $color]) {
            $actions[] = Action::make($outcome)
                ->label($label)
                ->icon($icon)
                ->color($color)
                ->action(fn (LeadActionLog $record) => $this->quickOutcome($record->id, $outcome))
                ->visible(fn (LeadActionLog $record): bool => ! $record->staff_outcome);
        }

        $actions[] = Action::make('add_notes')
            ->label('+ Notes')
            ->icon('heroicon-m-pencil-square')
            ->color('gray')
            ->schema([
                Textarea::make('outcome_notes')
                    ->label('Staff notes')
                    ->placeholder('e.g. Asked to be called back Monday after 2 PM…')
                    ->rows(3),
                Select::make('outcome')
                    ->label('Outcome')
                    ->options(LeadActionLog::outcomeOptions())
                    ->required(),
            ])
            ->action(function (LeadActionLog $record, array $data): void {
                $this->outcomeNotes = $data['outcome_notes'] ?? '';
                $this->recordOutcome($record->id, $data['outcome']);
            })
            ->visible(fn (LeadActionLog $record): bool => ! $record->staff_outcome);

        return $actions;
    }

    /**
     * Record an outcome, and move the lead's own status along with it.
     *
     * Writing back to the lead is what stops the same enquiry reappearing in
     * tomorrow's queue as though nothing had happened.
     */
    public function recordOutcome(int $actionId, string $outcome): void
    {
        $action = LeadActionLog::find($actionId);

        if (! $action) {
            Notification::make()->title('Action not found')->danger()->send();

            return;
        }

        $action->update([
            'staff_outcome' => $outcome,
            'outcome_at' => Carbon::now(),
            'outcome_notes' => $this->outcomeNotes ?: null,
            'is_active' => false,
        ]);

        if ($lead = $action->lead) {
            $newStatus = match ($outcome) {
                'booked' => \App\Enums\LeadStatus::Won,
                'not_interested', 'do_not_contact' => \App\Enums\LeadStatus::Lost,
                'called', 'whatsapp_sent', 'no_answer', 'call_later' => \App\Enums\LeadStatus::Contacted,
                default => null,
            };

            if ($newStatus !== null && $lead->status !== $newStatus) {
                $lead->update(['status' => $newStatus->value]);
            }
        }

        $this->outcomeNotes = '';

        Notification::make()
            ->title('Outcome recorded: ' . (LeadActionLog::outcomeOptions()[$outcome] ?? $outcome))
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

    /**
     * Reduce a typed phone number to the digits actually stored.
     *
     * Numbers are persisted E.164 (+918696299993) while staff type them any
     * number of ways — 08696299993, +91 86962 99993, (869) 629-9993. Matching on
     * the trailing national digits makes all of those find the same lead. Text
     * searches are returned untouched so names still match.
     */
    protected static function phoneNeedle(string $search): string
    {
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        if ($digits === '' || strlen($digits) < 4) {
            return $search;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    /**
     * Visit-timing options, read from the imported question's own option list.
     *
     * @return array<string, string>
     */
    protected static function visitTimingOptions(): array
    {
        $field = LeadCustomField::query()
            ->where('key', 'when_would_you_like_to_visit')
            ->first();

        return collect($field?->options ?? [])
            ->mapWithKeys(fn (string $option): array => [$option => LeadCustomField::humanizeValue($option)])
            ->all();
    }
}
