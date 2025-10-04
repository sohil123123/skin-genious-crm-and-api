<?php

namespace App\Filament\Resources\Holidays\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Support\Icons\Heroicon;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Filament\Resources\Clinic\Schemas\ClinicInfolist;

use App\Models\Holiday;
use App\Models\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class HolidaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->placeholder('Unassigned')
                    ->sortable()
                    ->searchable()
                    ->action(
                        ViewAction::make('view_clinic')
                            ->record(fn (User $record) => $record->clinic)
                            ->infolist(
                                fn (Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn ($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn (User $record) => $record->clinic !== null)
                    )
                    ->toggleable(),
                TextColumn::make('user.name')->label('Therapist')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('start_date')->date()->searchable()->sortable(),
                TextColumn::make('end_date')->date()->searchable()->sortable(),
                TextColumn::make('status')->badge(),
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
                                DatePicker::make('from')->label('From Date')->minDate(Carbon::today())->closeOnDateSelection()->native(false)->placeholder('From Date'),
                                DatePicker::make('to')->label('To Date')->afterOrEqual('from')->closeOnDateSelection()->native(false)->placeholder('To Date'),
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
                                    // Clinic
                                    Select::make('clinic_id')
                                        ->label('Clinic')
                                        ->relationship('clinic', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a clinic')
                                        ->native(false)
                                        ->visible(!auth()->user()->hasRole('therapist')),

                                    // Therapist
                                    Select::make('user_id')
                                        ->label('Therapist')
                                        ->options(function (callable $get) {
                                            $clinicId = $get('clinic_id');
                                            if (!$clinicId) {
                                                // return User::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))
                                                //     ->get()
                                                //     ->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                                return [];
                                            }
                                            return User::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))
                                                    ->where('clinic_id', $clinicId)
                                                    ->get()
                                                    ->mapWithKeys(fn ($u) => [$u->id => $u->name]);

                                        })
                                        // ->options(fn () =>
                                        //     User::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))
                                        //         ->get()
                                        //         ->mapWithKeys(fn ($u) => [$u->id => $u->name])
                                        // )
                                        ->reactive()
                                        ->searchable()
                                        ->placeholder('All Therapists')
                                        ->visible(!auth()->user()->hasRole('therapist')),

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
                            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
                    })
            ],
            layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->label('Advanced Filters')
                    ->icon('heroicon-o-funnel')
            )
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('User Deleted 🎉')
                            ->body("The User **{$record->name}** has been removed successfully.")
                            ->success();
                    }),
                // Custom approve/reject actions for managers
                Action::make('approve')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn (Holiday $record) => $record->status === 'pending' && (auth()->user()->hasRole('clinic_manager') || auth()->user()->hasRole('admin')))
                    ->action(fn (Holiday $record) => $record->update(['status' => 'approved', 'approved_by' => auth()->id()])),
                Action::make('reject')
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->visible(fn (Holiday $record) => $record->status === 'pending' && (auth()->user()->hasRole('clinic_manager') || auth()->user()->hasRole('admin')))
                    ->action(fn (Holiday $record) => $record->update(['status' => 'rejected', 'approved_by' => auth()->id()])),
            ])
            ->groups([
                Group::make('clinic_id')
                    ->label('Clinic Name')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->clinic_id ?? 'no_clinic')
                    ->getTitleFromRecordUsing(fn ($record) => $record->clinic?->name ?? 'Unassigned'),
                Group::make('status')->label('Status')->collapsible(),
                Group::make('created_at')->date(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
