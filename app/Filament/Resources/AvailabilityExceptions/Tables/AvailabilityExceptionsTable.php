<?php

namespace App\Filament\Resources\AvailabilityExceptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

class AvailabilityExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('start_date', 'asc')
            ->columns([
                // TextColumn::make('exceptionable_type')
                //     ->label('Scope')
                //     ->formatStateUsing(fn ($v) =>
                //         $v === Clinic::class ? 'Clinic' : 'Therapist'
                //     ),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->icon('heroicon-o-building-office')
                    ->color('gray')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->action(
                        ViewAction::make('view_clinic')
                            ->record(fn ($record) => $record->clinic)
                            ->infolist(
                                fn (Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn ($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn ($record) => $record->clinic !== null)
                    )
                    ->toggleable(),

                TextColumn::make('exceptionable')
                    ->label('Therapist/Clinic')
                    ->getStateUsing(function ($record) {
                        if (! $record->exceptionable)
                            return '-';

                        // Therapist (User)
                        if ($record->exceptionable instanceof User)
                            return $record->exceptionable->name;

                        // Clinic
                        if ($record->exceptionable instanceof Clinic)
                            return $record->exceptionable->name;

                        return '-';
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHasMorph(
                            'exceptionable',
                            [User::class],
                            fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                        )->orWhereHasMorph(
                            'exceptionable',
                            [Clinic::class],
                            fn ($q) => $q->where('name', 'like', "%{$search}%")
                        );
                    }),

                TextColumn::make('type')->label('Type'),
                TextColumn::make('leave_type')->badge()->placeholder('-'),
                TextColumn::make('start_date')->date(),
                TextColumn::make('end_date')->date(),
                TextColumn::make('start_time')->placeholder('-'),
                TextColumn::make('end_time')->placeholder('-'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Availability Exception Deleted 🎉')
                            ->body("Availability Exception has been removed successfully.")
                            ->success();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first record, it will appear here.');
    }
}
