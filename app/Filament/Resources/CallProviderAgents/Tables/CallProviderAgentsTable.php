<?php

declare(strict_types=1);

namespace App\Filament\Resources\CallProviderAgents\Tables;

use App\Enums\Call\CallProvider;
use App\Models\CallProviderAgent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CallProviderAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Unmapped rows first, then the busiest — so the list opens on the
            // work rather than on the rows that are already fine.
            ->defaultSort('call_count', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByRaw('user_id IS NULL DESC'))
            ->columns([
                TextColumn::make('provider')->badge()->sortable(),

                TextColumn::make('provider_employee_name')
                    ->label('Provider name')
                    ->placeholder('—')
                    ->description(fn (CallProviderAgent $record): ?string => $record->provider_employee_code)
                    ->searchable(),

                TextColumn::make('provider_employee_number')
                    ->label('Number')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(['provider_employee_number', 'provider_employee_key']),

                TextColumn::make('user.name')
                    ->label('CRM user')
                    ->badge()
                    ->color(fn (CallProviderAgent $record): string => $record->user_id ? 'success' : 'warning')
                    ->placeholder('Not mapped')
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'Not mapped'),

                TextColumn::make('clinic.name')->label('Clinic')->badge()->placeholder('—')->toggleable(),

                TextColumn::make('call_count')
                    ->label('Calls')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->placeholder('—')
                    ->sortable(),

                IconColumn::make('auto_discovered')
                    ->label('Auto')
                    ->boolean()
                    ->tooltip('Created automatically the first time this number appeared on a call')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('provider')->options(CallProvider::options()),

                TernaryFilter::make('mapped')
                    ->label('Mapped to a user')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                    ),

                TernaryFilter::make('active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('The calls this mapping already explained keep their agent. Only future matching is affected.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No agent mappings yet')
            ->emptyStateDescription('These appear on their own as soon as calls start arriving.');
    }
}
