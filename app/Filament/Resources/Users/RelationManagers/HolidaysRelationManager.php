<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\HolidayStatus;
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

use Filament\Actions\Action;
use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;
use Filament\Actions\ViewAction;

use App\Models\User;
use App\Models\Holiday;

class HolidaysRelationManager extends RelationManager
{
    protected static string $relationship = 'holidays';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('clinic_id')
                    ->numeric(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required(),
                Textarea::make('reason')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(HolidayStatus::class)
                    ->default('pending')
                    ->required(),
                TextInput::make('approved_by')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('holiday')
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
                    ),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('approved_by')
                    ->numeric()
                    ->sortable(),
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
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
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
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
