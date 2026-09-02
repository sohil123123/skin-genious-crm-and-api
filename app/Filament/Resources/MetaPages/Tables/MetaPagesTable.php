<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages\Tables;

use App\Filament\Resources\MetaPages\MetaPageResource;
use App\Models\MetaPage;
use App\Services\Meta\Exceptions\MetaApiException;
use App\Services\Meta\MetaGraphApiService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The Pages Meta has actually sent leads from.
 *
 * There is no access-token column and never should be: a Page token grants read
 * access to every lead that Page has collected.
 */
class MetaPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_lead_at', 'desc')
            ->columns([
                TextColumn::make('page_name')
                    ->label('Page')
                    ->description(fn (MetaPage $record): string => 'ID ' . $record->page_id)
                    ->searchable(['page_name', 'page_id'])
                    ->sortable()
                    ->placeholder('Awaiting first sync')
                    ->weight('medium'),

                // A discovered Page is stamped with the default clinic on
                // creation, so this normally shows a real clinic. The
                // placeholder only appears for a Page whose clinic was deleted,
                // or one created before a default was configured.
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color(fn (MetaPage $record): string => $record->usesDefaultClinic() ? 'gray' : 'info')
                    ->placeholder('Default clinic')
                    ->tooltip(fn (MetaPage $record): ?string => $record->usesDefaultClinic()
                        ? 'No clinic set. Leads fall back to the default on Meta Lead Settings.'
                        : null),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_lead_at')
                    ->label('Last lead')
                    ->since()
                    ->placeholder('None yet')
                    ->sortable(),

                TextColumn::make('last_synced_at')
                    ->label('Last sync')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Discovered')
                    ->dateTime(app_datetime_format())
                    ->timezone(app_timezone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All pages'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('refresh')
                        ->label('Refresh from Meta')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn (MetaPage $record): bool => MetaPageResource::canEdit($record))
                        ->action(function (MetaPage $record): void {
                            $result = app(MetaGraphApiService::class)->testConnection($record);

                            if ($result['ok'] && filled($result['page_name'])) {
                                $record->forceFill([
                                    'page_name' => $result['page_name'],
                                    'last_synced_at' => now(),
                                ])->save();
                            }

                            Notification::make()
                                ->title($result['ok'] ? 'Page refreshed' : 'Could not reach Meta')
                                ->body($result['message'])
                                ->status($result['ok'] ? 'success' : 'danger')
                                ->send();
                        }),

                    // Optional, not part of setup: subscription is normally
                    // handled once when the app is connected to the Page in
                    // Business Manager. Kept because it is the fastest way to
                    // repair a Page that has silently stopped delivering.
                    Action::make('subscribe')
                        ->label('Subscribe to leadgen')
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Asks Meta to send this Page\'s new leads to the CRM. Safe to run more than once.')
                        ->visible(fn (MetaPage $record): bool => MetaPageResource::canEdit($record))
                        ->action(function (MetaPage $record): void {
                            try {
                                $subscribed = app(MetaGraphApiService::class)->subscribePageToLeadgen($record);
                            } catch (MetaApiException $exception) {
                                Notification::make()
                                    ->title('Could not subscribe')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if ($subscribed) {
                                $record->forceFill(['subscribed_at' => now()])->save();
                            }

                            Notification::make()
                                ->title($subscribed ? 'Subscribed to leadgen' : 'Meta did not confirm the subscription')
                                ->status($subscribed ? 'success' : 'warning')
                                ->send();
                        }),

                    EditAction::make()->label('Clinic & status'),
                ]),
            ])
            ->emptyStateHeading('No Meta Pages yet')
            ->emptyStateDescription('Pages appear here on their own, the first time a lead arrives from one. Nothing needs creating.')
            ->emptyStateIcon('heroicon-o-flag');
    }
}
