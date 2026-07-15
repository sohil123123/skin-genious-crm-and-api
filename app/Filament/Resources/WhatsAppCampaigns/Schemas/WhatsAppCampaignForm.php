<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use App\Models\WhatsAppTemplate;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Support\Facades\Log;

class WhatsAppCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Campaign Details')
                ->icon('heroicon-o-megaphone')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Diwali Offer Campaign'),

                        Select::make('template_id')
                            ->label('Template')
                            ->options(fn () => WhatsAppTemplate::where('status', 'APPROVED')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $template = WhatsAppTemplate::find($state);
                                    if ($template) {
                                        $placeholders = array_filter(
                                            $template->getVariablePlaceholders(),
                                            fn ($val) => trim((string)$val) !== ''
                                        );
                                        $variables = [];
                                        foreach ($placeholders as $placeholder) {
                                            $variables[] = [
                                                'key' => $placeholder,
                                                'value' => '',
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

            Section::make('Template Variables')
                ->icon('heroicon-o-variable')
                ->description('Map template variables to user fields or enter static values.')
                ->visible(function (callable $get) {
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
                        fn ($val) => trim((string)$val) !== ''
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
                                            if (trim((string)$val) === '') {
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
                                            fn ($val) => trim((string)$val) !== ''
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
                                        fn ($val) => trim((string)$val) !== ''
                                    );
                                    return array_intersect_key($flatState, array_flip($placeholders));
                                }
                            }
                            return $flatState;
                        }),
                ]),

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
                            ->options(fn () => Clinic::pluck('name', 'id')->toArray())
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
