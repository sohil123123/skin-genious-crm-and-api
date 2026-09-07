<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Http\Controllers\Api\Webhooks\CallyzerWebhookController;
use App\Http\Controllers\Api\Webhooks\ExotelCallWebhookController;
use App\Models\Clinic;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Credentials and behaviour for both call integrations.
 *
 * Stored in the settings table rather than in .env, matching how the Meta and
 * WhatsApp integrations already work — so credentials can be rotated without a
 * deploy, and there is one way credentials are stored in this application
 * rather than three.
 *
 * The webhook URLs are generated here with their secrets already embedded,
 * rather than being written down somewhere for an administrator to paste
 * together by hand. That is not a convenience: a secret typed into a provider
 * dashboard by hand is a secret that will one day not match the one this
 * application checks, and the symptom is silently missing calls.
 */
class CallSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Calls';

    protected static ?string $title = 'Call Settings';

    protected static ?string $navigationLabel = 'Call Settings';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.call-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * @var array<int, string>
     */
    protected const KEYS = [
        'exotel_enabled',
        'exotel_account_sid',
        'exotel_api_key',
        'exotel_api_token',
        'exotel_subdomain',
        'exotel_webhook_secret',
        'callyzer_enabled',
        'callyzer_sync_enabled',
        'callyzer_base_url',
        'callyzer_api_token',
        'callyzer_webhook_secret',
        'call_recording_disk',
        'call_recording_download_enabled',
        'call_transcription_enabled',
        'call_transcription_driver',
        'call_transcription_model',
        'call_transcription_language',
        'call_transcription_prompt',
        'call_transcription_temperature',
        'call_transcription_api_key',
        'call_analysis_enabled',
        'call_analysis_driver',
        'call_analysis_model',
        'call_analysis_min_words',
        'call_exotel_number_map',
    ];

    public static function canAccess(): bool
    {
        // Credentials, not configuration. Anyone who can read this page can
        // read the tokens that grant access to every recorded conversation.
        return auth()->user()?->hasRole(config('project.roles.super_admin')) ?? false;
    }

    public function mount(): void
    {
        $formData = [];

        foreach (self::KEYS as $key) {
            $formData[$key] = Setting::getValue($key, '');
        }

        // Stored as JSON because it is a handful of pairs that change about
        // once a year — which does not earn a table.
        $map = json_decode((string) ($formData['call_exotel_number_map'] ?: '{}'), true);
        $formData['call_exotel_number_map'] = is_array($map) ? $map : [];

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Exotel — incoming calls')
                    ->description('Credentials from your Exotel dashboard, and the secret that protects the webhook.')
                    ->icon('heroicon-o-phone-arrow-down-left')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('exotel_enabled')
                                ->label('Accept Exotel calls')
                                ->columnSpanFull(),

                            TextInput::make('exotel_account_sid')
                                ->label('Account SID')
                                ->autocomplete(false)
                                // Shaped like the real value rather than
                                // "Enter account SID": someone pasting from the
                                // Exotel dashboard can see at a glance whether
                                // they have grabbed the right field.
                                ->placeholder('e.g. skingenious1'),

                            TextInput::make('exotel_subdomain')
                                ->label('API subdomain')
                                ->placeholder(config('calls.exotel.subdomain'))
                                ->helperText('api.exotel.com or api.in.exotel.com, depending on your account region.'),

                            TextInput::make('exotel_api_key')
                                ->label('API key')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->placeholder('Paste the API key from Exotel'),

                            TextInput::make('exotel_api_token')
                                ->label('API token')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->placeholder('Paste the API token from Exotel')
                                // Not optional in practice: Exotel's recording
                                // URLs sit behind these same credentials, so an
                                // empty token means every download returns 401.
                                ->helperText('Also used to download recordings — without it, no audio can be fetched.'),

                            TextInput::make('exotel_webhook_secret')
                                ->label('Webhook secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->columnSpanFull()
                                // Points at the generator rather than at a
                                // format: a secret someone invents by hand is
                                // usually a weak one.
                                ->placeholder('Press the ✨ button to generate one')
                                ->helperText('Exotel signs nothing, so this shared secret is the only thing protecting the endpoint. Calls are rejected while it is empty.')
                                ->suffixAction(
                                    Action::make('generateExotelSecret')
                                        ->icon('heroicon-o-sparkles')
                                        ->tooltip('Generate a strong secret')
                                        ->action(fn (callable $set) => $set('exotel_webhook_secret', Str::random(40)))
                                ),
                        ]),

                        // Two URLs because the two Passthrus do different jobs,
                        // and pasting one where the other belongs breaks either
                        // call routing or the popup.
                        Text::make(fn (): HtmlString => static::urlBlock(
                            '1. Call Start Passthru — keep this one Sync. Powers the screen pop and the "existing / new" routing.',
                            ExotelCallWebhookController::screenPopUrl(),
                        )),

                        Text::make(fn (): HtmlString => static::urlBlock(
                            '2. After-call Passthru — tick Async. Carries the duration, outcome and recording.',
                            ExotelCallWebhookController::webhookUrl(),
                        )),
                    ]),

                Section::make('Callyzer — outgoing calls')
                    ->description('API token from Connectors → API & Webhook in your Callyzer dashboard.')
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('callyzer_enabled')->label('Accept Callyzer calls'),

                            Toggle::make('callyzer_sync_enabled')
                                ->label('Pull call history on a schedule')
                                ->helperText('Catches calls whose webhook never arrived, and notes edited after the fact.'),

                            TextInput::make('callyzer_base_url')
                                ->label('API base URL')
                                ->placeholder(config('calls.callyzer.base_url'))
                                ->url()
                                ->columnSpanFull()
                                ->helperText('Confirm this against your own dashboard — Callyzer has moved it between versions.'),

                            TextInput::make('callyzer_api_token')
                                ->label('API token')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->placeholder('Generated under Connectors → API & Webhook'),

                            TextInput::make('callyzer_webhook_secret')
                                ->label('Webhook secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->placeholder('Press the ✨ button to generate one')
                                ->helperText('Webhooks are rejected while this is empty.')
                                ->suffixAction(
                                    Action::make('generateCallyzerSecret')
                                        ->icon('heroicon-o-sparkles')
                                        ->tooltip('Generate a strong secret')
                                        ->action(fn (callable $set) => $set('callyzer_webhook_secret', Str::random(40)))
                                ),
                        ]),

                        Text::make(fn (): HtmlString => static::urlBlock(
                            'Webhook URL — paste this into Callyzer',
                            CallyzerWebhookController::webhookUrl(),
                        )),
                    ]),

                Section::make('Clinic numbers')
                    ->description('Which clinic each Exophone belongs to. Without this, calls to a shared number cannot be filed to a branch.')
                    ->icon('heroicon-o-building-office')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('call_exotel_number_map')
                            ->label('')
                            ->keyLabel('Exophone number')
                            ->valueLabel('Clinic ID')
                            // A worked example on both sides: the pairing is
                            // not obvious from two column headings alone.
                            ->keyPlaceholder('e.g. 08047122334')
                            ->valuePlaceholder('e.g. 1')
                            ->addActionLabel('Add a number')
                            ->helperText(fn (): string => 'Clinic IDs: ' . Clinic::query()
                                ->pluck('name', 'id')
                                ->map(fn (?string $name, $id): string => $id . ' = ' . ($name ?: 'unnamed'))
                                ->implode(', ')),
                    ]),

                Section::make('Recordings')
                    ->icon('heroicon-o-microphone')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('call_recording_download_enabled')
                                ->label('Download recordings')
                                ->helperText('Provider recording links expire. Without this, call audio is lost when they do.'),

                            Select::make('call_recording_disk')
                                ->label('Storage disk')
                                ->options(fn (): array => collect(array_keys(config('filesystems.disks', [])))
                                    ->mapWithKeys(fn (string $disk): array => [$disk => $disk])
                                    ->all())
                                ->placeholder(config('calls.recording.disk'))
                                ->helperText('Use a private disk. A public one makes every recorded conversation reachable by URL.'),
                        ]),
                    ]),

                Section::make('Transcription and AI')
                    ->icon('heroicon-o-sparkles')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('call_transcription_enabled')->label('Transcribe recordings'),
                            Toggle::make('call_analysis_enabled')
                                ->label('Analyse transcripts')
                                ->helperText('Reads each transcript and extracts intent, sentiment, objection and a next step.'),

                            Select::make('call_analysis_driver')
                                ->label('Analysis provider')
                                ->options([
                                    'null' => 'None - analysis off',
                                    'openai' => 'OpenAI',
                                ])
                                ->default('null')
                                ->native(false)
                                ->selectablePlaceholder(false)
                                ->helperText('Reuses the transcription API key unless you set one below.'),

                            Select::make('call_analysis_model')
                                ->label('Analysis model')
                                ->options([
                                    'gpt-4o-mini' => 'gpt-4o-mini - cheap, good enough for most calls',
                                    'gpt-4o' => 'gpt-4o - better on long or subtle calls',
                                ])
                                ->default('gpt-4o-mini')
                                ->native(false)
                                ->selectablePlaceholder(false)
                                // Analysis runs once per call rather than per
                                // minute of audio, so the cost difference is
                                // small in absolute terms.
                                ->helperText('Runs once per transcribed call.'),

                            TextInput::make('call_analysis_min_words')
                                ->label('Shortest transcript to analyse')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(500)
                                ->default((string) config('calls.analysis.min_words', 15))
                                ->suffix('words')
                                // Named in words rather than seconds because
                                // that is what the rule actually counts, and
                                // because a talkative ten seconds and a silent
                                // minute are not the same call.
                                ->helperText('Anything shorter is skipped. "Hello? Wrong number." is not worth a model call. Counted in words, so it means the same in Hindi as in English.'),

                            // Without this the driver could only be changed in
                            // .env, so turning the toggle on left the null
                            // driver bound and nothing was transcribed.
                            Select::make('call_transcription_driver')
                                ->label('Transcription provider')
                                ->options([
                                    'null' => 'None - transcription off',
                                    'openai' => 'OpenAI (Whisper)',
                                ])
                                ->default('null')
                                ->native(false)
                                ->selectablePlaceholder(false)
                                ->helperText('Pick a provider, or nothing is transcribed however the toggle is set.'),

                            TextInput::make('call_transcription_api_key')
                                ->label('Transcription API key')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->placeholder('Paste the speech provider API key'),

                            Select::make('call_transcription_model')
                                ->label('Model')
                                ->options([
                                    'whisper-1' => 'whisper-1 - timestamped segments',
                                    'gpt-4o-transcribe' => 'gpt-4o-transcribe - more accurate, no timestamps',
                                    'gpt-4o-mini-transcribe' => 'gpt-4o-mini-transcribe - cheaper, no timestamps',
                                ])
                                ->default('whisper-1')
                                ->native(false)
                                ->selectablePlaceholder(false)
                                // A real trade-off rather than a ranking: only
                                // whisper-1 returns segment timings, so the
                                // better transcript costs you the clickable
                                // timeline.
                                ->helperText('The gpt-4o models handle mixed Hindi and English better, but return one block of text with no timeline.'),

                            Select::make('call_transcription_language')
                                ->label('Language')
                                ->options([
                                    'hi' => 'Hindi',
                                    'gu' => 'Gujarati',
                                    'en' => 'English',
                                    'mr' => 'Marathi',
                                    'ur' => 'Urdu',
                                ])
                                ->native(false)
                                ->placeholder('Auto-detect')
                                // Auto-detect is not the safe default it looks
                                // like. Hindi and Urdu are the same spoken
                                // language in two scripts, so a Hindi call
                                // detected as Urdu comes back in Nastaliq —
                                // readable by nobody at the front desk.
                                ->helperText('Set this if calls are mostly one language. Auto-detect can return Hindi speech written in Urdu script.'),

                            Textarea::make('call_transcription_prompt')
                                ->label('Style prompt')
                                ->rows(2)
                                ->columnSpanFull()
                                ->placeholder('e.g. यह एक स्किन क्लिनिक की कॉल है। हिंदी और अंग्रेज़ी मिलाकर बात होती है। HydraFacial, PRP, laser.')
                                // The most effective knob this endpoint offers,
                                // and the one nobody knows about: a couple of
                                // lines in the script you want anchors the
                                // output to it, and teaches the model treatment
                                // names it would otherwise mangle.
                                ->helperText('A sentence or two in the script you want back. Also the place to list treatment names the model keeps getting wrong.'),
                        ]),
                    ]),
            ]);
    }

    protected static function urlBlock(string $label, string $url): HtmlString
    {
        return new HtmlString(sprintf(
            '<div style="font-size:.8125rem;"><p style="margin-bottom:.25rem;color:var(--gray-500);">%s</p>'
            . '<code style="word-break:break-all;">%s</code></div>',
            e($label),
            e($url),
        ));
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::KEYS as $key) {
            $value = $data[$key] ?? null;

            // The number map is an array in the form and JSON in the column.
            if ($key === 'call_exotel_number_map') {
                $value = json_encode(array_filter((array) $value)) ?: '{}';
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            Setting::setValue($key, $value);
        }

        // The settings cache is memoised statically and outlives a request, so
        // a queue worker would otherwise keep serving the old credentials.
        Setting::flushRuntimeCache();

        Notification::make()
            ->success()
            ->title('Call settings saved')
            ->body('New credentials take effect on the next webhook or sync.')
            ->send();
    }
}
