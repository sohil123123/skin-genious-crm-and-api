<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadMappingTemplates;

use App\Enums\DuplicateStrategy;
use App\Filament\Resources\LeadMappingTemplates\Pages\ListLeadMappingTemplates;
use App\Models\LeadMappingTemplate;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LeadMappingTemplateResource extends Resource
{
    protected static ?string $model = LeadMappingTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bookmark-square';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Mapping Templates';

    protected static ?string $modelLabel = 'mapping template';

    protected static ?int $navigationSort = 34;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                // ->columns(['default' => 1, 'md' => 2])
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Template name')
                        ->required()
                        ->maxLength(255),

                    Toggle::make('is_default')
                        ->label('Use as fallback')
                        ->helperText('Applied when an uploaded file does not match any saved template.'),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Section::make('Saved mapping')
                ->description('Recorded when the template was created. Re-save from the import wizard to change it.')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    View::make('filament.lead.template-mapping-summary')
                        ->viewData(fn (LeadMappingTemplate $record): array => ['record' => $record]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('usage_count', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->description(fn (LeadMappingTemplate $record): ?string => $record->description)
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),

                // Counted off the record rather than the column state: badging
                // an array state renders one badge per element and formats each
                // element in turn, so a formatter that counted the array never
                // saw it — every template showed a row of pills reading "0",
                // one per header. The full list belongs in the tooltip, since
                // seventeen column names do not fit a table cell.
                TextColumn::make('header_columns')
                    ->label('Columns')
                    ->state(fn (LeadMappingTemplate $record): int => count($record->header_columns ?? []))
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->tooltip(fn (LeadMappingTemplate $record): ?string => filled($record->header_columns)
                        ? implode(', ', $record->header_columns)
                        : null),

                TextColumn::make('duplicate_strategy')
                    ->label('Duplicates')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof DuplicateStrategy ? $state->getLabel() : (string) $state),

                TextColumn::make('usage_count')
                    ->label('Times used')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('Never')
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Fallback')
                    ->boolean(),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color('info')
                    ->placeholder('All clinics')
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),

                TextColumn::make('creator.name')
                    ->label('Saved by')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No saved mappings')
            ->emptyStateDescription('Save a mapping from the import wizard and it will be offered automatically the next time the same form is exported.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadMappingTemplates::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['clinic', 'creator'])
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }
}
