<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Schema;
use Filament\Forms\Components\KeyValue;

use Filament\Infolists;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\Split;
use Filament\Support\Enums\TextSize;
use Filament\Forms\Components\TextInput;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Invoice Details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Invoice #')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->prefix('#'),
                        TextEntry::make('clinic.name')->weight(FontWeight::Bold)->label('Clinic'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'paid' => 'success',
                                'draft' => 'gray',
                                'pending' => 'warning',
                                'cancelled' => 'danger',
                                default => 'info',
                            }),
                        TextEntry::make('invoice_date')->date(),
                        TextEntry::make('payment_mode')->badge()->placeholder('-'),
                        TextEntry::make('source_note')->label('Note')->placeholder('-'),
                        TextEntry::make('created_at')->dateTime()->label('Created At'),
                        TextEntry::make('updated_at')->dateTime()->label('Last Updated'),
                    ])
                    ->columns(4),

                Section::make('Client Details')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('client.name')->weight(FontWeight::Bold)->label('Name'),
                        TextEntry::make('client.mobile')->label('Phone')->icon('heroicon-m-phone'),
                        TextEntry::make('client.email')->label('Email')->icon('heroicon-m-envelope'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Line Items')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(5)->schema([
                                    TextEntry::make('product.name')->label('Product'),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('unit_price')->label('Price')->money('INR'),
                                    // TextEntry::make('discount_value')
                                    //     ->label('Discount')
                                    //     ->formatStateUsing(function ($record) {
                                    //         $actualDiscount = $record->valid_discount_amount ?? 0;
                                    //         if ($record->discount_type === 'percentage') {
                                    //             return ($record->discount_value ?? 0) . '% (₹' . number_format($actualDiscount, 2) . ')';
                                    //         }
                                    //         return '₹' . number_format($actualDiscount, 2);
                                    //     })
                                    //     ->badge()
                                    //     ->color('info'),
                                    TextEntry::make('gst_amount')
                                        ->label('GST')
                                        ->formatStateUsing(fn ($record) => ($record->gst_percentage ?? 0) . '% (₹' . number_format($record->gst_amount ?? 0, 2) . ')')
                                        ->badge()
                                        ->color('info'),
                                    TextEntry::make('line_total')->label('Total')->money('INR')->weight(FontWeight::Bold),
                                ]),
                            ]),
                    ])
                    ->collapsible(),

                    Grid::make(12)->schema([
                        Group::make()->columnSpan(8),
                        Section::make()
                            ->schema([
                                TextEntry::make('subtotal')->money('INR')->label('Subtotal')->inlineLabel(),
                                TextEntry::make('taxable_value')->money('INR')->label('Taxable Value')->inlineLabel(),
                                TextEntry::make('gst_total')->money('INR')->label('GST Total')->inlineLabel(),
                                TextEntry::make('discount_total')
                                        ->label('Discount')
                                        ->inlineLabel()
                                        ->formatStateUsing(function ($record) {
                                            $actualDiscount = $record->discount_total ?? 0;

                                            if ($record->package_id && $record->package) {
                                                $type = $record->package->discount_type instanceof \App\Enums\PackageDiscountType
                                                    ? $record->package->discount_type->value
                                                    : $record->package->discount_type;

                                                if ($type === 'percentage') {
                                                    $percent = $record->package->discount_value ?? 0;
                                                    return $percent . '% (₹' . number_format($actualDiscount, 2) . ')';
                                                }
                                            }

                                            return '₹' . number_format($actualDiscount, 2);
                                        })
                                        ->color('success'),
                                TextEntry::make('grand_total')
                                    ->money('INR')
                                    ->label('Grand Total')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color('primary')
                                    ->inlineLabel(),

                            ])
                            ->columnSpan(4),
                    ]),
            ])
            ->columns(1);
    }
}
