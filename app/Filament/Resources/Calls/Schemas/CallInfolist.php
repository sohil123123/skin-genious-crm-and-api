<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Schemas;

use App\Enums\Call\CallAnalysisStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Enums\Call\TranscriptSpeakerType;
use App\Filament\Resources\Calls\Actions\AnalyseCallAction;
use App\Filament\Resources\Calls\Actions\TranscribeCallAction;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Call;
use App\Models\CallRecording;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * One call in full.
 *
 * Laid out like the Ad view page: a wide left column carrying the substance of
 * the record — what was said, the audio, the transcript, what AI made of it —
 * and a narrow right column for the facts you check rather than read: timing,
 * who was involved, and where the record came from.
 *
 * Tabs were the earlier shape and were wrong for this. A call has four things
 * worth seeing and they are consulted together — you listen to the recording
 * while reading the outcome someone typed — so hiding three of them behind
 * clicks made the page slower to use than the list it was opened from.
 *
 * The raw provider payload stays gated on a super admin: it holds every phone
 * number involved, unredacted, and is a diagnostic artefact rather than a CRM
 * screen.
 */
class CallInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // ViewRecord::defaultInfolist() applies columns(2) to the root
            // schema unless it already declares its own, which would confine
            // everything below to the left half of the page.
            ->columns(1)
            ->components([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // ---------- left column ----------
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                static::conversationSection(),
                                static::outcomeSection(),
                                static::transcriptSection(),
                                static::analysisSection(),
                            ]),

                        // ---------- right column ----------
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                static::participantsSection(),
                                static::timingSection(),
                                static::followUpSection(),
                                static::providerSection(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Who rang, from where, and how it went — the four facts the page exists
     * to answer, above everything else.
     */
    protected static function conversationSection(): Section
    {
        return Section::make('Call')
            ->icon('heroicon-o-phone')
            ->schema([
                TextEntry::make('customer_name')
                    ->label('Customer')
                    ->size('lg')
                    ->weight('bold')
                    ->url(fn (Call $record): ?string => $record->customer_user_id !== null
                        ? UserResource::getUrl('edit', ['record' => $record->customer_user_id])
                        : ($record->lead_id !== null
                            ? LeadResource::getUrl('view', ['record' => $record->lead_id])
                            : null)),

                TextEntry::make('client_phone_normalized')
                    ->label('Number')
                    ->copyable()
                    ->placeholder('Withheld'),

                TextEntry::make('direction')->label('Direction')->badge(),

                TextEntry::make('call_status')->label('Status')->badge(),

                TextEntry::make('duration_for_humans')
                    ->label('Duration')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not reported'),

                TextEntry::make('matching_status')
                    ->label('Match')
                    ->badge()
                    ->helperText(fn (Call $record): ?string => $record->matching_method?->getLabel()),

                TextEntry::make('recordings_player')
                    ->hiddenLabel()
                    ->state(fn (Call $record): HtmlString => static::renderPlayers($record))
                    ->html()
                    // The player moved into this section from a Recording
                    // section of its own, and the policy check did not come
                    // with it - so a user without PlayRecording was being
                    // handed controls and a stream URL. The route re-checks the
                    // policy, so no audio ever reached them, but the panel must
                    // not offer what it will refuse.
                    ->visible(fn (Call $record): bool => auth()->user()?->can('playRecording', $record) ?? false)
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 2, 'sm' => 3]);
    }

    /**
     * Provider and CRM side by side, because the whole point is that neither
     * overwrites the other. Seeing "Interested" next to "Appointment booked"
     * is the feature, not a duplication.
     */
    protected static function outcomeSection(): Section
    {
        return Section::make('Outcome and notes')
            ->icon('heroicon-o-clipboard-document-check')
            // Only promises both halves when both halves can exist. These two
            // provider fields come from an agent typing into the Callyzer app;
            // Exotel is a telephony flow with no such screen, so on an Exotel
            // call the sentence described something the CRM was never going to
            // show - and four dashes under it read as breakage rather than as
            // "not applicable here".
            ->description(fn (Call $record): string => static::hasProviderOutcome($record)
                ? 'What the provider recorded, and what the clinic concluded. Neither overwrites the other.'
                : 'What the clinic concluded. ' . ($record->provider?->getLabel() ?? 'This provider')
                    . ' sends no agent status or note of its own.')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextEntry::make('provider_crm_status')
                    ->label('Provider status')
                    ->badge()
                    ->color('gray')
                    ->visible(fn (Call $record): bool => filled($record->provider_crm_status)),

                TextEntry::make('crm_outcome')
                    ->label('CRM outcome')
                    ->badge()
                    // Stays visible when empty, unlike the provider fields:
                    // this one is a prompt to the person reading the page, not
                    // a fact that is merely missing.
                    ->placeholder('Not recorded'),

                TextEntry::make('provider_note')
                    ->label('Agent note, from the provider app')
                    ->visible(fn (Call $record): bool => filled($record->provider_note))
                    ->columnSpanFull(),

                TextEntry::make('crm_note')
                    ->label('CRM note')
                    ->placeholder('-')
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 1, 'sm' => 2]);
    }

    /**
     * Whether this provider gave us an agent-side outcome at all.
     */
    protected static function hasProviderOutcome(Call $call): bool
    {
        return filled($call->provider_crm_status) || filled($call->provider_note);
    }

    protected static function transcriptSection(): Section
    {
        return Section::make('Transcript')
            ->icon('heroicon-o-document-text')
            ->collapsible()
            ->collapsed(fn (Call $record): bool => ! $record->hasTranscript())
            ->visible(fn (Call $record): bool => auth()->user()?->can('viewTranscript', $record) ?? false)
            ->schema([
                // Same reasoning as the AI section: when there is nothing to
                // read, the useful thing to put here is the way to get
                // something, not a sentence describing the absence.
                EmptyState::make(fn (Call $record): string => match (true) {
                    ! $record->recordings()->exists() => 'No recording for this call',
                    $record->transcription_status === TranscriptionStatus::Processing => 'Transcribing now',
                    $record->transcription_status === TranscriptionStatus::Failed => 'The last attempt failed',
                    default => 'Not transcribed yet',
                })
                    ->description(fn (Call $record): string => match (true) {
                        ! $record->recordings()->exists() => 'The provider published no audio for this call, so there is nothing to transcribe.',
                        $record->transcription_status === TranscriptionStatus::Processing => 'The text appears here once the worker finishes.',
                        $record->transcription_status === TranscriptionStatus::Failed => 'Try again below.',
                        default => 'Turn the recording into text you can read, search and analyse. If you just started one, it appears here once the worker picks it up.',
                    })
                    ->icon('heroicon-o-document-text')
                    ->footer([TranscribeCallAction::make('transcribeFromEmptyState')])
                    ->columnSpanFull()
                    ->visible(fn (Call $record): bool => ! $record->hasTranscript()),

                TextEntry::make('transcript')
                    ->hiddenLabel()
                    ->state(fn (Call $record): HtmlString => static::renderTranscript($record))
                    ->html()
                    ->columnSpanFull()
                    // The empty state above says it better; showing both would
                    // state the absence twice.
                    ->visible(fn (Call $record): bool => $record->hasTranscript()),
            ]);
    }

    protected static function analysisSection(): Section
    {
        return Section::make('AI analysis')
            ->icon('heroicon-o-sparkles')
            ->collapsible()
            // Always present, never hidden. A section that disappears when it
            // has nothing in it leaves no answer to "where would the analysis
            // show up?" — which is exactly the question someone asks after
            // switching the feature on and seeing nothing change.
            // The attribution belongs at the top of the section, not on one
            // field. Everything below is inference - purchase intent, outcome,
            // temperature - and each one is as capable of being wrong as the
            // booking flag that prompted this note.
            ->description(fn (Call $record): ?string => $record->currentAnalysis !== null
                ? $record->currentAnalysis->analysis_version . ' · ' . ($record->currentAnalysis->model ?? 'unknown model')
                    . ' · the model’s reading of the transcript, not a clinic record'
                : null)
            ->schema([
                // A sentence telling someone to go and find a menu is worse
                // than the button itself. The heading says what is true, the
                // description says why, and the action is right there - the
                // menu entries stay for people who are already looking there.
                EmptyState::make(fn (Call $record): string => match (true) {
                    ! $record->hasTranscript() => 'Nothing to analyse yet',
                    $record->analysis_status === CallAnalysisStatus::Processing => 'Analysing now',
                    $record->analysis_status === CallAnalysisStatus::Failed => 'The last attempt failed',
                    default => 'Not analysed yet',
                })
                    ->description(fn (Call $record): string => match (true) {
                        ! $record->hasTranscript() => 'Analysis reads the transcript, so the recording has to be transcribed first.',
                        $record->analysis_status === CallAnalysisStatus::Processing => 'The result appears here once the worker finishes.',
                        $record->analysis_status === CallAnalysisStatus::Failed => 'Try again below.',
                        // Pending is the column default, not evidence that
                        // anything was queued, so it must not claim a worker is
                        // on its way. Both readings are covered instead.
                        default => 'Read the transcript for intent, sentiment, objection and a suggested next step. If you just started one, it appears here once the worker picks it up.',
                    })
                    ->icon('heroicon-o-sparkles')
                    ->footer([AnalyseCallAction::make('analyseFromEmptyState')])
                    ->columnSpanFull()
                    ->visible(fn (Call $record): bool => $record->currentAnalysis === null),

                TextEntry::make('currentAnalysis.summary')->label('Summary')->columnSpanFull()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.customer_intent')->label('Intent')->badge()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.sentiment')->label('Sentiment')->badge()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.outcome')->label('Outcome')->badge()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.lead_temperature')->label('Temperature')->badge()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.purchase_intent')->label('Purchase intent')->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.urgency')->label('Urgency')->badge()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.objection')->label('Objection')->columnSpanFull()->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.treatment_interest')->label('Treatment interest')->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.product_interest')->label('Product interest')->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                // Deliberately not a tick. A boolean IconEntry renders the
                // model's inference in exactly the same green check the CRM
                // uses for facts it holds - so a guess reads as a record, and
                // "Appointment booked ✓" on a call where nobody booked anything
                // is the CRM stating something untrue. Words carry the doubt
                // that an icon cannot, and null gets its own answer instead of
                // being flattened into "no".
                TextEntry::make('appointment_booked_reading')
                    ->label('Appointment (per AI)')
                    ->badge()
                    ->state(fn (Call $record): string => match ($record->currentAnalysis?->appointment_booked) {
                        true => 'Booked on the call',
                        false => $record->currentAnalysis?->appointment_discussed === true
                            ? 'Discussed, not booked'
                            : 'Not booked',
                        default => 'Not stated',
                    })
                    ->color(fn (Call $record): string => match ($record->currentAnalysis?->appointment_booked) {
                        true => 'success',
                        false => 'gray',
                        default => 'gray',
                    })
                    ->helperText(fn (Call $record): ?string => $record->currentAnalysis?->appointment_booked === true
                        ? 'The model read the transcript this way. It is not a booking in the diary.'
                        : null)
                    ->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
                TextEntry::make('currentAnalysis.next_best_action')
                    ->label('Suggested next step')
                    ->columnSpanFull()
                    ->placeholder('-')->visible(fn (Call $record): bool => $record->currentAnalysis !== null),
            ])
            ->columns(['default' => 2, 'sm' => 3]);
    }

    protected static function participantsSection(): Section
    {
        return Section::make('Participants')
            ->icon('heroicon-o-users')
            ->schema([
                TextEntry::make('agent_name')
                    ->label('Agent')
                    ->badge()
                    ->placeholder('Unassigned'),

                TextEntry::make('assignedUser.name')
                    ->label('Assigned to')
                    ->placeholder('-'),

                TextEntry::make('employee_code')->label('Agent code')->placeholder('-'),

                TextEntry::make('employee_phone_normalized')
                    ->label('Agent number')
                    ->copyable()
                    ->placeholder('-'),

                TextEntry::make('virtual_number_normalized')
                    ->label('Clinic line rang (Exophone)')
                    ->copyable()
                    // Named explicitly because Exotel's Inbox uses "To" for the
                    // agent who answered, while its webhook uses "To" for this
                    // number - so an unqualified label here looks like a
                    // contradiction against their screen.
                    ->helperText('The number the caller dialled, not who answered it.')
                    ->placeholder('-'),

                TextEntry::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color('info')
                    ->placeholder('Unassigned'),
            ])
            ->columns(['default' => 2, 'sm' => 2]);
    }

    protected static function timingSection(): Section
    {
        return Section::make('Timing')
            ->icon('heroicon-o-clock')
            ->schema([
                TextEntry::make('started_at')
                    ->label('Started')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('-'),

                // Exotel sends neither an answer time nor a ring timer, so
                // both are worked out from the durations it does send. Marked
                // as such: a computed answer time is accurate to the second the
                // arithmetic allows and no better, and someone reconciling this
                // page against Exotel's own report should know why it is not
                // quoted there.
                TextEntry::make('answered_at')
                    ->label('Answered')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->helperText(fn (Call $record): ?string => $record->answered_at !== null
                        && blank($record->provider_answered_at_raw)
                            ? 'Worked out from the durations'
                            : null)
                    ->placeholder('Not answered'),

                TextEntry::make('ended_at')
                    ->label('Ended')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('-'),

                TextEntry::make('ring_duration_seconds')->label('Ring (s)')->placeholder('-'),
                TextEntry::make('talk_duration_seconds')->label('Talk (s)')->placeholder('-'),

                // Time held in a queue or IVR before a human. Exotel's webhook
                // does not separate it from ring time, and showing it as a dash
                // on every call taught people the field was broken rather than
                // inapplicable.
                TextEntry::make('wait_duration_seconds')
                    ->label('Wait (s)')
                    ->visible(fn (Call $record): bool => $record->wait_duration_seconds !== null),
                TextEntry::make('disposition')->label('Ended because')->badge()->color('gray')->placeholder('-'),
                TextEntry::make('timezone')->label('Provider timezone')->placeholder('-'),
            ])
            ->columns(['default' => 2, 'sm' => 2]);
    }

    protected static function followUpSection(): Section
    {
        return Section::make('Follow-up')
            ->icon('heroicon-o-flag')
            ->schema([
                IconEntry::make('follow_up_required')->label('Needed')->boolean(),

                TextEntry::make('follow_up_at')
                    ->label('Due')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('-'),

                TextEntry::make('follow_up_completed_at')
                    ->label('Completed')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('-'),

                TextEntry::make('provider_reminder_at')
                    ->label('Reminder set in the provider app')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('-'),
                    // ->columnSpanFull(),

                TextEntry::make('follow_up_reason')->label('Reason')->placeholder('-')->columnSpanFull(),
            ])
            ->columns(['default' => 2, 'sm' => 2]);
    }

    /**
     * Where the record came from, and the untouched payloads behind it.
     *
     * Collapsed by default: it is the section people open when something looks
     * wrong, not something to read past on the way to the transcript.
     */
    protected static function providerSection(): Section
    {
        return Section::make('Provider and sync')
            ->icon('heroicon-o-wrench-screwdriver')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextEntry::make('provider')->label('Provider')->badge(),
                TextEntry::make('source')->label('First seen via')->badge(),
                TextEntry::make('last_source')->label('Last updated via')->badge()->placeholder('-'),
                TextEntry::make('event_count')->label('Provider events'),

                TextEntry::make('provider_call_id')
                    ->label('Provider call ID')
                    ->copyable()
                    ->columnSpanFull(),

                TextEntry::make('first_seen_at')
                    ->label('First seen')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone()),

                TextEntry::make('last_event_at')
                    ->label('Last event')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('-'),

                TextEntry::make('provider_call_status')->label('Raw status')->placeholder('-'),
                TextEntry::make('provider_direction')->label('Raw direction')->placeholder('-'),

                TextEntry::make('last_error')
                    ->label('Last error')
                    ->color('danger')
                    ->placeholder('None')
                    ->columnSpanFull(),

                TextEntry::make('provider_data')
                    ->label('Normalised provider fields')
                    ->state(fn (Call $record): HtmlString => static::renderJson($record->provider_data))
                    ->html()
                    ->columnSpanFull()
                    ->visible(fn (): bool => auth()->user()?->can('viewRawPayload', Call::class) ?? false),

                TextEntry::make('payloads')
                    ->label('Raw payloads as received')
                    ->state(fn (Call $record): HtmlString => static::renderPayloads($record))
                    ->html()
                    ->columnSpanFull()
                    ->visible(fn (): bool => auth()->user()?->can('viewRawPayload', Call::class) ?? false),
            ])
            ->columns(['default' => 2, 'sm' => 2]);
    }

    /**
     * The audio, one player per recording.
     *
     * Native <audio controls> rather than the compact button used in the table:
     * on the detail page the job is reviewing a conversation, which means
     * scrubbing back over a sentence you missed — and a play/pause button
     * cannot do that. The browser's own transport gives seek, volume and speed
     * for free, and is keyboard accessible without any work from us.
     *
     * `preload="none"` matters: a call with three recordings would otherwise
     * start three downloads the moment the page opens, before anyone has asked
     * to hear any of them.
     *
     * The src is always the authorised streaming route, never the provider URL
     * — that URL can carry a signed token, and putting it in a browser leaks it
     * into history and referrer headers.
     */
    protected static function renderPlayers(Call $call): HtmlString
    {
        $recordings = $call->recordings()->orderBy('id')->get();

        if ($recordings->isEmpty()) {
            return new HtmlString(
                '<p class="sgc-rec-msg">No recording was published for this call.</p>'
            );
        }

        $blocks = $recordings->map(function (CallRecording $recording, int $index) use ($recordings): string {
            $meta = implode(' · ', array_filter([
                $recordings->count() > 1 ? 'Part ' . ($index + 1) : null,
                $recording->storage_status?->getLabel(),
                $recording->duration_seconds !== null
                    ? sprintf('%d:%02d', intdiv($recording->duration_seconds, 60), $recording->duration_seconds % 60)
                    : null,
                $recording->file_size !== null ? round($recording->file_size / 1024) . ' KB' : null,
                $recording->extension ? strtoupper($recording->extension) : null,
            ]));

            // fileExists() stats the disk. Acceptable on one record; it is what
            // stops the page offering a player for a file removed outside the
            // application.
            if (! $recording->fileExists()) {
                $reason = $recording->storage_status === RecordingStorageStatus::Purged
                    ? 'The audio was deleted under the retention policy.'
                    : 'The audio has not been downloaded yet.';

                return sprintf(
                    '<div class="sgc-rec-item">'
                    . '<p class="sgc-rec-msg">%s</p>'
                    . '%s'
                    . '<p class="sgc-rec-meta">%s</p>'
                    . '</div>',
                    e($reason),
                    $recording->error_message
                        ? '<p class="sgc-rec-err">' . e($recording->error_message) . '</p>'
                        : '',
                    e($meta),
                );
            }

            $url = route('calls.recordings.stream', ['recording' => $recording->getKey()]);

            return sprintf(
                '<div class="sgc-rec-item">'
                . '<audio class="sgc-rec-audio" controls preload="none" src="%s"'
                . ' onplay="window.sgCallAudio?.stop(); window.sgCallAudioSolo(this);"></audio>'
                . '<p class="sgc-rec-meta">%s</p>'
                . '</div>',
                e($url),
                e($meta),
            );
        })->implode('');

        return new HtmlString($blocks);
    }

    /**
     * The transcript, as segments when they exist and as prose when they do not.
     */
    protected static function renderTranscript(Call $call): HtmlString
    {
        $transcription = $call->currentTranscription;

        if ($transcription === null || ! $transcription->hasText()) {
            return new HtmlString(sprintf(
                '<p style="color:var(--gray-500);">%s</p>',
                e($call->transcription_status?->getLabel() === 'Completed'
                    ? 'No transcript text was produced.'
                    : 'Transcript: ' . ($call->transcription_status?->getLabel() ?? 'not available') . '.'),
            ));
        }

        // A degraded transcript that looks complete is worse than an obviously
        // failed one: it carries a plausible word count and reads as finished.
        // Anything the transcriber flagged is stated before the text, not after.
        $warnings = (array) (($transcription->transcript_json['_crm_warnings'] ?? []) ?: []);

        $notice = $warnings === [] ? '' : sprintf(
            '<p style="margin:0 0 .75rem;padding:.5rem .625rem;border-radius:.5rem;'
            . 'background:var(--warning-50);color:var(--warning-700);font-size:.75rem;line-height:1.5;">%s</p>',
            e(implode(' ', $warnings)),
        );

        $header = $notice . sprintf(
            '<p style="font-size:.75rem;color:var(--gray-400);margin-bottom:.75rem;">%s</p>',
            e(implode(' · ', array_filter([
                $transcription->provider,
                $transcription->model,
                $transcription->language,
                $transcription->word_count !== null ? $transcription->word_count . ' words' : null,
            ]))),
        );

        $segments = $transcription->segments()->get();

        if ($segments->isEmpty()) {
            return new HtmlString(
                $header . '<div style="white-space:pre-wrap;line-height:1.7;">' . e($transcription->transcript) . '</div>'
            );
        }

        $body = $segments->map(function ($segment): string {
            $colour = match ($segment->speaker_type) {
                TranscriptSpeakerType::Agent => 'var(--primary-600)',
                TranscriptSpeakerType::Customer => 'var(--success-600)',
                default => 'var(--gray-500)',
            };

            return sprintf(
                '<div style="display:flex;gap:.75rem;margin-bottom:.5rem;">'
                . '<span style="font-variant-numeric:tabular-nums;color:var(--gray-400);font-size:.75rem;min-width:3rem;">%s</span>'
                . '<span style="color:%s;font-size:.75rem;min-width:5rem;font-weight:600;">%s</span>'
                . '<span style="line-height:1.6;">%s</span>'
                . '</div>',
                e($segment->timestamp_label ?? ''),
                $colour,
                e($segment->speaker_type?->getLabel() ?? 'Unknown'),
                e($segment->text),
            );
        })->implode('');

        return new HtmlString($header . $body);
    }

    /**
     * What the AI concluded, when analysis has run.
     */
    protected static function renderPayloads(Call $call): HtmlString
    {
        $payloads = $call->payloads()->limit(20)->get();

        if ($payloads->isEmpty()) {
            return new HtmlString('<p style="color:var(--gray-500);">No payloads are archived for this call.</p>');
        }

        return new HtmlString($payloads->map(fn ($payload): string => sprintf(
            '<details style="margin-bottom:.75rem;"><summary style="cursor:pointer;font-size:.8125rem;">%s — %s (%s)</summary>%s</details>',
            e($payload->received_at?->timezone(app_timezone())->format(app_datetime_format()) ?? ''),
            e($payload->event_type ?: 'event'),
            e($payload->processing_status?->getLabel() ?? ''),
            static::renderJson($payload->payload)->toHtml(),
        ))->implode(''));
    }

    protected static function renderJson(mixed $data): HtmlString
    {
        if (blank($data)) {
            return new HtmlString('<p style="color:var(--gray-500);">Nothing recorded.</p>');
        }

        return new HtmlString(sprintf(
            '<pre style="overflow-x:auto;font-size:.75rem;line-height:1.5;padding:.75rem;border-radius:.5rem;background:var(--gray-50);">%s</pre>',
            e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
        ));
    }
}
