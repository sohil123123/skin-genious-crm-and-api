<?php

namespace App\Filament\Widgets;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteAction;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
// use Filament\Forms\Components\CheckboxList;
// use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Str;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

use App\Filament\Resources\Users\UserResource;

class LatestClients extends TableWidget
{
    use InteractsWithPageFilters, HasWidgetShield;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '5s';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            // ->query(fn (): Builder => User::query())
            ->query(UserResource::getEloquentQuery()->role('client'))
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->color('info')
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
                    ),
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name'])
                    ->formatStateUsing(fn ($record) => trim($record->first_name . ' ' . ($record->last_name ?? ''))),
                TextColumn::make('mobile')->searchable(),
                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match (strtolower($state)) {
                        'male'   => '👨 Male',
                        'female' => '👩 Female',
                        default  => '❓ Unknown',
                    })
                    ->color(fn ($state) => match (strtolower($state)) {
                        'male'   => 'info',
                        'female' => 'danger',
                        default  => 'gray',
                    })
                    ->placeholder('-'),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('-'),
                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    ->onColor('success')
                    ->sortable()
                    // ->disabled(fn () => ! auth()->user()?->can('toggle_user_status'))
                    // ->visible(auth()->user()->can('toggle_user_status'))
                    ->afterStateUpdated(function ($state, $record) {
                        if (! auth()->user()->can('toggle_user_status')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update user status.')
                                ->danger()
                                ->send();

                            $record->is_active = ! $state;
                            $record->save();

                            return;
                        }

                        $record->is_active = $state;
                        $record->save();

                        Notification::make()
                            ->title('Status Updated')
                            ->body("User status has been updated successfully.")
                            ->success()
                            ->send();
                    }),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (User $record): string => UserResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->color('success')
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->emptyStateDescription('Once you create your first user, it will appear here.');
    }
}
