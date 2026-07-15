<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class WhatsAppSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'WhatsApp Settings';

    protected static ?int $navigationSort = 29;

    protected string $view = 'filament.pages.whatsapp-settings';

    public ?array $data = [];
    public ?string $currentProfilePictureUrl = null;

    public function mount(): void
    {
        $keys = [
            'whatsapp_app_id',
            'whatsapp_app_secret',
            'whatsapp_business_account_id',
            'whatsapp_phone_number_id',
            'whatsapp_access_token',
            'whatsapp_webhook_verify_token',
            'whatsapp_override_callback_url',
            'whatsapp_appointment_template_name',
            'openai_api_key',
            'openai_model',
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = Setting::getValue($key, '');
        }

        // Try fetching business profile details from Meta API
        try {
            $whatsAppService = app(WhatsAppService::class);
            $profile = $whatsAppService->getBusinessProfile();
            if ($profile) {
                $formData['business_about'] = $profile['about'] ?? '';
                $formData['business_address'] = $profile['address'] ?? '';
                $formData['business_description'] = $profile['description'] ?? '';
                $formData['business_email'] = $profile['email'] ?? '';
                $formData['business_websites'] = $profile['websites'] ?? [];
                $formData['business_vertical'] = $profile['vertical'] ?? '';
                $this->currentProfilePictureUrl = $profile['profile_picture_url'] ?? null;
            }

            // Sync webhook callback URL from Meta
            $sub = $whatsAppService->getAppSubscription();
            if ($sub && !empty($sub['callback_url'])) {
                $formData['whatsapp_override_callback_url'] = $sub['callback_url'];
            }
        } catch (\Exception $e) {
            Log::warning('Could not load WhatsApp details during settings mount: ' . $e->getMessage());
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

                            TextInput::make('whatsapp_override_callback_url')
                                ->label('Override Callback URL')
                                ->placeholder('e.g. ' . url('/api/whatsapp/webhook'))
                                ->helperText(fn() => 'Default: ' . url('/api/whatsapp/webhook'))
                                ->url(),

                            TextInput::make('whatsapp_webhook_verify_token')
                                ->label('Webhook Verify Token')
                                ->required()
                                ->placeholder('Custom string for webhook verification')
                                ->helperText('This token will be used to verify the webhook URL on Meta.'),

                            Select::make('whatsapp_appointment_template_name')
                                ->label('Appointment Confirmation Template')
                                ->options(fn() => WhatsAppTemplate::pluck('name', 'name')->toArray())
                                ->searchable()
                                ->placeholder('Select template name')
                                ->helperText('This template will be sent automatically when a new appointment is created.')
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('WhatsApp Business Profile')
                    ->description('Public details displayed to clients on WhatsApp.')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('current_profile_photo')
                            ->label('Current Profile Photo')
                            ->content(function () {
                                if ($this->currentProfilePictureUrl) {
                                    return new HtmlString('<div style="display: flex; align-items: center; height: 100%; padding-top: 0.25rem;"><img src="' . e($this->currentProfilePictureUrl) . '" alt="Profile Picture" style="width: 80px; height: 80px; border-radius: 9999px; object-fit: cover; border: 2px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" /></div>');
                                }
                                return new HtmlString('<div style="display: flex; align-items: center; height: 100%; padding-top: 0.25rem; color: #6b7280; font-size: 0.875rem; font-style: italic;">No profile photo loaded from Meta.</div>');
                            }),

                        FileUpload::make('profile_photo')
                            ->label('Update Profile Photo')
                            ->image()
                            ->disk('public')
                            ->directory('whatsapp-profile')
                            ->helperText('Will be uploaded to Meta as the business profile photo.'),

                        TextInput::make('business_about')
                            ->label('About / Status text')
                            ->maxLength(139)
                            ->placeholder('e.g. Skin & Hair Clinic'),

                        TextInput::make('business_email')
                            ->label('Business Email')
                            ->email()
                            ->placeholder('e.g. contact@skingenious.com'),

                        TextInput::make('business_address')
                            ->label('Business Address')
                            ->placeholder('Physical location'),

                        Select::make('business_vertical')
                            ->label('Category / Vertical')
                            ->options([
                                'BEAUTY' => 'Beauty, Cosmetics & Personal Care',
                                'HEALTH' => 'Health & Medical',
                                'PROF_SERVICES' => 'Professional Services',
                                'RETAIL' => 'Retail / Shopping',
                                'EDUCATION' => 'Education',
                                'OTHER' => 'Other',
                            ]),

                        Textarea::make('business_description')
                            ->label('Business Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Repeater::make('business_websites')
                            ->label('Websites')
                            ->simple(TextInput::make('website')->url())
                            ->maxItems(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('OpenAI AI Settings')
                    ->description('Configuration for WhatsApp chatbot and automated replies.')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('openai_api_key')
                                ->label('OpenAI API Key')
                                ->password()
                                ->revealable()
                                ->placeholder('sk-...'),

                            Select::make('openai_model')
                                ->label('OpenAI Model')
                                ->options([
                                    'gpt-4o-mini' => 'gpt-4o-mini (Recommended)',
                                    'gpt-4o' => 'gpt-4o (High Quality)',
                                    'gpt-3.5-turbo' => 'gpt-3.5-turbo',
                                ])
                                ->default('gpt-4o-mini'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromMeta')
                ->label('Sync Profile from Meta')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    try {
                        $whatsAppService = app(WhatsAppService::class);
                        $profile = $whatsAppService->getBusinessProfile();
                        $sub = $whatsAppService->getAppSubscription();

                        if ($profile) {
                            $formData = [
                                'business_about' => $profile['about'] ?? '',
                                'business_address' => $profile['address'] ?? '',
                                'business_description' => $profile['description'] ?? '',
                                'business_email' => $profile['email'] ?? '',
                                'business_websites' => $profile['websites'] ?? [],
                                'business_vertical' => $profile['vertical'] ?? '',
                            ];

                            if ($sub && !empty($sub['callback_url'])) {
                                $formData['whatsapp_override_callback_url'] = $sub['callback_url'];
                            }

                            $this->form->fill(array_merge($this->form->getState(), $formData));
                            $this->currentProfilePictureUrl = $profile['profile_picture_url'] ?? null;

                            Notification::make()
                                ->title('Profile Synced ✅')
                                ->body('Business profile loaded from Meta successfully. Click "Save Settings" to save.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync Failed')
                                ->body('Could not retrieve business profile from Meta. Please check your credentials.')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('save')
                ->label('Save Settings')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(function () {
                    $data = $this->form->getState();

                    // Save local credential settings
                    $localKeys = [
                        'whatsapp_app_id',
                        'whatsapp_app_secret',
                        'whatsapp_business_account_id',
                        'whatsapp_phone_number_id',
                        'whatsapp_access_token',
                        'whatsapp_webhook_verify_token',
                        'whatsapp_override_callback_url',
                        'whatsapp_appointment_template_name',
                        'openai_api_key',
                        'openai_model',
                    ];

                    foreach ($localKeys as $key) {
                        if (isset($data[$key])) {
                            Setting::setValue($key, $data[$key] ?? '');
                            Setting::where('key', $key)->update([
                                'group' => 'whatsapp',
                            ]);
                        }
                    }

                    // Update Meta Business Profile
                    try {
                        $whatsAppService = app(WhatsAppService::class);

                        $profileData = [
                            'about' => $data['business_about'] ?? '',
                            'address' => $data['business_address'] ?? '',
                            'description' => $data['business_description'] ?? '',
                            'email' => $data['business_email'] ?? '',
                            'websites' => $data['business_websites'] ?? [],
                            'vertical' => $data['business_vertical'] ?? 'HEALTH',
                        ];

                        $profileResult = $whatsAppService->updateBusinessProfile($profileData);

                        if (!$profileResult['success']) {
                            Log::warning('WhatsApp Business Profile Update Error: ' . ($profileResult['error'] ?? 'Unknown error'));
                        }

                        // Upload profile photo if set
                        if (!empty($data['profile_photo'])) {
                            $photoPath = storage_path('app/public/' . $data['profile_photo']);
                            $photoResult = $whatsAppService->updateBusinessProfilePhoto($photoPath);

                            if (!$photoResult['success']) {
                                Log::warning('WhatsApp Business Profile Photo Update Error: ' . ($photoResult['error'] ?? 'Unknown error'));
                            } else {
                                // Clear upload state on success
                                $this->form->fill(array_merge($this->form->getState(), ['profile_photo' => null]));
                            }
                        }

                        // Update Webhook Callback URL on Meta via background command to avoid deadlocks on single-threaded local servers
                        $callbackUrl = $data['whatsapp_override_callback_url'] ?: url('/api/whatsapp/webhook');
                        $verifyToken = $data['whatsapp_webhook_verify_token'] ?? '';

                        if ($callbackUrl && $verifyToken) {
                            $artisanPath = base_path('artisan');
                            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                                pclose(popen("start /B php \"" . $artisanPath . "\" whatsapp:sync-webhook > NUL 2>&1", "r"));
                            } else {
                                exec("php \"" . $artisanPath . "\" whatsapp:sync-webhook > /dev/null 2>&1 &");
                            }
                        }

                        // Refresh profile picture URL
                        $profile = $whatsAppService->getBusinessProfile();
                        if ($profile) {
                            $this->currentProfilePictureUrl = $profile['profile_picture_url'] ?? null;
                        }

                    } catch (\Exception $e) {
                        Log::error('Error updating Meta business profile: ' . $e->getMessage());
                    }

                    Notification::make()
                        ->title('Settings Saved ✅')
                        ->body('WhatsApp credentials and Business Profile updated successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
