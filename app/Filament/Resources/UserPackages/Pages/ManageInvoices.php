<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Filament\Resources\UserPackages\UserPackageResource;
use App\Models\Invoice;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Schema;
use Filament\Infolists\Components;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Infolists\Components\RepeatableEntry;

class ManageInvoices extends Page implements HasForms, HasInfolists
{
    use InteractsWithRecord;
    use InteractsWithForms;
    use InteractsWithInfolists;

    protected static string $resource = UserPackageResource::class;

    protected string $view = 'filament.resources.user-packages.pages.manage-invoices';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Manage Invoice for "' . $this->record->package_name . '"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Invoice';
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->record->invoice)
            ->components([
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
                        TextEntry::make('payment_mode')->badge(),
                        TextEntry::make('source_note')->label('Note')->placeholder('-'),
                        TextEntry::make('created_at')->dateTime()->label('Created At'),
                        TextEntry::make('updated_at')->dateTime()->label('Last Updated'),
                    ])
                    ->columns(3),

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
                                Grid::make(6)->schema([
                                    TextEntry::make('product.name')->label('Product'),
                                    TextEntry::make('quantity')->label('Qty'),
                                    TextEntry::make('unit_price')->label('Price')->money('INR'),
                                    TextEntry::make('discount_value')
                                        ->label('Discount')
                                        ->formatStateUsing(function ($record) {
                                            $actualDiscount = $record->valid_discount_amount ?? $record->discount_amount ?? 0;
                                            if ($record->discount_type === 'percentage') {
                                                return ($record->discount_value ?? 0) . '% (₹' . number_format($actualDiscount, 2) . ')';
                                            }
                                            return '₹' . number_format($actualDiscount, 2);
                                        })
                                        ->badge()
                                        ->color('info'),
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
                                TextEntry::make('discount_total')->money('INR')->label('Discount')->color('success')->inlineLabel(),
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
            Action::make('generate_invoice')
                ->label('Generate Invoice')
                ->icon('heroicon-o-plus')
                ->hidden(fn () => $this->record->invoice !== null)
                ->action(function () {
                    $package = $this->record;

                    $invoice = Invoice::create([
                        'clinic_id' => $package->clinic_id,
                        'user_id' => $package->user_id,
                        'package_id' => $package->id,
                        'invoice_type' => 'package',
                        'invoice_date' => now(),
                        'source_note' => "Package: {$package->package_name} ({$package->quantity} sessions)",
                        'subtotal' => $package->total_amount,
                        'discount_total' => $package->discount_amount,
                        'taxable_value' => $package->final_amount,
                        'grand_total' => $package->final_amount,
                        'amount_due' => $package->final_amount,
                        'status' => 'unpaid',
                        'created_by' => auth()->id(),
                    ]);

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
                ->requiresConfirmation(),

            Action::make('make_payment')
                ->label('Make Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->hidden(fn () => !$this->record->invoice || in_array($this->record->invoice->status, ['paid', 'cancelled']))
                ->modalHeading('Create Invoice Payment')
                ->modalWidth('5xl')
                ->form(function () {
                    $record = $this->record->invoice;
                    return [
                        Grid::make(12)->schema([
                            Section::make('Invoice Information')
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    \Filament\Forms\Components\TextInput::make('invoice_number')
                                        ->label('Invoice')
                                        ->default($record->invoice_number)
                                        ->disabled()
                                        ->dehydrated(false),
                                    \Filament\Forms\Components\Placeholder::make('current_balance_due')
                                        ->label('Current Balance Due')
                                        ->content('₹ ' . number_format($record->amount_due, 2)),
                                ])
                                ->columnSpan(['default' => 12, 'md' => 5]),

                            Section::make('Payment Details')
                                ->icon('heroicon-o-banknotes')
                                ->schema([
                                    \Filament\Forms\Components\DatePicker::make('payment_date')
                                        ->label('Payment Date')
                                        ->default(now())
                                        ->required(),
                                    \Filament\Forms\Components\TextInput::make('amount')
                                        ->label('Payment Amount')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0.01)
                                        ->maxValue($record->amount_due)
                                        ->default($record->amount_due)
                                        ->prefix('₹'),
                                    \Filament\Forms\Components\Select::make('payment_method')
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
                                    \Filament\Forms\Components\TextInput::make('reference_number')
                                        ->label('Reference Number / TXN ID')
                                        ->placeholder('# Transaction ID, Check #, etc.')
                                        ->hidden(fn (Get $get) => $get('payment_method') === 'cash')
                                        ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null),
                                    \Filament\Forms\Components\Textarea::make('notes')
                                        ->label('Payment Notes')
                                        ->placeholder('Add any relevant notes about this payment...')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpan(['default' => 12, 'md' => 7]),
                        ])
                    ];
                })
                ->action(function (array $data) {
                    $record = $this->record->invoice;
                    \App\Models\InvoicePayment::create([
                        'invoice_id' => $record->id,
                        'payment_date' => $data['payment_date'],
                        'amount' => $data['amount'],
                        'payment_method' => $data['payment_method'],
                        'reference_number' => $data['reference_number'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => auth()->id(),
                    ]);

                    $record->recalculatePaymentStatus();

                    Notification::make()
                        ->title('Payment Recorded ✅')
                        ->body("₹" . number_format($data['amount'], 2) . " has been recorded for Invoice #{$record->id}.")
                        ->success()
                        ->send();
                }),

            Action::make('download_pdf')
                ->label('PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->hidden(fn () => !$this->record->invoice)
                ->action(function () {
                    $pdfService = app(\App\Services\InvoicePdfService::class);
                    return $pdfService->download($this->record->invoice);
                }),
                // Using pdf service download
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if (!$record) return false;
        return $record->user->hasRole('client');
    }
}
