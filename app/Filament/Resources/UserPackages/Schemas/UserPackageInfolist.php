<?php

namespace App\Filament\Resources\UserPackages\Schemas;

use App\Enums\PackageDiscountType;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserPackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()
                ->schema([
                    Section::make('Package Details')
                        ->icon('heroicon-o-rectangle-stack')
                        ->schema([
                            TextEntry::make('package_name')
                                ->label('Package Name')
                                ->weight('bold')
                                ->size('lg')
                                ->columnSpanFull(),

                            Grid::make(3)->schema([
                                TextEntry::make('clinic.name')
                                    ->label('Clinic')
                                    ->icon('heroicon-o-building-office')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('user.name')
                                    ->label('Patient')
                                    ->icon('heroicon-o-user')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('service.name')
                                    ->label('Service')
                                    ->icon('heroicon-o-sparkles')
                                    ->badge()
                                    ->color('primary'),
                            ]),
                        ]),

                    Section::make('Session Usage')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('quantity')
                                    ->label('Total Sessions')
                                    ->suffix(' sessions')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('used_sessions')
                                    ->label('Used Sessions')
                                    ->suffix(' sessions')
                                    ->badge()
                                    ->color('warning'),

                                TextEntry::make('remaining_sessions')
                                    ->label('Remaining Sessions')
                                    ->getStateUsing(fn ($record) => $record->getRemainingSessions())
                                    ->suffix(' sessions')
                                    ->badge()
                                    ->color(fn ($record) => $record->getRemainingSessions() > 0 ? 'success' : 'danger'),

                                TextEntry::make('expired_at')
                                    ->label('Expires On')
                                    ->date()
                                    ->placeholder('No expiry')
                                    ->color(fn ($record) => $record->expired_at && $record->expired_at->isPast() ? 'danger' : 'success'),
                            ]),
                        ]),

                    Section::make('Service Snapshot (at Purchase)')
                        ->icon('heroicon-o-camera')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('service_snapshot.name')
                                    ->label('Service Name'),
                                TextEntry::make('service_snapshot.sku')
                                    ->label('SKU')
                                    ->placeholder('N/A'),
                                TextEntry::make('service_snapshot.sell_price')
                                    ->label('Price at Purchase')
                                    ->money('INR'),
                                TextEntry::make('service_snapshot.captured_at')
                                    ->label('Captured At')
                                    ->dateTime(),
                            ]),
                        ]),

                    Section::make('Notes')
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            TextEntry::make('notes')
                                ->label('Internal Notes')
                                ->markdown()
                                ->placeholder('No internal notes recorded for this package.'),
                        ])
                        ->collapsible(),
                ])
                ->columnSpan(['lg' => 2]),

            Group::make()
                ->schema([
                    Section::make('Pricing')
                        ->icon('heroicon-o-currency-rupee')
                        ->schema([
                            TextEntry::make('price_per_unit')
                                ->label('Price / Session')
                                ->money('INR'),

                            TextEntry::make('total_amount')
                                ->label('Total (Before Discount)')
                                ->money('INR'),

                            TextEntry::make('discount_type')
                                ->label('Discount Type')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state instanceof PackageDiscountType ? $state->getLabel() : $state),

                            TextEntry::make('discount_value')
                                ->label('Discount Value')
                                ->formatStateUsing(fn ($record) =>
                                    $record->discount_type === PackageDiscountType::Percentage
                                        ? $record->discount_value . '%'
                                        : '₹' . number_format($record->discount_value, 2)
                                ),

                            TextEntry::make('discount_amount')
                                ->label('Discount Amount')
                                ->money('INR')
                                ->color('danger'),

                            TextEntry::make('final_amount')
                                ->label('Final Amount')
                                ->money('INR')
                                ->weight('bold')
                                ->color('success'),
                        ]),

                    Section::make('Status')
                        ->icon('heroicon-o-signal')
                        ->schema([
                            IconEntry::make('is_active')
                                ->label('Active')
                                ->boolean()
                                ->trueColor('success')
                                ->falseColor('danger'),

                            TextEntry::make('createdBy.name')
                                ->label('Created By')
                                ->placeholder('N/A'),

                            TextEntry::make('created_at')
                                ->label('Created At')
                                ->dateTime(),
                        ]),
                ])
                ->columnSpan(['lg' => 1]),
        ])
        ->columns(3);
    }
}
