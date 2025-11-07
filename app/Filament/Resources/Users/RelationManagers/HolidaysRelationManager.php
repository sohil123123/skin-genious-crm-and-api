<?php

namespace App\Filament\Resources\Users\RelationManagers;


use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Indicator;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Actions\Action;
use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;
use Filament\Actions\ViewAction;

use App\Models\User;
use App\Models\Holiday;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

use App\Enums\HolidayStatus;
use App\Enums\HolidayType;

class HolidaysRelationManager extends RelationManager
{
    protected static string $relationship = 'holidays';

    // public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    // {
    //     return $ownerRecord->hasRole('therapist');
    // }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Basic Information')
                            // ->description('Location and mapping information.')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    DatePicker::make('start_date')
                                        ->required()
                                        ->closeOnDateSelection()
                                        ->native(false)
                                        ->minDate(Carbon::today())
                                        ->maxDate(fn ($get) => $get('end_date'))
                                        ->reactive()
                                        ->placeholder('Select start date'),

                                    DatePicker::make('end_date')
                                        ->required()
                                        ->minDate(fn ($get) => $get('start_date') ?? Carbon::today())
                                        ->closeOnDateSelection()
                                        ->native(false)
                                        ->afterOrEqual('start_date')
                                        ->reactive()
                                        ->placeholder('Select end date'),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('type')
                                        ->label('Holiday Type')
                                        ->options(HolidayType::class)
                                        ->required(),
                                    ToggleButtons::make('status')
                                        ->inline()
                                        ->options(HolidayStatus::class)
                                        ->default('pending')
                                        ->required()
                                        ->columnSpan(['lg' => 2]),
                                ]),
                                Grid::make(1)->schema([
                                    Textarea::make('reason')->rows(4)->placeholder('Reason for holiday')->required(),
                                ])
                            ]),
                            // ->collapsible(),
                    ])
                    ->afterStateUpdated(function ($state, $set, $get, $operation) {
                            // Custom validation hook for limits (runs on create/edit)
                            if ($operation === 'create' || $operation === 'edit') {
                                $userId = $get('user_id');
                                $type = $get('type');
                                $startDate = $get('start_date');
                                $endDate = $get('end_date');

                                if ($userId && $type && $startDate && $endDate) {
                                    $user = User::find($userId);
                                    $days = (new \DateTime($endDate))->diff(new \DateTime($startDate))->days + 1;
                                    $remaining = $user->remainingLeaveDays($type, date('Y', strtotime($startDate)));

                                    if ($days > $remaining) {
                                        throw ValidationException::withMessages([
                                            'type' => "User has only {$remaining} days remaining for {$type} leave this year.",
                                        ]);
                                    }
                                }
                            }
                        })
                    ->columnSpan(['lg' => fn (?Holiday $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Holiday created date')
                            ->state(fn (Holiday $record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified at')
                            ->state(fn (Holiday $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Holiday $record) => $record === null),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordTitleAttribute('holiday')
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->placeholder('Unassigned')
                    ->sortable()
                    ->searchable()
                    ->action(
                        ViewAction::make('view_clinic')
                            ->record(fn (Holiday $record) => $record->clinic)
                            ->infolist(
                                fn (Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn ($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn (Holiday $record) => $record->clinic !== null)
                    )
                    ->toggleable(),
                TextColumn::make('start_date')->date()->searchable()->sortable(),
                TextColumn::make('end_date')->date()->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('type')->badge(),
                TextColumn::make('approver.name')
                    ->label('Approver')
                    ->placeholder('-')
                    ->badge()
                    ->icon(Heroicon::User)
                    ->iconColor('success')
                    ->color('success')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name'])
                    ->toggleable(),
                TextColumn::make('reason')->limit(50)->searchable()->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
             ->filters([
                // 1) ✅ Quick checkboxes (single filter with a CheckboxList)
                Filter::make('quick')
                    ->label('Quick Filters')
                    ->form([
                        Section::make('Quick Filters')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                CheckboxList::make('ranges')
                                    ->options([
                                        'today'      => 'Today',
                                        'yesterday'  => 'Yesterday',
                                        'this_week'  => 'This Week',
                                        'this_month' => 'This Month',
                                        'this_year'  => 'This Year',
                                    ])
                                    ->columns(2)
                                    ->bulkToggleable(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ranges = collect($data['ranges'] ?? []);
                        if ($ranges->isEmpty()) {
                            return $query;
                        }

                        // Combine selected quick ranges with OR logic
                        return $query->where(function (Builder $q) use ($ranges) {
                            if ($ranges->contains('today')) {
                                $q->orWhereDate('start_date', today());
                            }
                            if ($ranges->contains('yesterday')) {
                                $q->orWhereDate('start_date', today()->subDay());
                            }
                            if ($ranges->contains('this_week')) {
                                $q->orWhereBetween('start_date', [now()->startOfWeek(), now()->endOfWeek()]);
                            }
                            if ($ranges->contains('this_month')) {
                                $q->orWhereMonth('start_date', now()->month);
                            }
                            if ($ranges->contains('this_year')) {
                                $q->orWhereYear('start_date', now()->year);
                            }
                        });
                    })
                    // show nice chips for the selected quick filters
                    ->indicateUsing(function (array $data) {
                        $ranges = collect($data['ranges'] ?? []);
                        return $ranges->map(fn ($key) => match ($key) {
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'this_month' => 'This Month',
                            'this_year' => 'This Year',
                            default => null,
                        })->filter()->all();
                    }),

                // 2) 📅 Date range section (From / To)
                Filter::make('date_range')
                    ->form([
                        Section::make('Date Range')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                DatePicker::make('from')
                                    ->label('From Date')
                                    ->minDate(Carbon::today())
                                    ->maxDate(fn ($get) => $get('to'))
                                    ->closeOnDateSelection()
                                    ->native(false)
                                    ->placeholder('From Date')
                                    ->reactive(),
                                DatePicker::make('to')
                                    ->label('To Date')
                                    ->minDate(fn ($get) => $get('from') ?? Carbon::today())
                                    ->closeOnDateSelection()
                                    ->native(false)
                                    ->placeholder('To Date')
                                    ->reactive(),
                            ])
                            ->columns(1)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query) => $query->where('end_date', '>=', $data['from']))
                            ->when($data['to'], fn (Builder $query) => $query->where('start_date', '<=', $data['to']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From Date ' . Carbon::parse($data['from'])->toFormattedDateString())->removeField('from');
                        }

                        if ($data['to'] ?? null) {
                            $indicators[] = Indicator::make('To Date ' . Carbon::parse($data['to'])->toFormattedDateString())->removeField('to');
                        }

                        return $indicators;
                    }),

                // 3) 👩‍⚕️ Therapist & Status (two columns)
                Filter::make('extra')
                    ->label('Other Filters')
                    ->form([
                        Section::make('Other Filters')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Grid::make(1)->schema([
                                    // Status
                                    Select::make('status')
                                        ->label('Status')
                                        ->options([
                                            'pending'  => 'Pending',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                        ])
                                        ->placeholder('All'),
                                ]),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['clinic_id'] ?? null) {
                            $clinic = Clinic::find($data['clinic_id']);
                            if ($clinic) {
                                $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
                            }
                        }

                        if ($data['user_id'] ?? null) {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('user_id');
                            }
                        }

                        if ($data['status'] ?? null) {
                            $indicators[] = Indicator::make('Status: ' . ucfirst($data['status']))->removeField('status');
                        }

                        return $indicators;
                    })
            ],
            layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->label('Filters')
                    ->icon('heroicon-o-funnel')
            )
            ->headerActions([
                CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // DissociateAction::make(),
                DeleteAction::make(),
                Action::make('approve')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn (Holiday $record) => $record->status === 'pending' && (auth()->user()->hasRole('clinic_manager') || auth()->user()->hasRole('super_admin')))
                    ->action(fn (Holiday $record) => $record->update(['status' => 'approved', 'approved_by' => auth()->id()])),
                Action::make('reject')
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->visible(fn (Holiday $record) => $record->status === 'pending' && (auth()->user()->hasRole('clinic_manager') || auth()->user()->hasRole('super_admin')))
                    ->action(fn (Holiday $record) => $record->update(['status' => 'rejected', 'approved_by' => auth()->id()])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first holiday, it will appear here.');
    }
}
