<?php

namespace App\Filament\Resources\WhatsAppMediaLibrary\Tables;

use App\Models\WhatsAppMediaLibrary;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class WhatsAppMediaLibraryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Preview')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn($record) => match ($record->type) {
                        'video' => null,
                        'document' => null,
                        'audio' => null,
                        default => null,
                    })
                    ->visible(fn($record) => $record?->type === 'image'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'image' => 'success',
                        'video' => 'info',
                        'document' => 'warning',
                        'audio' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'image' => '📷 Image',
                        'video' => '🎥 Video',
                        'document' => '📄 Document',
                        'audio' => '🎵 Audio',
                        default => $state,
                    }),

                TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn($record) => $record->human_file_size),

                TextColumn::make('meta_media_id')
                    ->label('Meta ID')
                    ->copyable()
                    ->placeholder('Not uploaded')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'image' => 'Image',
                        'video' => 'Video',
                        'document' => 'Document',
                        'audio' => 'Audio',
                    ]),
            ])
            ->actions([
                Action::make('uploadToMeta')
                    ->label('Upload to Meta')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('info')
                    ->visible(fn(WhatsAppMediaLibrary $record) => !$record->isUploadedToMeta())
                    ->requiresConfirmation()
                    ->action(function (WhatsAppMediaLibrary $record) {
                        $whatsAppService = app(WhatsAppService::class);
                        $filePath = storage_path('app/public/' . $record->file_path);

                        $mediaId = $whatsAppService->uploadMedia($filePath, $record->mime_type ?? 'application/octet-stream');

                        if ($mediaId) {
                            $record->update([
                                'meta_media_id' => $mediaId,
                                'meta_uploaded_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Uploaded to Meta ✅')
                                ->body("Media ID: {$mediaId}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Upload Failed')
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
