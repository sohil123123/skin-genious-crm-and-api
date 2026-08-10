<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadCustomFields\Schemas;

use App\Enums\LeadFieldType;
use App\Models\LeadCustomField;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * The edit form for one imported lead question.
 *
 * Everything here arrives from a Facebook export, so the raw values are
 * snake_cased and awkward to read. The form's job is to let someone tidy that
 * up without having to understand which parts are safe to touch: the question
 * text and its options are editable, the key that stored answers hang off is
 * not, and the difference is made obvious rather than explained.
 */
class LeadCustomFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Question')
                ->description('How this question reads throughout the CRM.')
                ->icon('heroicon-o-chat-bubble-left-right')
                // ->columns(['default' => 1, 'md' => 2])
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Textarea::make('label')
                        ->label('Question text')
                        ->required()
                        ->rows(2)
                        ->autosize()
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->helperText('Shown on lead detail screens, filters and reports. Tidying up the wording here is safe — answers are linked by key, not by this text.'),

                    // The raw import value is worth seeing once, because it is
                    // what the person is deciding whether to rewrite, but it is
                    // reference material rather than a field to fill in.
                    Text::make(fn (?LeadCustomField $record): HtmlString => new HtmlString(
                        '<span style="opacity:.62;">As imported from Facebook:</span> '
                        . '<code style="word-break:break-word;">' . e((string) $record?->label) . '</code>'
                    ))
                        ->columnSpanFull()
                        ->visible(fn (?LeadCustomField $record): bool => $record !== null),

                    Select::make('type')
                        ->label('Answer type')
                        ->options(LeadFieldType::options())
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Choice types can be filtered and charted; text answers cannot.'),

                    TextInput::make('key')
                        ->label('Key')
                        ->disabled()
                        ->dehydrated(false)
                        // Changing the key would orphan every stored answer, so
                        // it is shown for reference only.
                        ->helperText('Set on first import. Answers are stored against it, so it cannot be changed.'),
                ]),

            Section::make('Answer options')
                ->description('The answers this question accepts.')
                ->icon('heroicon-o-list-bullet')
                ->visible(fn (Get $get): bool => in_array(
                    $get('type'),
                    [LeadFieldType::Select->value, LeadFieldType::MultiSelect->value],
                    true
                ))
                ->schema([
                    TagsInput::make('options')
                        ->hiddenLabel()
                        ->placeholder('Add an option')
                        ->reorderable()
                        ->helperText('Collected automatically as leads are imported. Remove an option no longer offered, or add one before it first appears. Editing an option here does not rewrite answers already recorded against the old wording — merge the question instead if the two should count as one.'),

                    // Raw values are kept as the stored state so existing
                    // answers keep matching, which makes the tag list hard to
                    // read on its own. The humanised reading is shown beside it
                    // rather than instead of it.
                    Text::make(fn (Get $get): HtmlString => static::optionsPreview($get('options')))
                        ->visible(fn (Get $get): bool => filled($get('options'))),
                ]),

            Section::make('Visibility')
                ->description('Where this question appears, and in what order.')
                ->icon('heroicon-o-eye')
                ->columns(['default' => 1, 'md' => 2])
                ->collapsible()
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive questions are hidden from mapping suggestions and lead detail screens. Their answers are kept.'),

                    TextInput::make('sort_order')
                        ->label('Display order')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Lower numbers appear first.'),

                    Textarea::make('description')
                        ->label('Internal note')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->placeholder('Only visible to staff on this screen.'),

                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->relationship('clinic', 'name', fn ($query) => $query->active()->orderBy('name'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Shared across all clinics')
                        ->helperText('Leave empty to offer this question to every clinic.')
                        ->columnSpanFull()
                        ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),
                ]),
        ]);
    }

    /**
     * Render the stored options as they will read in the CRM.
     *
     * @param  mixed  $options
     */
    protected static function optionsPreview($options): HtmlString
    {
        $options = is_array($options) ? $options : [];

        $rendered = array_map(
            fn ($option): string => '<span style="display:inline-block; padding:.125rem .5rem; margin:.125rem .25rem .125rem 0;'
                . ' border-radius:9999px; font-size:.75rem;'
                . ' background:color-mix(in srgb, currentColor 8%, transparent);">'
                . e(LeadCustomField::humanizeValue((string) $option)) . '</span>',
            $options
        );

        return new HtmlString(
            '<div style="font-size:.75rem; opacity:.62; margin-bottom:.25rem;">Shown in the CRM as</div>'
            . implode('', $rendered)
        );
    }
}
