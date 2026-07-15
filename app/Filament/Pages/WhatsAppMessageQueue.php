<?php

namespace App\Filament\Pages;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppRetryService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;

class WhatsAppMessageQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'Message Queue';

    protected static ?string $navigationLabel = 'Message Queue';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.whatsapp-message-queue';

    public function table(Table $table): Table
    {
        return $table
            ->query(WhatsAppMessage::query()->orderByDesc('created_at'))
            ->poll('5s') // Polls and refreshes the table every 10 seconds
            ->defaultSort('created_at', 'desc')
            ->columns([
                // TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('conversation.display_name')
                    ->label('Contact')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('conversation.phone_number')
                    ->label('Phone Number')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn($state) => $state?->getColor() ?? 'gray'),
                TextColumn::make('text_body')
                    ->label('Content')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state?->getColor() ?? 'gray'),
                TextColumn::make('retry_count')
                    ->label('Retries')
                    ->numeric(),
                TextColumn::make('next_retry_at')
                    ->label('Next Retry')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Queued At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->infolist([
                        Section::make('Contact Info')
                            ->compact()
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('conversation.display_name')->label('Contact Name'),
                                    TextEntry::make('conversation.phone_number')->label('Phone Number'),
                                ]),
                            ]),
                        Section::make('Message Details')
                            ->compact()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('direction')
                                        ->badge()
                                        ->color(fn($state) => $state->value === 'incoming' ? 'success' : 'info'),
                                    TextEntry::make('type')
                                        ->badge()
                                        ->color('gray'),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn($state) => $state->getColor()),
                                    TextEntry::make('message_id')
                                        ->label('Meta Message ID')
                                        ->copyable(),
                                    TextEntry::make('user.name')
                                        ->label('Sent By')
                                        ->placeholder('System / Webhook'),
                                    TextEntry::make('campaign.name')
                                        ->label('Campaign')
                                        ->placeholder('None'),
                                ]),
                            ]),
                        Section::make('Content')
                            ->compact()
                            ->schema([
                                TextEntry::make('text_body')
                                    ->label('Message Text')
                                    ->prose()
                                    ->placeholder('No text content'),
                                TextEntry::make('template_name')
                                    ->label('Template Name')
                                    ->visible(fn($record) => !empty($record->template_name)),
                                KeyValueEntry::make('template_variables')
                                    ->label('Template Variables')
                                    ->visible(fn($record) => !empty($record->template_variables)),
                            ]),
                        Section::make('Status Timestamps')
                            ->compact()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('created_at')->label('Queued At')->dateTime('d M Y, h:i A'),
                                    TextEntry::make('sent_at')->label('Sent At')->dateTime('d M Y, h:i A')->placeholder('-'),
                                    TextEntry::make('delivered_at')->label('Delivered At')->dateTime('d M Y, h:i A')->placeholder('-'),
                                    TextEntry::make('read_at')->label('Read At')->dateTime('d M Y, h:i A')->placeholder('-'),
                                ]),
                            ]),
                        Section::make('Errors & Retries')
                            ->compact()
                            ->visible(fn($record) => $record->status === WhatsAppMessageStatus::Failed || $record->retry_count > 0)
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('retry_count')->label('Retries'),
                                    TextEntry::make('max_retries')->label('Max Retries'),
                                    TextEntry::make('next_retry_at')->label('Next Retry At')->dateTime('d M Y, h:i A')->placeholder('-'),
                                ]),
                                TextEntry::make('failed_reason')
                                    ->label('Failed Reason')
                                    ->color('danger')
                                    ->columnSpanFull()
                                    ->placeholder('No failure reason logged'),
                            ]),
                    ]),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn(WhatsAppMessage $record) => $record->status === WhatsAppMessageStatus::Failed)
                    ->action(function (WhatsAppMessage $record) {
                        $retryService = app(WhatsAppRetryService::class);
                        $retryService->retryMessage($record);
                        Notification::make()
                            ->title('Message Re-queued')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(WhatsAppMessage $record) => $record->status === WhatsAppMessageStatus::Pending)
                    ->action(function (WhatsAppMessage $record) {
                        $record->update(['status' => WhatsAppMessageStatus::Failed, 'failed_reason' => 'Cancelled by user']);
                        Notification::make()
                            ->title('Message Cancelled')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('retry_all')
                        ->label('Retry Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $retryService = app(WhatsAppRetryService::class);
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === WhatsAppMessageStatus::Failed) {
                                    $retryService->retryMessage($record);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("$count Messages Re-queued")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryAllFailed')
                ->label('Retry All Failed')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $retryService = app(WhatsAppRetryService::class);
                    $processed = $retryService->processRetryQueue();
                    Notification::make()
                        ->title("Processed $processed failed messages")
                        ->success()
                        ->send();
                }),
        ];
    }
}
