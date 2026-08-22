<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Tables\Enums\FiltersLayout;

use UnitEnum;
use BackedEnum;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static string|UnitEnum|null $navigationGroup = 'Others';
    // protected static ?string $navigationLabel = 'Others';
    protected static ?string $title = 'Activity Logs';

    protected static ?int $navigationSort = 25;

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
            ->recordUrl(null)
            ->columns([
                TextColumn::make('description')
                    ->searchable()
                    ->badge()
                    ->color(fn(Activity $record): string => match ($record->event) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'emergency_override' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->searchable()
                    ->formatStateUsing(function ($state, Activity $record) {
                        if (!$state)
                            return '-';
                        return class_basename($state) . ' #' . $record->subject_id;
                    }),

                TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(app_datetime_format())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'login' => 'User Login',
                        'logout' => 'User Logout',
                        'emergency_override' => 'Emergency Override',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }

                        if ($data['value'] === 'login') {
                            $query->where('description', 'User logged in');
                        } elseif ($data['value'] === 'logout') {
                            $query->where('description', 'User logged out');
                        } else {
                            $query->where('event', $data['value']);
                        }
                    }),

                SelectFilter::make('subject_type')
                    ->label('Model')
                    ->options(function () {
                        return Activity::query()
                            ->whereNotNull('subject_type')
                            ->distinct()
                            ->pluck('subject_type')
                            ->mapWithKeys(fn($type) => [$type => class_basename($type)])
                            ->toArray();
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(
                fn(Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                ViewAction::make()
                    ->form([
                        Section::make('Basic Information')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('subject_type')
                                        ->label('Subject')
                                        ->formatStateUsing(function ($state, Activity $record) {
                                            if (!$state)
                                                return '-';
                                            return class_basename($state) . ' #' . $record->subject_id;
                                        }),
                                    TextEntry::make('description'),
                                    TextEntry::make('causer.name')->label('User'),
                                    TextEntry::make('created_at')->label('Date')->dateTime(app_datetime_format()),
                                ]),
                            ]),

                        Section::make('Changes')
                            ->visible(fn($record) => isset($record->properties['attributes']) && isset($record->properties['old']))
                            ->schema([
                                KeyValue::make('properties.attributes')
                                    ->label('New Values')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->visible(fn($record) => isset($record->properties['attributes'])),

                                KeyValue::make('properties.old')
                                    ->label('Old Values')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->visible(fn($record) => isset($record->properties['old'])),
                            ]),

                        // Section::make('Changes')
                        //     ->visible(fn ($record) => $record->event == 'status_changed')
                        //     ->schema([
                        //         TextEntry::make('properties.emergency_reason.capacity')->label('Available Capacity')->numeric(),
                        //         TextEntry::make('properties.emergency_reason.confirmed')->label('Confirmed Cases')->numeric(),
                        //         TextEntry::make('properties.emergency_reason.violations')->label('Violations')->badge()->listWithLineBreaks()->color('danger'),
                        //         TextEntry::make('properties.emergency_reason.is_emergency')->label('Emergency Override')->badge()->color(fn (bool $state) => $state ? 'danger' : 'gray')->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                        //     ])
                        //     ->columns(3)

                        Section::make('Emergency Overrides')
                            ->visible(fn($record) => filled($record->properties['emergency_reason'] ?? null))
                            ->schema([
                                RepeatableEntry::make('emergency_reason')
                                    ->label('Emergency Reasons')
                                    ->getStateUsing(fn($record) => $record->properties['emergency_reason'] ?? [])
                                    ->schema([
                                        TextEntry::make('message')
                                            ->label('Message')
                                            ->badge()
                                            ->color('danger')
                                            ->columnSpanFull(),

                                        // TextEntry::make('is_emergency')
                                        //     ->label('Emergency')
                                        //     ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                                        //     ->badge()
                                        //     ->color(fn ($state) => $state ? 'danger' : 'gray'),

                                        TextEntry::make('capacity')
                                            ->label('Capacity')
                                            ->placeholder('-')
                                            ->numeric()
                                            ->visible(fn($state) => filled($state)),

                                        TextEntry::make('confirmed')
                                            ->label('Confirmed')
                                            ->placeholder('-')
                                            ->numeric()
                                            ->visible(fn($state) => filled($state)),
                                    ])
                                    ->columns(2),
                            ])


                    ])
            ]);
    }
}
