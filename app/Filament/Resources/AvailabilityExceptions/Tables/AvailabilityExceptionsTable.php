<?php

namespace App\Filament\Resources\AvailabilityExceptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Models\Clinic;
use App\Models\User;
use App\Models\AvailabilityException;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

use App\Enums\AvailabilityExceptionType;
use App\Enums\LeaveType;

class AvailabilityExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('start_date', 'asc')
            ->columns([
                TextColumn::make('exceptionable_type')
                    ->label('Type')
                    ->badge()
                    ->visible(fn() => check_role('super_admin') || check_role(['clinic_manager', 'clinic_head']))
                    ->getStateUsing(function ($record) {
                        // Therapist (User)
                        if ($record->exceptionable instanceof User)
                            return 'Therapist';

                        // Clinic
                        if ($record->exceptionable instanceof Clinic)
                            return 'Clinic';

                        return '-';
                    })
                    // ->color(function ($record) {
                    //     if($record->exceptionable instanceof User)
                    //         return 'warning';

                    //     return 'gray';
                    // })
                    ->color(fn($record) => $record->type?->getColor() ?? 'gray')
                    ->icon(function ($record) {
                        if ($record->exceptionable instanceof User)
                            return 'heroicon-o-user';

                        return 'heroicon-o-building-office';
                    }),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->visible(fn() => auth()->user()->hasRole('super_admin') || check_role(['clinic_manager', 'clinic_head']))
                    ->color(fn($record) => $record->type?->getColor() ?? 'gray')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->action(
                        ViewAction::make('view_clinic')
                            ->record(fn($record) => $record->clinic)
                            ->infolist(
                                fn(Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn($record) => $record->clinic !== null)
                    )
                    ->toggleable(),

                TextColumn::make('exceptionable')
                    ->label('Therapist')
                    ->visible(fn() => check_role('super_admin') || check_role(['clinic_manager', 'clinic_head']))
                    ->badge()
                    ->color(fn($record) => $record->type?->getColor() ?? 'gray')
                    // ->color(function ($record) {
                    //     if($record->exceptionable instanceof User)
                    //         return 'warning';

                    //     // return 'gray';
                    // })
                    ->icon(function ($record) {
                        if ($record->exceptionable instanceof User)
                            return 'heroicon-o-user';

                        // return 'heroicon-o-building-office';
                    })
                    ->getStateUsing(function ($record) {
                        if (!$record->exceptionable)
                            return '-';

                        // Therapist (User)
                        if ($record->exceptionable instanceof User)
                            return $record->exceptionable->name;

                        // // Clinic
                        // if ($record->exceptionable instanceof Clinic)
                        //     return $record->exceptionable->name;
            
                        return '-';
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHasMorph(
                            'exceptionable',
                            [User::class],
                            fn($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                        )->orWhereHasMorph(
                                'exceptionable',
                                [Clinic::class],
                                fn($q) => $q->where('name', 'like', "%{$search}%")
                            );
                    }),

                TextColumn::make('type')->label('Type')->badge()->searchable(),
                TextColumn::make('leave_type')->badge()->color(fn($record) => $record->type?->getColor() ?? 'gray')->searchable()->placeholder('-'),
                // TextColumn::make('start_date')->date()->searchable(),
                // TextColumn::make('end_date')->date()->searchable(),
                // TextColumn::make('start_time')->searchable()->placeholder('-'),
                // TextColumn::make('end_time')->searchable()->placeholder('-'),
                TextColumn::make('start_datetime')
                    ->label('Start')
                    ->badge()
                    ->color(fn($record) => $record->type?->getColor() ?? 'gray')
                    // ->color(fn ($record) => $record->type->value == AvailabilityExceptionType::LeaveFullDay->value ? 'success' : 'gray')
                    ->state(function ($record) {
                        if ($record->type->value == AvailabilityExceptionType::LeaveFullDay->value)
                            return Carbon::parse($record->start_date)->format('M d, Y') . ' (Full day)';

                        return $record->start_date
                            ->copy()
                            ->setTimeFromTimeString($record->start_time)
                            ->format('M d, Y · H:i');
                    })
                    ->sortable(query: function ($query, $direction) {
                        $query->orderBy('start_date', $direction)->orderBy('start_time', $direction);
                    }),

                TextColumn::make('end_datetime')
                    ->label('End')
                    ->badge()
                    ->color(fn($record) => $record->type?->getColor() ?? 'gray')
                    // ->color(fn ($record) => $record->type->value == AvailabilityExceptionType::LeaveFullDay->value ? 'success' : 'gray')
                    ->state(function ($record) {
                        if ($record->type->value == AvailabilityExceptionType::LeaveFullDay->value)
                            return Carbon::parse($record->end_date)->format('M d, Y') . ' (Full day)';

                        return $record->end_date
                            ->copy()
                            ->setTimeFromTimeString($record->end_time)
                            ->format('M d, Y · H:i');
                    })
                    ->sortable(query: function ($query, $direction) {
                        $query->orderBy('end_date', $direction)->orderBy('end_time', $direction);
                    }),
                TextColumn::make('status')->badge()->searchable(),
            ])
            ->filters(
                [
                    // 1) ✅ Quick checkboxes (single filter with a CheckboxList)
                    Filter::make('quick')
                        ->label('Quick Filters')
                        ->form([
                            Section::make('Quick Filters')
                                ->icon('heroicon-o-clock')
                                ->schema([
                                    CheckboxList::make('ranges')
                                        ->options([
                                            'today' => 'Today',
                                            'yesterday' => 'Yesterday',
                                            'this_week' => 'This Week',
                                            'this_month' => 'This Month',
                                            'this_year' => 'This Year',
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
                            return $ranges->map(fn($key) => match ($key) {
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
                                        // ->minDate(Carbon::today())
                                        ->maxDate(fn($get) => $get('to'))
                                        ->closeOnDateSelection()
                                        ->native(false)
                                        ->placeholder('From Date')
                                        ->reactive(),
                                    DatePicker::make('to')
                                        ->label('To Date')
                                        ->minDate(fn($get) => $get('from') ?? Carbon::today())
                                        ->closeOnDateSelection()
                                        ->native(false)
                                        ->placeholder('To Date')
                                        ->reactive(),
                                ])
                                ->columns(1)
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return $query
                                ->when($data['from'], fn(Builder $query) => $query->where('end_date', '>=', $data['from']))
                                ->when($data['to'], fn(Builder $query) => $query->where('start_date', '<=', $data['to']));
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
                                        // exceptionable_type (clinic or therapist)
                                        Select::make('exceptionable_type')
                                            ->label('Type')
                                            ->options([
                                                User::class => 'Therapist',
                                                Clinic::class => 'Clinic',
                                            ])
                                            ->native(true)
                                            ->visible(!check_role('therapist')),

                                        // Clinic
                                        Select::make('clinic_id')
                                            ->label('Clinic')
                                            ->relationship('clinic', 'name')
                                            // ->searchable()
                                            // ->preload()
                                            ->placeholder('Select a clinic')
                                            ->native(false)
                                            ->visible(check_role('super_admin')),

                                        // Therapist
                                        Select::make('exceptionable_id')
                                            ->label('Therapist')
                                            ->options(function (callable $get) {
                                                $clinicId = $get('clinic_id');
                                                if (!$clinicId)
                                                    $clinicId = auth()->user()->clinic_id;

                                                return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn($u) => [$u->id => $u->name]);

                                            })
                                            ->reactive()
                                            ->searchable()
                                            ->placeholder('All Therapists')
                                            ->visible(fn($get) => !check_role('therapist')),

                                        // Type
                                        Select::make('type')
                                            ->label('Availability Type')
                                            ->options(AvailabilityExceptionType::class),

                                        // Status
                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'pending' => 'Pending',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                            ])
                                            ->placeholder('All'),
                                    ]),
                                ]),
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return $query
                                ->when($data['exceptionable_type'] ?? null, fn($q, $id) => $q->where('exceptionable_type', $id))
                                ->when($data['clinic_id'] ?? null, fn($q, $id) => $q->where('clinic_id', $id))
                                ->when($data['exceptionable_id'] ?? null, fn($q, $id) => $q->where('exceptionable_id', $id))
                                ->when($data['status'] ?? null, fn($q, $status) => $q->where('status', $status))
                                ->when($data['type'] ?? null, fn($q, $type) => $q->where('type', $type));
                        })
                        ->indicateUsing(function (array $data): array {
                            $indicators = [];

                            if ($data['exceptionable_type'] ?? null) {
                                if ($data['exceptionable_type'] instanceof User)
                                    $type = 'Clinic';
                                else
                                    $type = 'Therapist';
                                $indicators[] = Indicator::make('Type: ' . $type)->removeField('exceptionable_type');
                            }

                            if ($data['clinic_id'] ?? null) {
                                $clinic = Clinic::find($data['clinic_id']);
                                if ($clinic) {
                                    $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
                                }
                            }

                            if ($data['exceptionable_id'] ?? null) {
                                $user = User::find($data['exceptionable_id']);
                                if ($user) {
                                    $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('exceptionable_id');
                                }
                            }

                            if ($data['status'] ?? null) {
                                $indicators[] = Indicator::make('Status: ' . ucfirst($data['status']))->removeField('status');
                            }

                            if ($data['type'] ?? null) {
                                $indicators[] = Indicator::make('Availability Type: ' . ucfirst($data['type']))->removeField('type');
                            }

                            return $indicators;
                        })
                ],
                layout: FiltersLayout::Modal
            )
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->label('Filters')
                    ->icon('heroicon-o-funnel')
            )
            ->recordActions([
                // Custom approve/reject actions for managers
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Approve record')
                    ->modalSubheading('Are you sure you want to approve this item?')
                    ->visible(fn($record) => ($record->exceptionable instanceof User) && ($record->status->value === 'pending') && (check_role(['clinic_manager', 'clinic_head']) || check_role('super_admin')))
                    ->action(function ($record) {
                        $record->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => Carbon::now()]);
                        Notification::make()
                            ->success()
                            ->title('Approved')
                            ->body('Record approved successfully.')
                            ->send();
                    }),

                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Reject record')
                    ->modalSubheading('Please confirm rejection. This action can be recorded.')
                    ->visible(fn($record) => ($record->exceptionable instanceof User) && ($record->status->value === 'pending') && (check_role(['clinic_manager', 'clinic_head']) || check_role('super_admin')))
                    ->action(function ($record) {
                        $record->update(['status' => 'rejected']);
                        Notification::make()
                            ->danger()
                            ->title('Rejected')
                            ->body('Record rejected.')
                            ->send();
                    }),

                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->successNotification(function ($record) {
                            return Notification::make()
                                ->title('Availability Exception Deleted 🎉')
                                ->body("Availability Exception has been removed successfully.")
                                ->success();
                        }),
                ]),
            ])
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ])
            ->emptyStateDescription('Once you create your first record, it will appear here.');
    }
}
