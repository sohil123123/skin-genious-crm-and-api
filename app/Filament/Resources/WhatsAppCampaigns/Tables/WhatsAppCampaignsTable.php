<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Tables;

use App\Models\WhatsAppCampaign;
use App\Services\WhatsAppCampaignService;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class WhatsAppCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('message_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'media' => 'warning',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'template')),

                TextColumn::make('template.name')
                    ->label('Template / Media')
                    ->badge()
                    ->formatStateUsing(fn (WhatsAppCampaign $record): string => match ($record->message_type) {
                        'media' => $record->mediaLibrary?->name ?? 'Media File',
                        default => $record->template?->name ?? '-',
                    })
                    ->color(fn (WhatsAppCampaign $record): string => match ($record->message_type) {
                        'media' => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('total_recipients')
                    ->label('Recipients')
                    ->numeric(),

                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('delivered_count')
                    ->label('Delivered')
                    ->numeric()
                    ->color('info'),

                TextColumn::make('read_count')
                    ->label('Read')
                    ->numeric()
                    ->color('primary'),

                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric()
                    ->color('danger'),

                TextColumn::make('scheduled_at')
                    ->dateTime(app_datetime_format())
                    ->sortable()
                    ->placeholder('Immediate'),

                TextColumn::make('created_at')
                    ->dateTime(app_date_format())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sending' => 'Sending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (WhatsAppCampaign $record) => $record->isEditable()),

                Action::make('send')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Campaign Now')
                    ->modalDescription('This will start sending messages to all recipients immediately.')
                    ->visible(fn (WhatsAppCampaign $record) => in_array($record->status?->value ?? $record->status, ['draft', 'scheduled']))
                    ->action(function (WhatsAppCampaign $record) {
                        $service = app(WhatsAppCampaignService::class);
                        $result = $service->executeCampaign($record);

                        if ($result) {
                            Notification::make()
                                ->title('Campaign Started 🚀')
                                ->body('Messages are being sent in the background.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to Start')
                                ->body('Campaign could not be started.')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (WhatsAppCampaign $record) {
                        $service = app(WhatsAppCampaignService::class);
                        $service->duplicateCampaign($record);

                        Notification::make()
                            ->title('Campaign Duplicated')
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WhatsAppCampaign $record) => $record->isCancellable())
                    ->action(function (WhatsAppCampaign $record) {
                        $record->update(['status' => 'cancelled']);

                        Notification::make()
                            ->title('Campaign Cancelled')
                            ->warning()
                            ->send();
                    }),

                DeleteAction::make(),
            ]);
    }
}
