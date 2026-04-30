<?php

namespace App\Filament\Resources\UserPackages\RelationManagers;

use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\InvoicePayment;
use Filament\Notifications\Notification;

class InvoiceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoice';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total Amount')
                    ->money('INR'),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Paid Amount')
                    ->money('INR')
                    ->color('success'),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Due Amount')
                    ->money('INR')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'draft' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('generate_invoice')
                    ->label('Create Invoice')
                    ->icon('heroicon-o-plus')
                    ->hidden(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->invoice()->exists())
                    ->action(function (RelationManager $livewire) {
                        $package = $livewire->getOwnerRecord();

                        // Automatically create the invoice
                        $invoice = Invoice::create([
                            'clinic_id' => $package->clinic_id,
                            'user_id' => $package->user_id,
                            'package_id' => $package->id,
                            'invoice_type' => 'package',
                            'invoice_date' => now(),
                            'source_note' => "Package: {$package->package_name} ({$package->quantity} sessions)",
                            'subtotal' => $package->total_amount,
                            'discount_total' => $package->discount_amount,
                            'taxable_value' => $package->final_amount, // Assuming no GST
                            'grand_total' => $package->final_amount,
                            'amount_due' => $package->final_amount,
                            'status' => 'unpaid',
                            'created_by' => auth()->id(),
                        ]);

                        // Create invoice item
                        $invoice->items()->create([
                            'product_id' => $package->service_id,
                            'quantity' => 1,
                            'unit_price' => $package->total_amount,
                            'discount_type' => $package->discount_type,
                            'discount_value' => $package->discount_value,
                            'valid_discount_amount' => $package->discount_amount,
                            'line_total' => $package->final_amount,
                        ]);

                        Notification::make()
                            ->title('Invoice Created Successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Generate Package Invoice')
                    ->modalDescription('This will automatically generate an invoice for this package. Do you wish to continue?'),
            ])
            ->actions([
                ViewAction::make()->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record])),
                Action::make('make_payment')
                    ->label('Make Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->hidden(fn ($record) => in_array($record->status, ['paid', 'cancelled']))
                    ->form(function ($record) {
                        return [
                            Placeholder::make('summary')
                                ->label('Payment Summary')
                                ->content(new HtmlString("Invoice Total: ₹" . number_format($record->grand_total, 2) . "<br>Amount Paid: ₹" . number_format($record->amount_paid, 2) . "<br><strong>Remaining Balance: ₹" . number_format($record->grand_total - $record->amount_paid, 2) . "</strong>")),
                            DatePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),
                            TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->maxValue($record->grand_total - $record->amount_paid),
                            Select::make('payment_method')
                                ->label('Payment Method')
                                ->options([
                                    'cash' => 'Cash',
                                    'card' => 'Card',
                                    'upi' => 'UPI',
                                    'bank_transfer' => 'Bank Transfer',
                                    'other' => 'Other',
                                ])
                                ->required()
                                ->live(),
                            TextInput::make('reference_number')
                                ->label('Reference Number')
                                ->placeholder('e.g. Transaction ID, Check #')
                                ->hidden(fn (Get $get) => $get('payment_method') === 'cash')
                                ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        InvoicePayment::create([
                            'invoice_id' => $record->id,
                            'payment_date' => $data['payment_date'],
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'reference_number' => $data['reference_number'] ?? null,
                            'created_by' => auth()->id(),
                        ]);

                        $record->recalculatePaymentStatus();

                        Notification::make()
                            ->title('Payment Recorded ✅')
                            ->body("₹" . number_format($data['amount'], 2) . " has been recorded for Invoice #{$record->id}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                //
            ]);
    }
}
