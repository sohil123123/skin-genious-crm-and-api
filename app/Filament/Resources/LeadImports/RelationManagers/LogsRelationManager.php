<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadImports\RelationManagers;

use App\Models\LeadImportLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Activity log';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-list-bullet';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LeadImportLog::eventLabels()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (LeadImportLog $record): string => LeadImportLog::levelColors()[$record->level] ?? 'gray'),

                TextColumn::make('message')
                    ->label('Detail')
                    ->wrap()
                    ->limit(200)
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->options([
                        LeadImportLog::LEVEL_DEBUG => 'Debug',
                        LeadImportLog::LEVEL_INFO => 'Info',
                        LeadImportLog::LEVEL_WARNING => 'Warning',
                        LeadImportLog::LEVEL_ERROR => 'Error',
                    ])
                    ->multiple()
                    // Chunk-level debug entries are noise for a 200-chunk file
                    // and are hidden unless explicitly asked for.
                    ->default([LeadImportLog::LEVEL_INFO, LeadImportLog::LEVEL_WARNING, LeadImportLog::LEVEL_ERROR]),

                SelectFilter::make('event')
                    ->options(LeadImportLog::eventLabels())
                    ->multiple(),
            ])
            ->emptyStateHeading('No activity recorded');
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
