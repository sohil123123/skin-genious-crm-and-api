<?php

namespace App\Filament\Resources\Clinics\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Table;

use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
// use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

use App\Models\Clinic;

class ClinicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('name')
            // ->columns([
            //     Split::make([
            //         ImageColumn::make('logo')
            //             ->disk('public')
            //             ->circular()
            //             // ->size(40)
            //             ->defaultImageUrl(asset('images/clinic_plaseholder.png'))
            //             ->sortable(false),

            //         Stack::make([
            //             TextColumn::make('name')->weight(FontWeight::Bold)->searchable()->sortable(),
            //             TextColumn::make('full_address')->color('gray')->size(TextSize::Small)->searchable(),
            //         ])->space(1),

            //         Stack::make([
            //             TextColumn::make('phone')->icon('heroicon-o-phone-arrow-up-right')->color('gray')->size(TextSize::Small),
            //             TextColumn::make('email')->icon('heroicon-o-envelope')->color('gray')->size(TextSize::Small),
            //         ])
            //         ->alignment(Alignment::End)
            //         ->hiddenOn('sm')
            //         ->space(1),

            //         TextColumn::make('manager.name')
            //             ->label('Manager')
            //             ->sortable()
            //             ->alignment(Alignment::End)
            //             ->hiddenOn('md'),

            //         ToggleColumn::make('is_active')->alignment(Alignment::End),
            //     ])
            //         ->from('md'),
            //         // ->collapsible(),
            // ])
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->columns([
                // ImageColumn::make('logo')
                //     ->imageSize(80)
                //     ->label('Logo')
                //     ->disk('public')
                //     ->circular()
                //     ->defaultImageUrl(asset('images/clinic_plaseholder.png'))
                //     ->action(
                //         Action::make('viewPhoto')
                //             ->modalHeading('Photo Preview')
                //             ->modalContent(fn ($record) =>
                //                 view('filament.photo-preview', ['photo' => $record->logo])
                //             )
                //             ->modalSubmitAction(false)
                //             ->modalCancelActionLabel('Close')
                //     ),
                TextColumn::make('manager.name')->label('Manager')->badge()->color('info')->sortable()->placeholder('Not Assigned'),
                TextColumn::make('name')->weight(FontWeight::Bold)->wrap()->searchable()->sortable(),
                TextColumn::make('state')
                    ->label('State')
                    ->formatStateUsing(fn($state) => config('project.indian_states.' . $state, $state))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                BadgeColumn::make('therapists_count')
                    ->label('Therapists')
                    ->counts('therapists')
                    ->icon('heroicon-o-user-group')
                    ->iconPosition('before')
                    ->color(fn($state) => match (true) {
                        $state >= 50 => 'success',
                        $state >= 20 => 'warning',
                        default => 'danger',
                    }),
                BadgeColumn::make('clients_count')
                    ->label('Clients')
                    ->counts('clients')
                    ->icon('heroicon-o-user-group')
                    ->iconPosition('before')
                    ->color(fn($state) => match (true) {
                        $state >= 50 => 'success',
                        $state >= 20 => 'warning',
                        default => 'danger',
                    }),

                // TextColumn::make('full_address')
                //     ->label('Address')
                //     ->searchable(['address_line1', 'address_line2', 'city', 'pincode'])
                //     ->toggleable()
                //     ->fontFamily(FontFamily::Mono)
                //     ->wrap(),
                //     // ->limit(35)
                //     // ->tooltip(function (TextColumn $column): ?string {
                //     //     $state = $column->getState();
                //     //     if (strlen($state) <= $column->getCharacterLimit()) {
                //     //         return null;
                //     //     }
                //     //     return $state;
                //     // }),
                TextColumn::make('start_time')->time(app_time_format())->sortable(),
                TextColumn::make('end_time')->time(app_time_format())->sortable(),
                BadgeColumn::make('number_of_beds')->color('info')->searchable()->sortable(),
                TextColumn::make('face_scan_machine')->label('Machine')->placeholder('Not specified')->toggleable(),
                TextColumn::make('google_map_link')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->searchable()->placeholder('-'),
                TextColumn::make('email')->label('Email address')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')->searchable()->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->onColor('success')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    // ->visible(auth()->user()->can('toggle_clinic_status'))
                    ->afterStateUpdated(function ($state, $record) {
                        if (!auth()->user()->can('toggle_clinic_status')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update clinic status.')
                                // ->color('danger')
                                ->danger()
                                ->send();

                            // revert change
                            $record->is_active = !$state;
                            $record->save();
                            return;
                        }

                        // Save the new state
                        $record->is_active = $state;
                        $record->save();

                        Notification::make()
                            ->title('Status Updated')
                            ->body("User status has been updated successfully.")
                            ->success()
                            // ->color('success')
                            ->send();
                    }),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('is_active')
                    ->options([
                        1 => 'Active',
                        0 => 'Deactive',
                    ])
                    ->label('Status')
                    ->searchable(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                // One menu rather than five controls per row. Six columns of
                // clinic data were competing with a strip of icons whose
                // meaning only a tooltip could explain; in a menu each item
                // carries its own label and says what it does.
                ActionGroup::make([
                    Action::make('clients')
                        ->label('Manage clients')
                        ->icon('heroicon-o-users')
                        ->url(fn($record) => route('filament.admin.resources.clinics.clients', ['record' => $record])),

                    Action::make('holiday')
                        ->label('Manage holidays')
                        ->icon('heroicon-o-no-symbol')
                        // No longer red: inside a menu, colour reads as
                        // consequence, and managing holidays destroys nothing.
                        ->url(fn($record) => route('filament.admin.resources.clinics.holidays', ['record' => $record])),

                    ViewAction::make(),

                    EditAction::make(),

                    RestoreAction::make()
                        ->successNotification(
                            Notification::make()
                                ->title('Clinic Restored 🎉')
                                ->body('The selected clinics have been restored successfully.')
                                ->success()
                        ),

                    DeleteAction::make()
                        ->successNotification(function ($record) {
                            return Notification::make()
                                ->title('Clinic Deleted 🎉')
                                ->body("The User **{$record->name}** has been removed successfully.")
                                ->success();
                        }),
                ]),
            ])
            ->emptyStateDescription('Once you create your first clinic, it will appear here.');
    }
}
