<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Actions\Action;

class WhatsAppSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Others';

    protected static ?string $title = 'WhatsApp Settings';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.whatsapp-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $keys = [
            'whatsapp_app_id',
            'whatsapp_app_secret',
            'whatsapp_business_account_id',
            'whatsapp_phone_number_id',
            'whatsapp_access_token',
            'whatsapp_webhook_verify_token',
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = Setting::getValue($key, '');
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Meta App Credentials')
                    ->description('Configuration for WhatsApp Business Cloud API.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('whatsapp_app_id')
                                ->label('App ID')
                                ->required()
                                ->placeholder('Enter Meta App ID'),

                            TextInput::make('whatsapp_app_secret')
                                ->label('App Secret')
                                ->required()
                                ->password()
                                ->revealable()
                                ->placeholder('Enter Meta App Secret'),

                            TextInput::make('whatsapp_business_account_id')
                                ->label('WhatsApp Business Account ID')
                                ->required()
                                ->placeholder('Enter Business Account ID'),

                            TextInput::make('whatsapp_phone_number_id')
                                ->label('Phone Number ID')
                                ->required()
                                ->placeholder('Enter Phone Number ID'),

                            TextInput::make('whatsapp_access_token')
                                ->label('Permanent Access Token')
                                ->required()
                                ->password()
                                ->revealable()
                                ->placeholder('Enter Access Token')
                                ->columnSpanFull(),

                            TextInput::make('whatsapp_webhook_verify_token')
                                ->label('Webhook Verify Token')
                                ->required()
                                ->placeholder('Custom string for webhook verification')
                                ->helperText('This token will be used to verify the webhook URL on Meta.')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(function () {
                    $data = $this->form->getState();

                    foreach ($data as $key => $value) {
                        Setting::setValue($key, $value ?? '');

                        // Also update group and description
                        Setting::where('key', $key)->update([
                            'group' => 'whatsapp',
                        ]);
                    }

                    Notification::make()
                        ->title('Settings Saved ✅')
                        ->body('WhatsApp configuration has been updated successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
