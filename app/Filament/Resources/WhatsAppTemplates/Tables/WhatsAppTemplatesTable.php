<?php

namespace App\Filament\Resources\WhatsAppTemplates\Tables;

use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class WhatsAppTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'MARKETING' => 'info',
                        'UTILITY' => 'primary',
                        'AUTHENTICATION' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('language')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'APPROVED' => 'success',
                        'PENDING' => 'warning',
                        'REJECTED' => 'danger',
                        'PAUSED' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'APPROVED' => 'heroicon-o-check-circle',
                        'PENDING' => 'heroicon-o-clock',
                        'REJECTED' => 'heroicon-o-x-circle',
                        'PAUSED' => 'heroicon-o-pause-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('quality_score')
                    ->label('Quality')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'GREEN' => 'success',
                        'YELLOW' => 'warning',
                        'RED' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->dateTime(app_datetime_format())
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime(app_datetime_format())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime(app_datetime_format())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'APPROVED' => 'Approved',
                        'PENDING' => 'Pending',
                        'REJECTED' => 'Rejected',
                        'PAUSED' => 'Paused',
                    ]),

                SelectFilter::make('category')
                    ->options([
                        'MARKETING' => 'Marketing',
                        'UTILITY' => 'Utility',
                        'AUTHENTICATION' => 'Authentication',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (WhatsAppTemplate $record) {
                        // Delete from Meta Cloud API before local delete
                        $whatsAppService = app(WhatsAppService::class);
                        $result = $whatsAppService->deleteTemplate($record->name);

                        if (!$result['success']) {
                            Notification::make()
                                ->title('Meta API Warning ⚠️')
                                ->body('Could not delete from Meta: ' . ($result['error'] ?? 'Unknown error') . '. Removing locally only.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('WhatsApp Template Deleted 🎉')
                            ->body("The WhatsApp Template **{$record->name}** has been removed successfully.")
                            ->success();
                    }),
            ]);
        // ->bulkActions([
        //     BulkActionGroup::make([
        //         DeleteBulkAction::make(),
        //     ]),
        // ]);
    }
}
