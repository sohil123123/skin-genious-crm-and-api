<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\CheckboxList;

use Filament\Actions\Action;
use Spatie\Permission\Models\Permission;

use Filament\Notifications\Notification;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->heading('Roles')
            // ->description('Manage your roles here.')
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('guard_name')->searchable()->toggleable(),
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
            ->recordActions([
                EditAction::make()->hidden(fn ($record) => $record->name === 'admin'),
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->name === 'admin')
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Role Deleted 🎉')
                            ->body("The Role **{$record->name}** has been removed successfully.")
                            ->success();
                    }),

                Action::make('permissions')
                    ->label('Permissions')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->slideOver() // or ->modalHeading("Manage permissions")
                    // ->modalHeading("Manage permissions")
                    ->form([
                        CheckboxList::make('permissions')
                            ->label('Manage Permissions')
                            ->options(Permission::all()->pluck('name', 'id'))
                            ->columns(3)
                            ->searchable()
                            ->bulkToggleable()
                            ->default(fn($record) => $record->permissions()->pluck('id')->toArray()),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->name === 'admin')
                            return; // Do nothing for admin role
                        $record->syncPermissions($data['permissions'] ?? []);

                        // ✅ Show notification after saving
                        Notification::make()
                            ->title('Permissions updated')
                            ->body("Permissions for role **{$record->name}** have been saved successfully.")
                            ->success()
                            ->send();
                    })
                    // ->disabled(fn ($record) => $record->name === 'admin'),
                    ->hidden(fn ($record) => $record->name === 'admin'),
            ])
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ])
            ->emptyStateDescription('Once you create your first role, it will appear here.');
    }
}
