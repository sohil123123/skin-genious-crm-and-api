<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Illuminate\Contracts\View\View;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            // ->placeholder(fn () => view('custom-table-placeholder', [
            //     'thead' => $table->renderHeader(), // Or manually render if needed
            //     'rows' => 5,
            //     'widths' => ['w-24', 'w-16', 'w-32', 'w-20', 'w-12', 'w-28', 'w-20'],
            // ]))
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name'])
                    ->formatStateUsing(fn ($record) => trim($record->first_name . ' ' . ($record->last_name ?? ''))),
                TextColumn::make('mobile')->searchable(),
                TextColumn::make('gender')->badge()->placeholder('-')->toggleable(),
                TextColumn::make('roles.name')->badge()->color('primary')->searchable()->sortable()->toggleable(),
                TextColumn::make('email')->label('Email address')->searchable()->toggleable(),
                ToggleColumn::make('is_active')->label('Status')->toggleable()->sortable()
                    ->action(function ($record) {
                        $record->update([
                            'is_active' => ! $record->is_active,
                        ]);
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->groups([
                Group::make('first_name')->label('Name')->collapsible(),
                Group::make('gender')->label('Gender')->collapsible(),
            ])
            ->emptyStateDescription('Once you create your first user, it will appear here.');
            // ->contentGrid([
            //     'md' => 2,
            //     'xl' => 3,
            // ])
    }
}
