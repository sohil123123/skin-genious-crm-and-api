<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    // public function form(Schema $schema): Schema
    // {
    //     return $schema;
    //         // ->schema([
    //         //     Placeholder::make('remaining_balance_info')
    //         //         ->label('Remaining Balance')
    //         //         ->content(function () {
    //         //             $invoice = $this->getOwnerRecord();
    //         //             return new HtmlString('<strong>₹' . number_format($invoice->grand_total - $invoice->amount_paid, 2) . '</strong>');
    //         //         }),

    //         //     DatePicker::make('payment_date')
    //         //         ->label('Payment Date')
    //         //         ->default(now())
    //         //         ->required(),

    //         //     TextInput::make('amount')
    //         //         ->label('Amount')
    //         //         ->numeric()
    //         //         ->required()
    //         //         ->minValue(0.01)
    //         //         ->rules([
    //         //             function () {
    //         //                 return function (string $attribute, $value, $fail) {
    //         //                     $invoice = $this->getOwnerRecord();
    //         //                     $currentPaymentAmount = $this->getRecord()?->amount ?? 0;
    //         //                     $remaining = $invoice->grand_total - ($invoice->amount_paid - $currentPaymentAmount);

    //         //                     if ($value > $remaining) {
    //         //                         $fail("The payment amount cannot exceed the remaining balance of ₹" . number_format($remaining, 2));
    //         //                     }
    //         //                 };
    //         //             },
    //         //         ]),

    //         //     Select::make('payment_method')
    //         //         ->label('Payment Method')
    //         //         ->options([
    //         //             'cash' => 'Cash',
    //         //             'card' => 'Card',
    //         //             'upi' => 'UPI',
    //         //             'bank_transfer' => 'Bank Transfer',
    //         //             'other' => 'Other',
    //         //         ])
    //         //         ->required()
    //         //         ->live(),

    //         //     TextInput::make('reference_number')
    //         //         ->label('Reference Number')
    //         //         ->placeholder('e.g. Transaction ID, Check #')
    //         //         ->hidden(fn (Get $get) => $get('payment_method') === 'cash')
    //         //         ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null),

    //         //     Textarea::make('notes')
    //         //         ->label('Notes')
    //         //         ->rows(2)
    //         //         ->columnSpanFull(),
    //         // ]);
    // }

}
