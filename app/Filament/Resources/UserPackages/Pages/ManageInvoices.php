<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Filament\Resources\UserPackages\UserPackageResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\InvoicePayments\Schemas\InvoicePaymentForm;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use BackedEnum;

class ManageInvoices extends Page implements HasForms, HasInfolists, HasTable
{
    use InteractsWithRecord;
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithTable;

    protected static string $resource = UserPackageResource::class;

    protected string $view = 'filament.resources.user-packages.pages.manage-invoices';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Manage Invoices for "' . $this->record->package_name . '"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()->where('package_id', $this->record->id)
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('taxable_value')
                    ->label('Taxable Total')
                    ->money('INR')
                    ->badge()
                    ->color('info')
                    ->summarize(Sum::make()->label('Total Taxable')->money('INR'))
                    ->sortable(),

                TextColumn::make('gst_total')
                    ->label('GST Total')
                    ->money('INR')
                    ->badge()
                    ->color('warning')
                    ->summarize(Sum::make()->label('Total GST')->money('INR'))
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Invoice Amount')
                    ->money('INR')
                    ->badge()
                    ->color('success')
                    ->summarize(Sum::make()->label('Total Amount')->money('INR'))
                    ->sortable(),
            ])
            ->actions([
                Action::make('view_invoice')
                    ->label('View')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->infolist(fn(Schema $schema, $record): Schema => InvoiceInfolist::configure($schema->record($record)))
                    ->modal()
                    ->modalHeading(fn($record) => 'Invoice ' . $record->invoice_number)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // Action::make('make_payment')
                //     ->label('Make Payment')
                //     ->icon('heroicon-o-banknotes')
                //     ->color('success')
                //     ->hidden(fn($record) => in_array($record->status, ['paid', 'cancelled']))
                //     ->modalHeading('Create Invoice Payment')
                //     ->modalWidth('5xl')
                //     ->form(function ($record) {
                //         return [
                //             Grid::make(12)->schema([
                //                 Section::make('Invoice Information')
                //                     ->icon('heroicon-o-document-text')
                //                     ->schema([
                //                         TextInput::make('invoice_number')
                //                             ->label('Invoice')
                //                             ->default($record->invoice_number)
                //                             ->disabled()
                //                             ->dehydrated(false),
                //                         Placeholder::make('current_balance_due')
                //                             ->label('Current Balance Due')
                //                             ->content('₹ ' . number_format($record->amount_due, 2)),
                //                     ])
                //                     ->columnSpan(['default' => 12, 'md' => 5]),

                //                 Section::make('Payment Details')
                //                     ->icon('heroicon-o-banknotes')
                //                     ->schema([
                //                         DatePicker::make('payment_date')
                //                             ->label('Payment Date')
                //                             ->default(now())
                //                             ->required(),
                //                         Repeater::make('payments')
                //                             ->label('Payment Methods')
                //                             ->schema([
                //                                 Select::make('payment_method')
                //                                     ->label('Method')
                //                                     ->options([
                //                                         'cash' => 'Cash',
                //                                         'card' => 'Card',
                //                                         'upi' => 'UPI',
                //                                         'bank_transfer' => 'Bank Transfer',
                //                                         'other' => 'Other',
                //                                     ])
                //                                     ->required()
                //                                     ->live()
                //                                     ->prefixIcon('heroicon-o-credit-card'),

                //                                 TextInput::make('amount')
                //                                     ->label('Amount')
                //                                     ->numeric()
                //                                     ->required()
                //                                     ->minValue(0.01)
                //                                     ->prefix('₹')
                //                                     ->live(onBlur: true),

                //                                 TextInput::make('reference_number')
                //                                     ->label('Ref / TXN ID')
                //                                     ->placeholder('Optional')
                //                                     ->hidden(fn(Get $get) => $get('payment_method') === 'cash')
                //                                     ->prefixIcon('heroicon-o-hashtag'),
                //                             ])
                //                             ->columns(3)
                //                             ->defaultItems(1)
                //                             ->addActionLabel('Add Payment Split')
                //                             ->live()
                //                             ->columnSpanFull()
                //                             ->rules([
                //                                 function () use ($record) {
                //                                     return function (string $attribute, $value, $fail) use ($record) {
                //                                         $remaining = $record->amount_due;
                //                                         $total = collect($value)->sum(fn($p) => floatval($p['amount'] ?? 0));

                //                                         if (round($total, 2) > round($remaining, 2)) {
                //                                             $fail("Total amount (₹" . number_format($total, 2) . ") exceeds remaining balance (₹" . number_format($remaining, 2) . ").");
                //                                         }
                //                                         if ($total <= 0) {
                //                                             $fail("Total payment amount must be greater than 0.");
                //                                         }
                //                                     };
                //                                 }
                //                             ]),

                //                         Placeholder::make('total_paid_preview')
                //                             ->label('Total Payment Scheduled')
                //                             ->content(function (Get $get) {
                //                                 $payments = $get('payments') ?? [];
                //                                 $total = collect($payments)->sum(fn($p) => floatval($p['amount'] ?? 0));
                //                                 return new HtmlString('<span class="text-xl font-bold text-success-600">₹' . number_format((float) $total, 2) . '</span>');
                //                             })
                //                             ->columnSpanFull(),

                //                         Textarea::make('notes')
                //                             ->label('Payment Notes')
                //                             ->placeholder('Add any relevant notes about this payment...')
                //                             ->columnSpanFull(),
                //                     ])
                //                     ->columns(2)
                //                     ->columnSpan(['default' => 12, 'md' => 7]),
                //             ])
                //         ];
                //     })
                //     ->action(function ($record, array $data) {
                //         $payments = $data['payments'] ?? [];
                //         $total = 0;

                //         foreach ($payments as $paymentData) {
                //             InvoicePayment::create([
                //                 'invoice_id' => $record->id,
                //                 'payment_date' => $data['payment_date'],
                //                 'amount' => $paymentData['amount'],
                //                 'payment_method' => $paymentData['payment_method'],
                //                 'reference_number' => $paymentData['reference_number'] ?? null,
                //                 'notes' => $data['notes'] ?? null,
                //                 'created_by' => auth()->id(),
                //             ]);
                //             $total += (float) $paymentData['amount'];
                //         }

                //         $record->recalculatePaymentStatus();

                //         Notification::make()
                //             ->title('Payment Recorded ✅')
                //             ->body("₹" . number_format($total, 2) . " has been recorded for Invoice #{$record->id}.")
                //             ->success()
                //             ->send();
                //     }),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function ($record) {
                        $pdfService = app(\App\Services\InvoicePdfService::class);
                        return $pdfService->download($record);
                    }),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('No Invoices Generated')
            ->emptyStateDescription('Click "Generate Invoice" to create a new partial or full invoice for this package.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),

            InvoicePaymentForm::getMakePaymentAction('record_payment')
                ->label('Make Payment')
                // ->icon('heroicon-o-plus')
                // ->color('success')
                ->hidden(fn() => $this->record->getOutstandingAmount() <= 0),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if (!$record) {
            return false;
        }
        return $record->user->hasRole('client');
    }
}
