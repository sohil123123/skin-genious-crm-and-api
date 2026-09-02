<?php

namespace App\Filament\Resources\WhatsAppScheduledMessages\Tables;

use App\Models\WhatsAppScheduledMessage;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class WhatsAppScheduledMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at', 'asc')
            ->columns([
                TextColumn::make('recipient_name')
                    ->label('Recipient')
                    ->weight('bold')
                    ->searchable(['phone_number']),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'text' => 'gray',
                        'template' => 'info',
                        'media' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('scheduled_at')
                    ->dateTime(app_datetime_format())
                    ->sortable(),

                TextColumn::make('is_recurring')
                    ->label('Recurring')
                    ->formatStateUsing(fn ($state, $record) => $state ? '🔁 ' . ucfirst($record->recurrence_rule) : 'No')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('last_sent_at')
                    ->dateTime(app_datetime_format())
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime(app_date_format())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WhatsAppScheduledMessage $record) => $record->status === 'pending')
                    ->action(function (WhatsAppScheduledMessage $record) {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()->title('Scheduled message cancelled')->warning()->send();
                    }),
                EditAction::make()->visible(fn (WhatsAppScheduledMessage $record) => $record->status === 'pending'),
                DeleteAction::make(),
            ]);
    }
}
