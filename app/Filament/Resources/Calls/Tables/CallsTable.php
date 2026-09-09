<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Tables;

use App\Enums\Call\CallAnalysisStatus;
use App\Filament\Resources\Calls\Actions\AnalyseCallAction;
use App\Filament\Resources\Calls\Actions\RefreshCallFromProviderAction;
use App\Filament\Resources\Calls\Actions\RematchCallCustomerAction;
use App\Filament\Resources\Calls\Actions\RetryRecordingDownloadAction;
use App\Filament\Resources\Calls\Actions\ViewAnalysisAction;
use App\Filament\Resources\Calls\Actions\ViewTranscriptAction;
use App\Filament\Resources\Calls\Actions\TranscribeCallAction;
use App\Enums\Call\CallDirection;
use App\Enums\Call\CallLinkType;
use App\Enums\Call\CallMatchingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Call;
use App\Models\Lead;
use App\Models\User;
use App\Services\Call\CallIngestionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Grouping\Group;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The unified call list.
 *
 * Nothing on this screen tells a user which provider a call came from unless
 * they ask for it — the provider column is toggled off by default. That is the
 * point of the whole system: reception looks at conversations with patients,
 * not at two telephony integrations.
 */
class CallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            // The largest table this feature produces, and it draws two card
            // cells per row; don't run the query until the page is interactive.
            ->deferLoading()
            // Calls arrive by webhook while the page sits open, and reception
            // works from this screen without touching it. Thirty seconds is
            // slow enough that the query cost stays trivial and fast enough
            // that a call is on screen before anyone thinks to reach for
            // Refresh. The tab counts refresh with it.
            ->poll('30s')
            // A call nobody could attribute is tinted, so the rows needing a
            // human stand out without reading a column. Deliberately not a
            // Tailwind utility: this panel ships no compiled Tailwind, so those
            // class names resolve to nothing. The rule for
            // `fi-row-call-unmatched` is injected in AdminPanelProvider.
            ->recordClasses(fn (Call $record): ?string => $record->matching_status?->needsAttention()
                ? 'fi-row-call-unmatched'
                : null)
            ->columns(static::columns())
            ->groups(static::groups())
            ->filters(static::filters(), layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(fn (Action $action) => $action
                ->button()
                ->label('Filters')
                ->color('primary')
                ->icon('heroicon-o-funnel'))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    // Registered here so the Text and AI badges on the card can
                    // mount them: an action has to be on the table to be
                    // mountable at all. They earn their place in the menu too —
                    // both hide themselves unless the call actually has the
                    // thing they show.
                    ViewTranscriptAction::make(),
                    ViewAnalysisAction::make(),
                    static::matchCustomerAction(),
                    // Temporarily off with the Outcome column; see the note
                    // where that column used to be.
                    // static::recordOutcomeAction(),
                    // Temporarily off with the rest of follow-up; see the note
                    // on the Follow-up filter below.
                    // static::followUpAction(),
                    TranscribeCallAction::make(),
                    AnalyseCallAction::make(),
                    // The same repairs the call page offers. This is where
                    // somebody notices a row with no recording or an unmatched
                    // caller, so it is where the fix belongs — opening the call
                    // to press one button is a page load per repair.
                    RetryRecordingDownloadAction::make(),
                    RematchCallCustomerAction::make(),
                    RefreshCallFromProviderAction::make(),

                    // Delete, restore, and delete for good — the third of which
                    // takes the audio off disk with it, so it is worded as what
                    // it is rather than as a tidier "delete".
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make()
                        ->label('Delete permanently')
                        ->modalHeading('Delete this call permanently')
                        ->modalDescription('The call, its recording, transcript and analysis are removed for good. The audio file is deleted from storage too. This cannot be undone.')
                        ->modalSubmitActionLabel('Delete permanently'),
                ]),
            ],
                // Moved to the front of the row. Reviewing calls is a repeated
                // trip to the same menu, and at the far right that trip crossed
                // the whole table — past a card, a name, a provider and a
                // player — every time. First column puts it where the pointer
                // already is when a row is chosen.
                position: RecordActionsPosition::BeforeColumns,
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->label('Delete permanently')
                        ->modalHeading('Delete these calls permanently')
                        ->modalDescription('Every selected call, with its recording, transcript and analysis, is removed for good. The audio files are deleted from storage too. This cannot be undone.')
                        ->modalSubmitActionLabel('Delete permanently'),
                ]),
            ])
            ->emptyStateHeading('No calls yet')
            ->emptyStateDescription('Calls appear here as soon as Exotel or Callyzer starts sending them.')
            ->emptyStateIcon('heroicon-o-phone');
    }


    /**
     * Outcomes offered inline. Free text is still accepted from the row action;
     * these are the ones staff reach for often enough to be worth one click.
     *
     * @var array<string, string>
     */
    protected const OUTCOMES = [
        'Interested' => 'Interested',
        'Not interested' => 'Not interested',
        'Appointment booked' => 'Appointment booked',
        'Call back later' => 'Call back later',
        'Wrong number' => 'Wrong number',
        'No answer' => 'No answer',
        'Price objection' => 'Price objection',
    ];

    /**
     * The row is two cards rather than a strip of text columns.
     *
     * Judging a call is a judgement about the whole thing at once — who rang,
     * whether anyone spoke to them, how long for, and what came of it. The
     * previous layout made a receptionist reassemble that from six narrow
     * columns, and gave the provider — the one fact nobody needs — a column of
     * its own.
     *
     * @return array<int, mixed>
     */
    protected static function columns(): array
    {
        return [
            TextColumn::make('clinic.name')
                ->label('Clinic')
                ->badge()
                ->color('info')
                ->placeholder('Unassigned')
                ->verticalAlignment(VerticalAlignment::Center)
                ->toggleable(isToggledHiddenByDefault: true)
                ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),

            // Named `started_at` rather than `card`, so the header still sorts
            // on the column the list is actually ordered by.
            ViewColumn::make('started_at')
                ->label('Call')
                ->view('filament.tables.columns.call-card')
                ->verticalAlignment(VerticalAlignment::Start)
                // Shrink to fit the card rather than swallow the row.
                //
                // "1%" is the shrink-to-content idiom for a full-width table:
                // the browser cannot honour it, so it falls back to the cell's
                // minimum content width and hands the remainder to whichever
                // column has no constraint. Every other column here is either
                // fixed or content-sized, so that column is "Handled by" —
                // which is the right one to stretch, being the only other cell
                // holding a name that can run long.
                //
                // Without this the card had no upper bound at all, and taking
                // the Outcome column out gave it another 11rem to spread into.
                ->width('1%')
                // customer_name is an accessor, so search has to name the
                // underlying columns explicitly.
                ->searchable(query: fn (Builder $query, string $search): Builder => $query
                    ->where(fn (Builder $inner): Builder => $inner
                        ->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone_normalized', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%")
                        ->orWhere('crm_outcome', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $user): Builder => $user
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%"))
                        ->orWhereHas('lead', fn (Builder $lead): Builder => $lead
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))))
                ->sortable(),

            ViewColumn::make('agent.name')
                ->label('Handled by')
                ->view('filament.tables.columns.call-agent')
                ->verticalAlignment(VerticalAlignment::Start)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query
                    ->where(fn (Builder $inner): Builder => $inner
                        ->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('employee_phone_normalized', 'like', "%{$search}%")
                        ->orWhereHas('agent', fn (Builder $user): Builder => $user
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")))),

            // Lifted out of the "Handled by" cell, where it sat as the fourth
            // muted line under a name and a number and read as a footnote about
            // the agent. It is not: it says which system recorded the call, and
            // when two providers are running it is the first thing anyone
            // checks when a call looks wrong.
            //
            // The enum carries its own label, colour and icon, so the badge
            // needs none of them spelled out here.
            TextColumn::make('provider')
                ->label('Provider')
                ->badge()
                ->verticalAlignment(VerticalAlignment::Center)
                ->alignCenter()
                // ->width('8rem')
                ->sortable()
                ->toggleable(),

            // Its own column rather than a badge in the card, because it is a
            // control rather than a fact: reviewing calls means sampling a lot
            // of them, and opening each one to press play made that a page load
            // per call.
            ViewColumn::make('recording')
                ->label('Recording')
                ->view('filament.tables.columns.call-recording')
                ->verticalAlignment(VerticalAlignment::Center)
                // ->alignCenter()
                // ->width('9rem')
                ->visible(fn (): bool => auth()->user()?->can('viewAny', Call::class) ?? false),

            // ─── Outcome, temporarily switched off ──────────────────────────
            //
            // Commented rather than deleted, at the client's request, while the
            // outcome vocabulary is reconsidered. Nothing behind it is removed:
            // crm_outcome is still stored, still searchable, still shown on the
            // call page, and anything already recorded is untouched. Only the
            // two ways of setting it from this screen are hidden — this column
            // and the "Record outcome" row action.
            //
            // The SelectColumn import and recordOutcomeAction() below are kept
            // for the same reason: turning this back on should be an uncomment,
            // not a reconstruction. Neither is dead code to be tidied away.
            //
            // Editable in place: recording an outcome is the most common thing
            // anyone does to a call, and as a row action it sat two clicks and
            // a modal away.
            // SelectColumn::make('crm_outcome')
            //     ->label('Outcome')
            //     ->options(static::OUTCOMES)
            //     ->placeholder('Not recorded')
            //     ->verticalAlignment(VerticalAlignment::Center)
            //     ->width('11rem')
            //     ->disabled(fn (Call $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
            //     // SelectColumn saves silently; name the outcome so the change is
            //     // visible without re-reading the row.
            //     ->afterStateUpdated(fn (Call $record, $state) => Notification::make()
            //         ->success()
            //         ->title('Outcome saved')
            //         ->body(filled($state) ? 'Marked as ' . $state . '.' : 'Outcome cleared.')
            //         ->send()),

            TextColumn::make('created_at')
                ->label('Recorded')
                ->dateTime(app_datetime_format())
                ->timezone(app_timezone())
                ->verticalAlignment(VerticalAlignment::Center)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Grouping options.
     *
     * Grouped on ids wherever two records could share a name: grouping calls by
     * a patient's name would silently merge two different people into one
     * bucket, which is the same trap the Ads table avoids for its posters.
     *
     * @return array<int, Group>
     */
    protected static function groups(): array
    {
        return [
            Group::make('started_at')
                ->label('Date')
                ->date()
                ->collapsible(),

            Group::make('agent_user_id')
                ->label('Agent')
                ->collapsible()
                ->getTitleFromRecordUsing(fn (Call $record): string => $record->agent?->name
                    ?: ($record->employee_name ?: 'Unassigned')),

            Group::make('direction')
                ->label('Direction')
                ->collapsible()
                ->getTitleFromRecordUsing(fn (Call $record): string => $record->direction?->getLabel() ?? 'Unknown'),

            Group::make('call_status')
                ->label('Status')
                ->collapsible()
                ->getTitleFromRecordUsing(fn (Call $record): string => $record->call_status?->getLabel() ?? 'Unknown'),

            Group::make('provider')
                ->label('Provider')
                ->collapsible()
                ->getTitleFromRecordUsing(fn (Call $record): string => $record->provider?->getLabel() ?? '-'),
        ];
    }

    /**
     * Restrict a call subquery to the calls this user is allowed to see.
     *
     * The filter options are built from the calls table, so without this a
     * clinic could infer another clinic's staff and patients from the names
     * offered in the dropdown.
     */
    protected static function visibleCalls(Builder $query): Builder
    {
        return $query->when(
            ! check_role(config('project.roles.super_admin')),
            fn (Builder $calls): Builder => $calls->forCurrentClinic(),
        );
    }

    /**
     * @return array<int, mixed>
     */
    protected static function filters(): array
    {
        return [
            SelectFilter::make('direction')
                ->options(CallDirection::options())
                ->multiple(),

            SelectFilter::make('call_status')
                ->label('Status')
                ->options(CallStatus::options())
                ->multiple(),

            SelectFilter::make('provider')
                ->options(CallProvider::options())
                ->multiple(),

            SelectFilter::make('matching_status')
                ->label('Match status')
                ->options(CallMatchingStatus::options())
                ->multiple(),

            // Separate from Match status on purpose. That one asks how well the
            // matching worked; this asks who the caller is — and "show me every
            // call with a lead this week" is a sales question nobody could ask
            // this table before.
            //
            // Not a relationship filter: the answer spans two nullable foreign
            // keys with a precedence between them, which lives in the scope.
            SelectFilter::make('link_type')
                ->label('Linked to')
                ->options(CallLinkType::options())
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->linkedTo($data['value'])
                    : $query),

            // Both lists are narrowed to people who actually appear on a call.
            // Offering the whole user table meant scrolling past hundreds of
            // patients and staff who have never been on the phone, every one of
            // which filters the table down to nothing.
            SelectFilter::make('agent_user_id')
                ->label('Agent')
                ->relationship(
                    'agent',
                    'first_name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereHas('handledCalls', fn (Builder $calls): Builder => static::visibleCalls($calls)),
                )
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                ->searchable()
                ->preload(),

            SelectFilter::make('customer_user_id')
                ->label('Client')
                ->relationship(
                    'customer',
                    'first_name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereHas('calls', fn (Builder $calls): Builder => static::visibleCalls($calls)),
                )
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                ->searchable()
                ->preload(),

            // Grouped as one filter rather than three toggles: "connected" is a
            // single question, and the three states are mutually exclusive.
            SelectFilter::make('connection')
                ->label('Connection')
                ->options([
                    'connected' => 'Connected',
                    'unanswered' => 'Not answered',
                ])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'connected' => $query->where('is_connected', true),
                    'unanswered' => $query->whereIn('call_status', CallStatus::unansweredValues()),
                    default => $query,
                }),

            Filter::make('started_at')
                ->schema([
                    DatePicker::make('from')->label('Called from')->placeholder('Any date'),
                    DatePicker::make('until')->label('Called until')->placeholder('Any date'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('started_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('started_at', '<=', $date)))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['from'] ?? null) {
                        $indicators[] = 'From ' . $data['from'];
                    }

                    if ($data['until'] ?? null) {
                        $indicators[] = 'Until ' . $data['until'];
                    }

                    return $indicators;
                }),

            Filter::make('duration')
                ->schema([
                    // Worked examples rather than "0": 60 and 300 are the two
                    // cuts staff actually reach for — anything under a minute
                    // is a wrong number, anything over five is a consultation.
                    TextInput::make('min_seconds')->label('Longer than (seconds)')->numeric()->placeholder('e.g. 60'),
                    TextInput::make('max_seconds')->label('Shorter than (seconds)')->numeric()->placeholder('e.g. 300'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['min_seconds'] ?? null, fn (Builder $q, $value): Builder => $q
                        ->whereRaw('COALESCE(talk_duration_seconds, duration_seconds, 0) >= ?', [(int) $value]))
                    ->when($data['max_seconds'] ?? null, fn (Builder $q, $value): Builder => $q
                        ->whereRaw('COALESCE(talk_duration_seconds, duration_seconds, 0) <= ?', [(int) $value]))),

            TernaryFilter::make('has_recording')
                ->label('Recording available'),

            TernaryFilter::make('transcribed')
                ->label('Transcribed')
                ->queries(
                    true: fn (Builder $query): Builder => $query->where('transcription_status', TranscriptionStatus::Completed->value),
                    false: fn (Builder $query): Builder => $query->where('transcription_status', '!=', TranscriptionStatus::Completed->value),
                ),

            TernaryFilter::make('analysed')
                ->label('AI analysed')
                ->queries(
                    true: fn (Builder $query): Builder => $query->where('analysis_status', CallAnalysisStatus::Completed->value),
                    false: fn (Builder $query): Builder => $query->where('analysis_status', '!=', CallAnalysisStatus::Completed->value),
                ),

            // ─── Follow-up, temporarily switched off ────────────────────────
            //
            // Commented rather than deleted, at the client's request, on the
            // same terms as the Outcome column above: the ways to act on a
            // follow-up and to organise the list by one are hidden, while what
            // is already stored stays visible.
            //
            // Off with it: this filter, the "Flag for follow-up" row action,
            // and the Follow-up tab on ListCalls. Still there: the Follow-up
            // section on the call page and the badge on a flagged row, so a
            // call somebody already flagged does not look as though the flag
            // were lost.
            //
            // needsFollowUp() on the model is untouched and still used by the
            // action queue widgets, which are outside this resource.
            //
            // TernaryFilter::make('follow_up_required')
            //     ->label('Follow-up needed')
            //     ->queries(
            //         true: fn (Builder $query): Builder => $query->needsFollowUp(),
            //         false: fn (Builder $query): Builder => $query->where('follow_up_required', false),
            //     ),

            TrashedFilter::make(),
        ];
    }

    /**
     * Attach an unmatched or ambiguous call to the right person.
     *
     * The screen that makes ambiguity actionable rather than merely visible.
     * When the number matched several records, those candidates are offered
     * directly — the resolver already found them, and making a human search for
     * them again would be the slow way to answer a question the CRM has
     * already asked.
     */
    protected static function matchCustomerAction(): Action
    {
        return Action::make('matchCustomer')
            ->label('Match customer')
            ->icon('heroicon-o-user-plus')
            ->color('warning')
            ->visible(fn (Call $record): bool => $record->matching_status?->needsAttention() === true
                || $record->matching_status === CallMatchingStatus::ManuallyMatched)
            ->authorize(fn (Call $record): bool => auth()->user()->can('matchCustomer', $record))
            ->modalHeading('Who was this call with?')
            ->modalDescription(fn (Call $record): string => sprintf(
                'Number on the call: %s',
                $record->client_phone_normalized ?: $record->client_phone ?: 'unknown',
            ))
            ->schema([
                Select::make('customer_user_id')
                    ->label('Patient')
                    ->options(fn (Call $record): array => static::candidateUsers($record))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where(fn (Builder $q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->id => $user->name . ' — ' . $user->mobile])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->placeholder('Search by name or number'),

                Select::make('lead_id')
                    ->label('Lead')
                    ->options(fn (Call $record): array => static::candidateLeads($record))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Lead::query()
                        ->where(fn (Builder $q) => $q
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Lead $lead): array => [$lead->id => $lead->display_name . ' — ' . $lead->phone])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Lead::find($value)?->display_name)
                    ->placeholder('Search by name or number')
                    ->helperText('Leave both empty to mark the call as belonging to nobody in the CRM.'),
            ])
            ->action(function (Call $record, array $data): void {
                app(CallIngestionService::class)->matchManually(
                    $record,
                    $data['customer_user_id'] ? (int) $data['customer_user_id'] : null,
                    $data['lead_id'] ? (int) $data['lead_id'] : null,
                );

                Notification::make()
                    ->success()
                    ->title('Call matched')
                    ->body('This call now appears in that person\'s history, and no future sync will change it.')
                    ->send();
            });
    }

    /**
     * The candidates the resolver already found, offered first.
     *
     * @return array<int, string>
     */
    protected static function candidateUsers(Call $record): array
    {
        $ids = collect($record->match_candidates ?? [])
            ->where('type', 'patient')
            ->pluck('id')
            ->all();

        if ($record->customer_user_id !== null) {
            $ids[] = $record->customer_user_id;
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name . ' — ' . $user->mobile])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function candidateLeads(Call $record): array
    {
        $ids = collect($record->match_candidates ?? [])
            ->where('type', 'lead')
            ->pluck('id')
            ->all();

        if ($record->lead_id !== null) {
            $ids[] = $record->lead_id;
        }

        return Lead::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Lead $lead): array => [$lead->id => $lead->display_name . ' — ' . $lead->phone])
            ->all();
    }

    /**
     * Record what came of the call.
     *
     * NOT CURRENTLY WIRED UP. Its entry in recordActions() is commented out
     * alongside the Outcome column, temporarily, while the outcome vocabulary
     * is reconsidered. Kept intact so restoring it is a one-line uncomment.
     *
     * Writes only to the CRM-owned columns. The provider's own status and note
     * sit beside these untouched and are shown next to them on the detail page,
     * so a re-sync can never overwrite what a staff member concluded and a
     * staff member can never erase what the provider reported.
     */
    protected static function recordOutcomeAction(): Action
    {
        return Action::make('recordOutcome')
            ->label('Record outcome')
            ->icon('heroicon-o-clipboard-document-check')
            ->authorize(fn (Call $record): bool => auth()->user()->can('update', $record))
            ->fillForm(fn (Call $record): array => [
                'crm_outcome' => $record->crm_outcome,
                'crm_note' => $record->crm_note,
            ])
            ->schema([
                TextInput::make('crm_outcome')
                    ->label('Outcome')
                    ->maxLength(60)
                    // The field is free text with a suggestion list, so the
                    // placeholder has to say that typing something new is
                    // allowed — otherwise it reads as a broken dropdown.
                    ->placeholder('Choose one, or type your own')
                    ->datalist([
                        'Interested',
                        'Not interested',
                        'Appointment booked',
                        'Call back later',
                        'Wrong number',
                        'No answer',
                        'Price objection',
                    ]),

                Textarea::make('crm_note')
                    ->label('Note')
                    ->rows(4)
                    ->placeholder('What was agreed, and anything the next person should know before ringing back')
                    ->helperText('Kept separate from the note the agent typed in the provider app, which is never overwritten.'),
            ])
            ->action(function (Call $record, array $data): void {
                $record->forceFill([
                    'crm_outcome' => $data['crm_outcome'] ?: null,
                    'crm_note' => $data['crm_note'] ?: null,
                ])->save();

                Notification::make()->success()->title('Outcome saved')->send();
            });
    }

    /**
     * Flag a call for a callback.
     *
     * NOT CURRENTLY WIRED UP. Its entry in recordActions() is commented out
     * along with the Follow-up filter and tab, temporarily. Kept intact so
     * restoring it is a one-line uncomment.
     */
    protected static function followUpAction(): Action
    {
        return Action::make('followUp')
            ->label(fn (Call $record): string => $record->follow_up_required ? 'Update follow-up' : 'Flag for follow-up')
            ->icon('heroicon-o-flag')
            ->authorize(fn (Call $record): bool => auth()->user()->can('update', $record))
            ->fillForm(fn (Call $record): array => [
                'follow_up_required' => $record->follow_up_required,
                'follow_up_at' => $record->follow_up_at,
                'follow_up_reason' => $record->follow_up_reason,
                'completed' => $record->follow_up_completed_at !== null,
            ])
            ->schema([
                Toggle::make('follow_up_required')->label('Needs a follow-up'),
                DateTimePicker::make('follow_up_at')->label('Due')->seconds(false)->placeholder('When to call back'),
                TextInput::make('follow_up_reason')
                    ->label('Reason')
                    ->maxLength(255)
                    ->placeholder('e.g. Wanted to check the price with her husband'),
                Toggle::make('completed')->label('Already done'),
            ])
            ->action(function (Call $record, array $data): void {
                $record->forceFill([
                    'follow_up_required' => (bool) $data['follow_up_required'],
                    'follow_up_at' => $data['follow_up_at'] ?: null,
                    'follow_up_reason' => $data['follow_up_reason'] ?: null,
                    'follow_up_completed_at' => $data['completed'] ? ($record->follow_up_completed_at ?? now()) : null,
                ])->save();

                Notification::make()->success()->title('Follow-up updated')->send();
            });
    }
}
