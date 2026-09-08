<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Schemas;

use App\Models\Clinic;
use App\Models\User;
use App\Models\WhatsAppMediaLibrary;
use App\Models\WhatsAppTemplate;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class WhatsAppCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Campaign Details')
                ->icon('heroicon-o-megaphone')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Diwali Offer Campaign'),

                        Select::make('message_type')
                            ->label('Message Type')
                            ->options([
                                'template' => 'Template Message',
                                'media' => 'Media Message',
                            ])
                            ->default('template')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('template_id', null);
                                $set('template_variables', []);
                                $set('header_image_path', null);
                                $set('media_library_id', null);
                                $set('media_caption', null);
                            }),

                        Select::make('template_id')
                            ->label('Template')
                            ->options(fn () => WhatsAppTemplate::where('status', 'APPROVED')
                                ->get()
                                ->mapWithKeys(fn (WhatsAppTemplate $t) => [
                                    $t->id => $t->name . ' (' . strtoupper($t->header_type ?? 'none') . ' | ' . $t->language . ')',
                                ])
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(fn (callable $get) => $get('message_type') === 'template')
                            ->visible(fn (callable $get) => $get('message_type') === 'template')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('header_image_path', null);

                                if ($state) {
                                    $template = WhatsAppTemplate::find($state);

                                    if ($template) {
                                        $placeholders = array_filter(
                                            $template->getVariablePlaceholders(),
                                            fn ($val) => trim((string) $val) !== ''
                                        );

                                        $variables = [];
                                        foreach ($placeholders as $placeholder) {
                                            $defaultMapping = match (strtolower(trim((string) $placeholder))) {
                                                'name', 'username', 'user_name', 'client_name', 'customer_name', 'full_name', '1' => 'name',
                                                'first_name' => 'first_name',
                                                'last_name' => 'last_name',
                                                'mobile', 'phone', 'phone_number', 'mobile_number' => 'mobile',
                                                'email' => 'email',
                                                'city' => 'city',
                                                'state' => 'state',
                                                'clinic', 'clinic_name' => 'clinic',
                                                default => '',
                                            };

                                            $variables[] = [
                                                'key' => $placeholder,
                                                'value' => $defaultMapping,
                                            ];
                                        }

                                        $set('template_variables', $variables);
                                    }
                                } else {
                                    $set('template_variables', []);
                                }
                            }),
                    ]),

                    Textarea::make('description')
                        ->placeholder('Brief description of this campaign')
                        ->rows(2),
                ]),

            /*
            |------------------------------------------------------------------
            | Template Preview (visible when template is selected)
            |------------------------------------------------------------------
            */
            Section::make('Template Preview')
                ->icon('heroicon-o-eye')
                ->visible(fn (callable $get) => $get('message_type') === 'template' && !empty($get('template_id')))
                ->schema([
                    Placeholder::make('template_preview_content')
                        ->label('')
                        ->content(function (callable $get): HtmlString {
                            $template = WhatsAppTemplate::find($get('template_id'));

                            if (!$template) {
                                return new HtmlString('<span class="text-gray-400">Template not found</span>');
                            }

                            $html = '<div class="space-y-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">';

                            // Header info
                            if ($template->header_type && $template->header_type !== 'none') {
                                $headerIcon = match (strtolower($template->header_type)) {
                                    'image' => '🖼️',
                                    'video' => '🎬',
                                    'document' => '📄',
                                    default => '📝',
                                };
                                $html .= '<div class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wide">'
                                    . $headerIcon . ' Header: ' . strtoupper($template->header_type) . '</div>';

                                if ($template->header_type === 'text' && $template->header_content) {
                                    $html .= '<div class="font-semibold text-gray-800 dark:text-gray-200">' . e($template->header_content) . '</div>';
                                }
                            }

                            // Body
                            if ($template->body_text) {
                                $html .= '<div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">' . e($template->body_text) . '</div>';
                            }

                            // Footer
                            if ($template->footer_text) {
                                $html .= '<div class="text-xs text-gray-400 italic">' . e($template->footer_text) . '</div>';
                            }

                            // Buttons
                            if (!empty($template->buttons)) {
                                $html .= '<div class="flex flex-wrap gap-2 pt-2 border-t border-gray-200 dark:border-gray-600">';
                                foreach ($template->buttons as $btn) {
                                    $html .= '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-800 dark:text-primary-200">'
                                        . e($btn['text'] ?? 'Button') . '</span>';
                                }
                                $html .= '</div>';
                            }

                            $html .= '</div>';

                            return new HtmlString($html);
                        }),
                ]),

            /*
            |------------------------------------------------------------------
            | Header Image (visible when template has IMAGE header)
            |------------------------------------------------------------------
            */
            Section::make('Header Image')
                ->icon('heroicon-o-photo')
                ->description('Upload an image to send as the template header.')
                ->visible(function (callable $get): bool {
                    if ($get('message_type') !== 'template') {
                        return false;
                    }

                    $templateId = $get('template_id');

                    if (!$templateId) {
                        return false;
                    }

                    $template = WhatsAppTemplate::find($templateId);

                    return $template && strtolower($template->header_type ?? '') === 'image';
                })
                ->schema([
                    FileUpload::make('header_image_path')
                        ->label('Select Image')
                        ->image()
                        ->disk('public')
                        ->directory('whatsapp-broadcast')
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->helperText('Accepted formats: JPEG, PNG. Max size: 5MB.'),
                ]),

            /*
            |------------------------------------------------------------------
            | Template Variables (visible when template has variables)
            |------------------------------------------------------------------
            */
            Section::make('Template Variables')
                ->icon('heroicon-o-variable')
                ->description('Map template variables to user fields or enter static values.')
                ->visible(function (callable $get): bool {
                    if ($get('message_type') !== 'template') {
                        return false;
                    }

                    $templateId = $get('template_id');

                    if (!$templateId) {
                        return false;
                    }

                    $template = WhatsAppTemplate::find($templateId);

                    if (!$template) {
                        return false;
                    }

                    $placeholders = array_filter(
                        $template->getVariablePlaceholders(),
                        fn ($val) => trim((string) $val) !== ''
                    );

                    return count($placeholders) > 0;
                })
                ->schema([
                    KeyValue::make('template_variables')
                        ->keyLabel('Variable Name')
                        ->valueLabel('User Field / Static Value')
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(false)
                        ->editableKeys(false)
                        ->required()
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (!is_array($value)) {
                                        return;
                                    }
                                    foreach ($value as $item) {
                                        if (is_array($item) && isset($item['key'])) {
                                            $key = $item['key'];
                                            $val = $item['value'] ?? '';
                                            if (trim((string) $val) === '') {
                                                $fail("The value for variable '{$key}' is required.");
                                            }
                                        }
                                    }
                                };
                            }
                        ])
                        ->afterStateHydrated(function ($state, callable $set, callable $get) {
                            if (is_array($state)) {
                                $isFlat = true;
                                foreach ($state as $k => $v) {
                                    if (is_array($v) && isset($v['key'])) {
                                        $isFlat = false;
                                        break;
                                    }
                                }

                                $flatState = [];
                                if ($isFlat) {
                                    $flatState = $state;
                                } else {
                                    foreach ($state as $item) {
                                        if (is_array($item) && isset($item['key'])) {
                                            $flatState[$item['key']] = $item['value'] ?? '';
                                        }
                                    }
                                }

                                $templateId = $get('template_id');
                                if ($templateId) {
                                    $template = WhatsAppTemplate::find($templateId);
                                    if ($template) {
                                        $placeholders = array_filter(
                                            $template->getVariablePlaceholders(),
                                            fn ($val) => trim((string) $val) !== ''
                                        );
                                        $flatState = array_intersect_key($flatState, array_flip($placeholders));
                                    }
                                }

                                $listState = [];
                                foreach ($flatState as $k => $v) {
                                    $listState[] = [
                                        'key' => $k,
                                        'value' => $v,
                                    ];
                                }
                                $set('template_variables', $listState);
                            }
                        })
                        ->dehydrateStateUsing(function ($state, callable $get) {
                            if (!is_array($state)) {
                                return $state;
                            }

                            $flatState = [];
                            foreach ($state as $item) {
                                if (is_array($item) && isset($item['key'])) {
                                    $flatState[$item['key']] = $item['value'] ?? '';
                                } else {
                                    $flatState = $state;
                                    break;
                                }
                            }

                            $templateId = $get('template_id');
                            if ($templateId) {
                                $template = WhatsAppTemplate::find($templateId);
                                if ($template) {
                                    $placeholders = array_filter(
                                        $template->getVariablePlaceholders(),
                                        fn ($val) => trim((string) $val) !== ''
                                    );
                                    return array_intersect_key($flatState, array_flip($placeholders));
                                }
                            }
                            return $flatState;
                        }),
                ]),

            /*
            |------------------------------------------------------------------
            | Media Message Content (visible when media type is selected)
            |------------------------------------------------------------------
            */
            Section::make('Media Content')
                ->icon('heroicon-o-photo')
                ->description('Select a media file from the library and add an optional caption.')
                ->visible(fn (callable $get) => $get('message_type') === 'media')
                ->schema([
                    Select::make('media_library_id')
                        ->label('Media File')
                        ->options(fn () => WhatsAppMediaLibrary::pluck('name', 'id')->toArray())
                        ->searchable()
                        ->preload()
                        ->required(fn (callable $get) => $get('message_type') === 'media'),

                    Textarea::make('media_caption')
                        ->label('Media Caption')
                        ->placeholder('Write a caption for the media...')
                        ->rows(3),
                ]),

            /*
            |------------------------------------------------------------------
            | Audience Selection
            |------------------------------------------------------------------
            */
            Section::make('Audience Selection')
                ->icon('heroicon-o-users')
                ->schema([
                    Select::make('audience_type')
                        ->options([
                            'all' => 'All Active Clients',
                            'specific' => 'Specific Clients',
                            'filter' => 'Filter by Criteria',
                        ])
                        ->default('all')
                        ->required()
                        ->live(),

                    Select::make('audience_user_ids')
                        ->label('Select Clients')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => User::role('client')
                            ->where('is_active', true)
                            ->whereNotNull('mobile')
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->mobile . ')'])
                            ->toArray())
                        ->visible(fn ($get) => $get('audience_type') === 'specific')
                        ->preload(),

                    Grid::make(3)->schema([
                        Select::make('audience_filter.clinic_id')
                            ->label('Clinic')
                            ->options(fn () => Clinic::active()->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn ($get) => $get('audience_type') === 'filter'),

                        Select::make('audience_filter.gender')
                            ->label('Gender')
                            ->options(['male' => 'Male', 'female' => 'Female'])
                            ->visible(fn ($get) => $get('audience_type') === 'filter'),

                        Select::make('audience_filter.city')
                            ->label('City')
                            ->options(fn () => User::whereNotNull('city')->distinct()->pluck('city', 'city')->toArray())
                            ->searchable()
                            ->visible(fn ($get) => $get('audience_type') === 'filter'),
                    ]),
                ]),

            /*
            |------------------------------------------------------------------
            | Scheduling
            |------------------------------------------------------------------
            */
            Section::make('Scheduling')
                ->icon('heroicon-o-clock')
                ->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('scheduled_at')
                            ->label('Schedule For')
                            ->placeholder('Leave empty to send immediately')
                            ->native(false)
                            ->minDate(now()),

                        Select::make('timezone')
                            ->options(['Asia/Kolkata' => 'Asia/Kolkata (IST)', 'UTC' => 'UTC'])
                            ->default('Asia/Kolkata'),
                    ]),

                    Toggle::make('is_recurring')
                        ->label('Recurring Campaign')
                        ->live(),

                    Select::make('recurrence_rule')
                        ->label('Recurrence')
                        ->options([
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                        ])
                        ->visible(fn ($get) => $get('is_recurring')),
                ]),
        ]);
    }
}
