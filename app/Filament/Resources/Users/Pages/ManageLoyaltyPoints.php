<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\LoyaltyPointTransaction;
use App\Models\Setting;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Tables\Enums\FiltersLayout;

class ManageLoyaltyPoints extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'loyaltyTransactions';

    protected static ?string $navigationLabel = 'Loyalty Points';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if (!$record) {
            return false;
        }
        return $record->getRoleNames()->contains('client');
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getOwnerRecord();
        $balance = $record->getLoyaltyBalance();
        $minRedeem = Setting::getLoyaltyMinRedeem();
        $canRedeem = $balance >= $minRedeem;

        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),

            Action::make('balance_info')
                ->label("Balance: {$balance} pts")
                ->icon('heroicon-o-star')
                ->color($canRedeem ? 'success' : 'warning')
                ->badge($canRedeem ? 'Redeemable' : "Need {$minRedeem} pts")
                ->disabled(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(app_datetime_format())
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'earn'    => 'success',
                        'redeem'  => 'danger',
                        'reverse' => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('points')
                    ->label('Points')
                    ->formatStateUsing(function ($state) {
                        $prefix = $state > 0 ? '+' : '';
                        return $prefix . number_format($state);
                    })
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' pts'),

                TextColumn::make('description')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('invoicePayment.transaction_id')
                    ->label('Payment Ref')
                    ->placeholder('-'),

                TextColumn::make('creator.first_name')
                    ->label('By')
                    ->formatStateUsing(fn ($record) => $record->creator ? $record->creator->name : 'System')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'earn'    => 'Earned',
                        'redeem'  => 'Redeemed',
                        'reverse' => 'Reversed',
                    ]),
            ],layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            );
    }
}
