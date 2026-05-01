<?php

namespace App\Filament\Resources\InvoicePayments\Schemas;

use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use App\Models\InvoicePayment;


class InvoicePaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Invoice Information')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('invoice_id')
                                    ->label('Invoice')
                                    ->relationship('invoice', 'id', modifyQueryUsing: function ($query) {
                                        if (!auth()->user()->hasRole('super_admin')) {
                                            $query->where('clinic_id', auth()->user()->clinic_id);
                                        }
                                        return $query->whereIn('status', ['unpaid', 'partial', 'pending']);
                                    })
                                    ->getOptionLabelFromRecordUsing(fn (Invoice $record) => "Invoice #{$record->id} — {$record->client?->name} (₹" . number_format($record->grand_total, 2) . ")")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (?InvoicePayment $record) => $record !== null)
                                    // Hide when inside an invoice's sub-navigation
                                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $invoice = Invoice::find($state);
                                            if ($invoice) {
                                                $set('remaining_balance_display', "₹" . number_format($invoice->grand_total - $invoice->amount_paid, 2));
                                            }
                                        } else {
                                            $set('remaining_balance_display', '₹0.00');
                                        }
                                    }),

                                Placeholder::make('remaining_balance_display')
                                    ->label('Current Balance Due')
                                    ->extraAttributes(['class' => 'text-danger-600 font-bold text-xl'])
                                    ->content(function (Get $get, $livewire) {
                                        // If in relationship context, use the owner record
                                        if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                            $invoice = $livewire->getOwnerRecord();
                                        } else {
                                            $invoiceId = $get('invoice_id');
                                            if (!$invoiceId) return 'Select an invoice first';
                                            $invoice = Invoice::find($invoiceId);
                                        }

                                        if (!$invoice) return 'Invoice not found';
                                        return new HtmlString('<span style="color: #dc2626; font-size: 1.25rem; font-weight: bold;">₹' . number_format($invoice->grand_total - $invoice->amount_paid, 2) . '</span>');
                                    }),
                            ]),
                    ]),

                Section::make('Payment Details')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    DatePicker::make('payment_date')
                                        ->label('Payment Date')
                                        ->default(now())
                                        ->required()
                                        ->prefixIcon('heroicon-o-calendar'),

                                    // TextInput::make('amount')
                                    //     ->label('Payment Amount')
                                    //     ->numeric()
                                    //     ->required()
                                    //     ->minValue(0.01)
                                    //     ->prefix('₹')
                                    //     ->placeholder('0.00')
                                    //     ->rules([
                                    //         function (Get $get, $livewire) {
                                    //             return function (string $attribute, $value, $fail) use ($get, $livewire) {
                                    //                 if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                    //                     $invoice = $livewire->getOwnerRecord();
                                    //                 } else {
                                    //                     $invoiceId = $get('invoice_id');
                                    //                     if (!$invoiceId) return;
                                    //                     $invoice = Invoice::find($invoiceId);
                                    //                 }

                                    //                 if (!$invoice) return;

                                    //                 $remaining = $invoice->grand_total - $invoice->amount_paid;

                                    //                 // Adjust for existing record if editing
                                    //                 if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                                    //                     $remaining += (float) $livewire->getRecord()->amount;
                                    //                 }

                                    //                 if ($value > $remaining) {
                                    //                     $fail("The payment amount cannot exceed the remaining balance of ₹" . number_format($remaining, 2));
                                    //                 }
                                    //             };
                                    //         },
                                    //     ]),

                                    // Select::make('payment_method')
                                    //     ->label('Payment Method')
                                    //     ->options([
                                    //         'cash' => 'Cash',
                                    //         'card' => 'Card',
                                    //         'upi' => 'UPI',
                                    //         'bank_transfer' => 'Bank Transfer',
                                    //         'other' => 'Other',
                                    //     ])
                                    //     ->required()
                                    //     ->live()
                                    //     ->prefixIcon('heroicon-o-credit-card'),

                                    // TextInput::make('reference_number')
                                    //     ->label('Reference Number / TXN ID')
                                    //     ->placeholder('Transaction ID, Check #, etc.')
                                    //     ->hidden(fn (Get $get) => $get('payment_method') === 'cash')
                                    //     ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null)
                                    //     ->prefixIcon('heroicon-o-hashtag'),

                                    // TextInput::make('transaction_id')
                                    //     ->label('Internal ID')
                                    //     ->placeholder('Auto-generated')
                                    //     ->disabled()
                                    //     ->dehydrated(false)
                                    //     ->visible(fn ($record) => $record !== null)
                                    //     ->prefixIcon('heroicon-o-finger-print'),
                                ])->columns(2)->hidden(fn (?InvoicePayment $record) => $record !== null)->columnSpanFull(),

                                Repeater::make('payments')
                                    ->label('Payment Methods')
                                    ->schema([
                                        Select::make('payment_method')
                                            ->label('Method')
                                            ->options([
                                                'cash' => 'Cash',
                                                'card' => 'Card',
                                                'upi' => 'UPI',
                                                'bank_transfer' => 'Bank Transfer',
                                                'other' => 'Other',
                                            ])
                                            ->required()
                                            ->live()
                                            ->prefixIcon('heroicon-o-credit-card'),

                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.01)
                                            ->prefix('₹')
                                            ->live(onBlur: true),

                                        TextInput::make('reference_number')
                                            ->label('Ref / TXN ID')
                                            ->placeholder('Optional')
                                            ->hidden(fn (Get $get) => $get('payment_method') === 'cash')
                                            // ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null)
                                            ->prefixIcon('heroicon-o-hashtag'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add Payment Split')
                                    ->live()
                                    ->visible(fn (?InvoicePayment $record) => $record === null)
                                    ->columnSpanFull()
                                    ->rules([
                                        function (Get $get, $livewire) {
                                            return function (string $attribute, $value, $fail) use ($get, $livewire) {
                                                if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                                    $invoice = $livewire->getOwnerRecord();
                                                } else {
                                                    $invoiceId = $get('invoice_id');
                                                    if (!$invoiceId) return;
                                                    $invoice = Invoice::find($invoiceId);
                                                }
                                                if (!$invoice) return;

                                                $remaining = $invoice->grand_total - $invoice->amount_paid;
                                                $total = collect($value)->sum(fn($p) => floatval($p['amount'] ?? 0));

                                                if (round($total, 2) > round($remaining, 2)) {
                                                    $fail("Total amount (₹" . number_format($total, 2) . ") exceeds remaining balance (₹" . number_format($remaining, 2) . ").");
                                                }
                                                if ($total <= 0) {
                                                    $fail("Total payment amount must be greater than 0.");
                                                }
                                            };
                                        }
                                    ]),

                                Placeholder::make('total_paid_preview')
                                    ->label('Total Payment Scheduled')
                                    ->content(function (Get $get) {
                                        $payments = $get('payments') ?? [];
                                        $total = collect($payments)->sum(fn($p) => floatval($p['amount'] ?? 0));
                                        return new HtmlString('<span class="text-xl font-bold text-success-600">₹' . number_format((float) $total, 2) . '</span>');
                                    })
                                    ->visible(fn (?InvoicePayment $record) => $record === null)
                                    ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Payment Notes')
                            ->placeholder('Add any relevant notes about this payment...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
