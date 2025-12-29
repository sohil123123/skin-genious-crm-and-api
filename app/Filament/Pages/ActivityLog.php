<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;

use UnitEnum;
use BackedEnum;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static string | UnitEnum | null $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Audit Logs';
    protected static ?string $title = 'Activity Logs';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.activity-log';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest())
            ->deferLoading()
            // ->recordUrl(null)
            ->columns([
                TextColumn::make('description')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->searchable()
                    ->formatStateUsing(function ($state, Activity $record) {
                        if (!$state) return '-';
                        return class_basename($state) . ' #' . $record->subject_id;
                    }),

                TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->form([
                        Section::make('Basic Information')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('subject_type')
                                        ->label('Subject')
                                        ->formatStateUsing(function ($state, Activity $record) {
                                            if (!$state) return '-';
                                            return class_basename($state) . ' #' . $record->subject_id;
                                        }),
                                    TextEntry::make('description'),
                                    TextEntry::make('causer.name')->label('User'),
                                    TextEntry::make('created_at')->label('Date')->dateTime('d M Y, h:i A'),
                                ]),
                            ]),

                        Section::make('Changes')
                            ->schema([
                                Forms\Components\KeyValue::make('properties.attributes')
                                    ->label('New Values')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value'),
                                
                                Forms\Components\KeyValue::make('properties.old')
                                    ->label('Old Values')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->visible(fn ($record) => isset($record->properties['old'])),
                            ])
                    ])
            ]);
    }
}
