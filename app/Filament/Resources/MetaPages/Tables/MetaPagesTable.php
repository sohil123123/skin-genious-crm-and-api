<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages\Tables;

use App\Filament\Resources\MetaPages\MetaPageResource;
use App\Models\MetaPage;
use App\Services\Meta\Exceptions\MetaApiException;
use App\Services\Meta\MetaGraphApiService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The connected Pages list.
 *
 * Note there is no column for the access token, and there never should be: it
 * grants read access to every lead the Page has collected.
 */
class MetaPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('page_name')
            ->columns([
                TextColumn::make('page_name')
                    ->label('Page')
                    ->description(fn (MetaPage $record): string => 'ID ' . $record->page_id)
                    ->searchable(['page_name', 'page_id'])
                    ->sortable()
                    ->placeholder('Not yet resolved')
                    ->weight('medium'),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                // Whether a token exists is operationally important; its value
                // is not shown anywhere.
                IconColumn::make('access_token')
                    ->label('Token')
                    ->boolean()
                    ->getStateUsing(fn (MetaPage $record): bool => $record->hasUsableToken())
                    ->tooltip(fn (MetaPage $record): string => $record->hasUsableToken()
                        ? 'A token is saved for this Page.'
                        : 'No token saved — leads from this Page cannot be fetched.'),

                TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime(config('leads.display.datetime_format'))
                    ->timezone(config('leads.display.timezone'))
                    ->placeholder('Not subscribed')
                    ->toggleable(),

                // A Page that has stopped delivering is the first symptom of a
                // lapsed subscription or an expired token.
                TextColumn::make('last_lead_at')
                    ->label('Last lead')
                    ->since()
                    ->placeholder('None yet')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All pages'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('testConnection')
                        ->label('Test connection')
                        ->icon('heroicon-o-signal')
                        ->color('info')
                        // Defers to whatever policy Shield generated for this
                        // resource, rather than naming a permission string that
                        // would silently drift if the model were renamed.
                        ->visible(fn (MetaPage $record): bool => MetaPageResource::canEdit($record))
                        ->action(function (MetaPage $record): void {
                            $result = app(MetaGraphApiService::class)->testConnection($record);

                            // A resolved name is worth keeping — it saves typing
                            // it by hand and is shown on every lead.
                            if ($result['ok'] && filled($result['page_name'])) {
                                $record->forceFill(['page_name' => $result['page_name']])->save();
                            }

                            Notification::make()
                                ->title($result['ok'] ? 'Connection successful' : 'Connection failed')
                                ->body($result['message'])
                                ->status($result['ok'] ? ($result['subscribed'] ? 'success' : 'warning') : 'danger')
                                ->send();
                        }),

                    Action::make('subscribe')
                        ->label('Subscribe to leadgen')
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Subscribes this Page to leadgen notifications so new leads reach the CRM in real time.')
                        // Defers to whatever policy Shield generated for this
                        // resource, rather than naming a permission string that
                        // would silently drift if the model were renamed.
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

                    EditAction::make(),

                    DeleteAction::make()
                        ->modalDescription('Leads already imported from this Page are kept. New leads from it will stop being accepted.'),
                ]),
            ])
            ->emptyStateHeading('No Meta Pages connected')
            ->emptyStateDescription('Connect a Page so its lead forms can reach the CRM in real time.')
            ->emptyStateIcon('heroicon-o-flag');
    }
}
