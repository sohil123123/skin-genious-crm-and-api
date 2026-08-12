<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\MetaPage;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * App-level credentials for the Meta Lead Ads integration.
 *
 * Only the three values that belong to the Meta app live here. Per-Page access
 * tokens belong to their Page and are managed on the Meta Pages screen, because
 * a token grants access to one Page's leads and a single shared token would
 * make the multi-Page case impossible to reason about.
 *
 * Follows the same settings-table pattern as WhatsAppSettings so there is one
 * way credentials are stored in this application, not two.
 */
class MetaLeadSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $title = 'Meta Lead Settings';

    protected static ?string $navigationLabel = 'Meta Lead Settings';

    protected static ?int $navigationSort = 36;

    protected string $view = 'filament.pages.meta-lead-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * @var array<int, string>
     */
    protected const KEYS = [
        'meta_app_id',
        'meta_app_secret',
        'meta_verify_token',
    ];

    public function mount(): void
    {
        $formData = [];

        foreach (self::KEYS as $key) {
            $formData[$key] = Setting::getValue($key, '');
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Meta App Credentials')
                    ->description('From the Meta App Dashboard. These identify the app, not any single Page.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('meta_app_id')
                                ->label('App ID')
                                ->autocomplete(false)
                                ->placeholder('Enter Meta App ID'),

                            TextInput::make('meta_app_secret')
                                ->label('App Secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false)
                                ->placeholder('Enter Meta App Secret')
                                // Every webhook POST is rejected without this,
                                // so it is worth saying why plainly.
                                ->helperText('Used to verify the signature on every incoming webhook. Leads are rejected while this is empty.'),

                            TextInput::make('meta_verify_token')
                                ->label('Webhook Verify Token')
                                ->autocomplete(false)
                                ->placeholder('Any hard-to-guess string')
                                ->columnSpanFull()
                                ->helperText('Invent a value here, then enter the same one in the Meta App Dashboard when saving the callback URL.'),
                        ]),
                    ]),

                Section::make('Webhook')
                    ->description('Give this callback URL to Meta, subscribed to the leadgen field.')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Text::make(new HtmlString(
                            '<code style="word-break:break-all;">' . e(url('/api/webhooks/meta')) . '</code>'
                        )),

                        Text::make(fn (): HtmlString => static::connectionSummary()),
                    ]),
            ]);
    }

    /**
     * A short readiness summary, so misconfiguration is visible here rather
     * than discovered when leads quietly fail to arrive.
     */
    protected static function connectionSummary(): HtmlString
    {
        $pages = MetaPage::query()->active()->count();
        $subscribed = MetaPage::query()->active()->whereNotNull('subscribed_at')->count();

        $lines = [];

        $lines[] = filled(Setting::getValue('meta_app_secret'))
            ? '✅ App secret saved — webhook signatures can be verified.'
            : '⚠️ No app secret saved — incoming webhooks are being rejected.';

        $lines[] = filled(Setting::getValue('meta_verify_token'))
            ? '✅ Verify token saved.'
            : '⚠️ No verify token saved — Meta cannot complete the subscription handshake.';

        $lines[] = $pages > 0
            ? sprintf(
                '✅ %d active %s connected, %d subscribed to leadgen.',
                $pages,
                Str::plural('Page', $pages),
                $subscribed,
            )
            : '⚠️ No active Meta Pages connected — leads have no clinic to be filed against.';

        return new HtmlString(
            '<div style="font-size:.8125rem; line-height:1.7;">' . implode('<br>', $lines) . '</div>'
        );
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(function (): void {
                    $data = $this->form->getState();

                    foreach (self::KEYS as $key) {
                        if (! array_key_exists($key, $data)) {
                            continue;
                        }

                        Setting::setValue($key, $data[$key] ?? '');
                        Setting::where('key', $key)->update(['group' => 'meta_leads']);
                    }

                    Notification::make()
                        ->title('Settings saved')
                        ->body('Meta Lead Ads credentials updated.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
