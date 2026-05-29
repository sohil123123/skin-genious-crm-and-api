<?php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use App\Models\TreatmentSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class LatestCompletedSessions extends TableWidget
{
    use HasWidgetShield;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Latest Sessions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TreatmentSession::query()
                    ->whereIn('status', ['completed', 'in_progress'])
                    ->whereNotNull('assessment_id')
                    ->latest('updated_at')
            )
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('user.first_name')
                    ->label('Patient')
                    ->searchable()
                    ->formatStateUsing(fn ($record) => trim(($record->user?->first_name ?? '') . ' ' . ($record->user?->last_name ?? ''))),
                TextColumn::make('title')
                    ->label('Session Title')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }
                        return $state;
                    }),
                TextColumn::make('plan_type')
                    ->label('Plan Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'single' => 'success',
                        'multiple' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('session_number')
                    ->label('Session #'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->label('Completed At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('complete_session_actions')
                    ->label('Post-Session Actions')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->url(function ($record) {
                        return clinic_head_complete_session($record);
                    })
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No Latest Sessions')
            ->emptyStateDescription('There are currently no completed sessions available.');
    }
}
