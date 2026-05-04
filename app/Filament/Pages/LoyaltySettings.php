<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use UnitEnum;
use BackedEnum;

class LoyaltySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected string $view = 'filament.pages.loyalty-settings';

    protected static string | UnitEnum | null $navigationGroup = 'Others';

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 40;

    public ?array $data = [];

    public function mount(): void
    {
        $formData = [];

        // Fill each setting: setting_{id} => value, desc_{id} => description
        $allSettings = Setting::all();
        foreach ($allSettings as $setting) {
            $formData["setting_{$setting->id}"] = $setting->value;
            $formData["desc_{$setting->id}"] = $setting->description;
        }

        // Empty repeater for new settings
        $formData['new_settings'] = [];

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        // ─── Dynamic Sections per Group ──────────────────────
        $allSettings = Setting::all();
        $grouped = $allSettings->groupBy(fn ($s) => $s->group ?: 'general');

        foreach ($grouped as $groupName => $settings) {
            $groupLabel = ucwords(str_replace(['_', '-'], ' ', $groupName));

            // Build individual fields for each setting in this group
            $fields = [];
            foreach ($settings as $setting) {
                $label = ucwords(str_replace(['_', '-'], ' ', $setting->key));

                $fields[] = \Filament\Schemas\Components\Group::make([
                    TextInput::make("setting_{$setting->id}")
                        ->label($label)
                        ->required()
                        ->prefixIcon($this->getFieldIcon($setting->key))
                        ->suffixAction(
                            Action::make('delete')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->action(fn () => $this->deleteSetting($setting->id))
                        ),
                    TextInput::make("desc_{$setting->id}")
                        ->label('Description')
                        ->placeholder('No description')
                        ->extraInputAttributes(['style' => 'font-size: 0.75rem; opacity: 0.8;'])
                        ->hiddenLabel(),
                ])->columnSpan(1);
            }

            // Arrange in a 3-column grid
            $sections[] = Section::make($groupLabel)
                ->icon($this->getGroupIcon($groupName))
                ->description("Manage \"{$groupLabel}\" settings ({$settings->count()} items)")
                ->collapsible()
                ->schema([
                    Grid::make(3)->schema($fields),
                ]);
        }

        // ─── Add New Setting Section ─────────────────────────
        // Build group options for the dropdown
        $groupOptions = $grouped->keys()
            ->map(fn ($g) => $g ?: 'general')
            ->unique()
            ->mapWithKeys(fn ($g) => [$g => ucwords(str_replace(['_', '-'], ' ', $g))])
            ->toArray();
        $groupOptions['__new__'] = '➕ Create New Group';

        $sections[] = Section::make('Add New Settings')
            ->icon('heroicon-o-plus-circle')
            ->description('Add new key-value settings. They will appear in their group section after saving.')
            ->collapsible()
            ->collapsed()
            ->schema([
                Repeater::make('new_settings')
                    ->label('')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key')
                                ->label('Key')
                                ->placeholder('e.g. app_name')
                                ->required()
                                ->prefixIcon('heroicon-o-key'),

                            TextInput::make('value')
                                ->label('Value')
                                ->placeholder('e.g. Skin Genious')
                                ->required()
                                ->prefixIcon('heroicon-o-tag'),
                        ]),
                        Grid::make(3)->schema([
                            Select::make('group')
                                ->label('Group')
                                ->options($groupOptions)
                                ->required()
                                ->live()
                                ->prefixIcon('heroicon-o-folder')
                                ->placeholder('Select a group'),

                            TextInput::make('new_group_name')
                                ->label('New Group Name')
                                ->placeholder('e.g. notifications')
                                ->required(fn (Get $get) => $get('group') === '__new__')
                                ->visible(fn (Get $get) => $get('group') === '__new__')
                                ->prefixIcon('heroicon-o-plus'),

                            TextInput::make('description')
                                ->label('Description')
                                ->placeholder('Short description')
                                ->prefixIcon('heroicon-o-document-text'),
                        ]),
                    ])
                    ->addActionLabel('+ Add New Setting')
                    ->defaultItems(0)
                    ->reorderable(false),
            ]);

        return $schema
            ->schema($sections)
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

                    // 1. Update existing settings
                    foreach ($data as $fieldKey => $value) {
                        if (Str::startsWith($fieldKey, 'setting_')) {
                            $settingId = (int) Str::after($fieldKey, 'setting_');
                            $setting = Setting::find($settingId);

                            if ($setting) {
                                $setting->update([
                                    'value' => $value,
                                    'description' => $data["desc_{$settingId}"] ?? $setting->description,
                                ]);
                            }
                        }
                    }

                    // 2. Save new settings
                    $newSettings = $data['new_settings'] ?? [];
                    foreach ($newSettings as $setting) {
                        if (!empty($setting['key'])) {
                            Setting::updateOrCreate(
                                ['key' => $setting['key']],
                                [
                                    'value'       => $setting['value'] ?? '',
                                    'group'       => $setting['group'] ?? 'general',
                                    'description' => $setting['description'] ?? null,
                                ]
                            );
                        }
                    }

                    // 3. Clear any list-based cache
                    Cache::forget('settings_all');

                    Notification::make()
                        ->title('Settings Saved ✅')
                        ->body('All settings have been updated successfully.')
                        ->success()
                        ->send();

                    // Redirect to refresh dynamic sections
                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function deleteSetting(int $id): void
    {
        $setting = Setting::find($id);

        if ($setting) {
            $setting->delete();

            Notification::make()
                ->title('Setting Deleted Successfully')
                ->success()
                ->send();

            $this->redirect(static::getUrl());
        }
    }

    /**
     * Map group names to appropriate section icons.
     */
    protected function getGroupIcon(string $group): string
    {
        return match (strtolower($group)) {
            'general'       => 'heroicon-o-cog-6-tooth',
            'loyalty'       => 'heroicon-o-gift',
            'notification', 'notifications' => 'heroicon-o-bell',
            'sms'           => 'heroicon-o-device-phone-mobile',
            'email'         => 'heroicon-o-envelope',
            'payment'       => 'heroicon-o-banknotes',
            'invoice'       => 'heroicon-o-document-text',
            'booking'       => 'heroicon-o-calendar',
            'clinic'        => 'heroicon-o-building-office',
            'api'           => 'heroicon-o-globe-alt',
            'security'      => 'heroicon-o-shield-check',
            default         => 'heroicon-o-adjustments-horizontal',
        };
    }

    /**
     * Map setting key patterns to appropriate field icons.
     */
    protected function getFieldIcon(string $key): string
    {
        $key = strtolower($key);

        if (str_contains($key, 'rate') || str_contains($key, 'percent')) return 'heroicon-o-receipt-percent';
        if (str_contains($key, 'point') || str_contains($key, 'redeem') || str_contains($key, 'star')) return 'heroicon-o-star';
        if (str_contains($key, 'otp') || str_contains($key, 'expiry') || str_contains($key, 'time') || str_contains($key, 'minute')) return 'heroicon-o-clock';
        if (str_contains($key, 'email') || str_contains($key, 'mail')) return 'heroicon-o-envelope';
        if (str_contains($key, 'phone') || str_contains($key, 'mobile') || str_contains($key, 'sms')) return 'heroicon-o-device-phone-mobile';
        if (str_contains($key, 'name') || str_contains($key, 'title')) return 'heroicon-o-identification';
        if (str_contains($key, 'url') || str_contains($key, 'link') || str_contains($key, 'domain')) return 'heroicon-o-globe-alt';
        if (str_contains($key, 'key') || str_contains($key, 'secret') || str_contains($key, 'token')) return 'heroicon-o-key';
        if (str_contains($key, 'price') || str_contains($key, 'amount') || str_contains($key, 'cost')) return 'heroicon-o-currency-rupee';

        return 'heroicon-o-tag';
    }
}
