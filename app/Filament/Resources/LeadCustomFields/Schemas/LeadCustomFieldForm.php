<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadCustomFields\Schemas;

use App\Enums\LeadFieldType;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LeadCustomFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Textarea::make('label')
                        ->label('Question')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull()
                        ->helperText('The question as it was asked on the lead form. Renaming it here changes how it appears everywhere in the CRM.'),

                    TextInput::make('key')
                        ->label('Key')
                        ->required()
                        ->maxLength(191)
                        ->disabled()
                        ->dehydrated(false)
                        // Changing the key would orphan every stored answer, so
                        // it is shown for reference only.
                        ->helperText('Set automatically when the question is first imported and cannot be changed.'),

                    Select::make('type')
                        ->label('Answer type')
                        ->options(LeadFieldType::options())
                        ->required()
                        ->native(false)
                        ->live(),

                    TagsInput::make('options')
                        ->label('Answer options')
                        ->helperText('Collected automatically as leads are imported. Edit to tidy up or remove an option that is no longer offered.')
                        ->visible(fn (Get $get): bool => in_array($get('type'), [LeadFieldType::Select->value, LeadFieldType::MultiSelect->value], true))
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Internal note')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive questions are hidden from mapping suggestions and lead detail screens, but their answers are kept.'),

                    TextInput::make('sort_order')
                        ->label('Display order')
                        ->numeric()
                        ->default(0),

                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->relationship('clinic', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Shared across all clinics')
                        ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),
                ]),
        ]);
    }
}
