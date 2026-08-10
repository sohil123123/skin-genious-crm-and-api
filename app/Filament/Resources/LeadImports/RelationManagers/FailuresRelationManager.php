<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadImports\RelationManagers;

use App\Enums\ImportFailureReason;
use App\Models\LeadImportFailure;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FailuresRelationManager extends RelationManager
{
    protected static string $relationship = 'failures';

    protected static ?string $title = 'Failed rows';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-exclamation-triangle';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->failures()->where('is_resolved', false)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getBadgeColor(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('row_number')
            ->columns([
                TextColumn::make('row_number')
                    ->label('Row')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('reason_code')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('What went wrong')
                    ->wrap()
                    ->limit(160)
                    ->searchable(),

                // The identifying values are lifted out of the raw row so the
                // table answers "which lead is this?" without needing to expand
                // every entry.
                TextColumn::make('raw_row')
                    ->label('Row data')
                    ->formatStateUsing(function ($state): string {
                        $row = is_array($state) ? $state : [];
                        $interesting = array_filter(
                            $row,
                            fn (string $key): bool => in_array($key, ['full_name', 'phone', 'email', 'id'], true),
                            ARRAY_FILTER_USE_KEY
                        );

                        return implode(' · ', array_map(
                            fn ($value): string => \Illuminate\Support\Str::limit((string) $value, 30),
                            $interesting !== [] ? $interesting : array_slice($row, 0, 3)
                        ));
                    })
                    ->wrap()
                    ->toggleable(),

                IconColumn::make('is_resolved')
                    ->label('Resolved')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('retried_at')
                    ->label('Last retried')
                    ->dateTime(config('leads.display.datetime_format'))
                    ->timezone(config('leads.display.timezone'))
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reason_code')
                    ->label('Failure type')
                    ->options(ImportFailureReason::options())
                    ->multiple(),

                TernaryFilter::make('is_resolved')
                    ->label('Resolved')
                    ->placeholder('All rows')
                    ->trueLabel('Resolved only')
                    ->falseLabel('Unresolved only')
                    ->default(false),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Inspect')
                    ->icon('heroicon-o-magnifying-glass')
                    ->modalHeading(fn (LeadImportFailure $record): string => "Row {$record->row_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (LeadImportFailure $record): array => [
                        View::make('filament.lead.failure-detail')->viewData(['record' => $record]),
                    ]),
            ])
            ->emptyStateHeading('No failed rows')
            ->emptyStateDescription('Every row in this file was processed successfully.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
