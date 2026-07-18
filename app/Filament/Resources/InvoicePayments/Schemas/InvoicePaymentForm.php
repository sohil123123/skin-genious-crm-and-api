<?php

namespace App\Filament\Resources\InvoicePayments\Schemas;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\LoyaltyOtpService;
use App\Services\LoyaltyPointService;
use Filament\Forms\Components\DateTimePicker;
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
use App\Models\User;
use App\Models\Clinic;
use App\Models\InvoicePayment;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use App\Models\UserPackage;


class InvoicePaymentForm
{
    /**
     * Get a common "Make Payment" action that can be used in tables or pages.
     */
    public static function getMakePaymentAction(string $name = 'make_payment'): Action
    {
        return Action::make($name)
            ->label('Make Payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading('Make Payment')
            ->modalWidth('4xl')
            ->form(function ($record = null, $livewire = null) {
                // Determine model: either passed as $record or from $livewire owner
                $isPackage = $record instanceof UserPackage;
                $isInvoice = $record instanceof Invoice;
                $invoice = null;

                if (!$isPackage && !$isInvoice && $livewire && method_exists($livewire, 'getOwnerRecord')) {
                    $owner = $livewire->getOwnerRecord();
                    if ($owner instanceof UserPackage) {
                        $record = $owner;
                        $isPackage = true;
                    } elseif ($owner instanceof Invoice) {
                        $invoice = $owner;
                        $isInvoice = true;
                    }
                } elseif ($isInvoice) {
                    $invoice = $record;
                }

                if (!$isPackage && !$isInvoice && !$invoice) {
                    return [];
                }

                $balanceDue = $isPackage ? $record->getOutstandingAmount() : ($invoice ? $invoice->grand_total - $invoice->amount_paid : 0);

                return [
                    Section::make('Payment Details')
                        ->schema([
                            Placeholder::make('balance_due')
                                ->label('Balance Due')
                                ->content('₹' . number_format($balanceDue, 2))
                                ->extraAttributes(['class' => 'text-danger-600 font-bold text-xl']),

                            Repeater::make('payments')
                                ->label('Payment Methods')
                                ->schema([
                                    Select::make('payment_method')
                                        ->label('Method')
                                        ->options([
                                            'cash' => '💵 Cash',
                                            'card' => '💳 Card',
                                            'upi' => '📱 UPI',
                                            'bank_transfer' => '🏦 Bank Transfer',
                                            'loyalty_points' => '⭐ Loyalty Points',
                                            'other' => '📋 Other',
                                        ])
                                        ->required()
                                        ->live()
                                        ->prefixIcon('heroicon-o-credit-card')
                                        ->afterStateUpdated(function (Set $set, $state) use ($record, $isPackage, $invoice) {
                                            if ($state === 'loyalty_points') {
                                                $client = $isPackage ? $record->user : ($invoice ? $invoice->client : null);
                                                if ($client) {
                                                    $balance = $client->getLoyaltyBalance();
                                                    $minRedeem = Setting::getLoyaltyMinRedeem();

                                                    if ($balance < $minRedeem) {
                                                        Notification::make()
                                                            ->title('Insufficient Loyalty Points')
                                                            ->body("Client has {$balance} points. Min {$minRedeem} pts required.")
                                                            ->danger()
                                                            ->send();
                                                        $set('payment_method', null);
                                                        return;
                                                    }
                                                    $loyaltyOtp = app(LoyaltyOtpService::class)->sendOtp($client);
                                                    Notification::make()
                                                        ->title('OTP Sent 📲')
                                                        ->body("Verification OTP has been sent to {$client->mobile}. Available balance: {$balance} pts.")
                                                        ->info()
                                                        ->send();
                                                }
                                            }
                                        }),

                                    TextInput::make('amount')
                                        ->label(fn(Get $get) => $get('payment_method') === 'loyalty_points' ? 'Points to Redeem' : 'Amount')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0.01)
                                        ->maxValue(function (Get $get) use ($record, $isPackage, $invoice) {
                                            if ($get('payment_method') === 'loyalty_points') {
                                                $client = $isPackage ? $record->user : ($invoice ? $invoice->client : null);
                                                if ($client) {
                                                    return $client->getLoyaltyBalance();
                                                }
                                            }
                                            return null;
                                        })
                                        ->prefix(fn(Get $get) => $get('payment_method') === 'loyalty_points' ? '⭐' : '₹')
                                        ->helperText(function (Get $get) use ($record, $isPackage, $invoice) {
                                            if ($get('payment_method') !== 'loyalty_points') {
                                                return null;
                                            }

                                            $client = $isPackage ? $record->user : ($invoice ? $invoice->client : null);
                                            if ($client) {
                                                $balance = $client->getLoyaltyBalance();
                                                return "Available: {$balance} pts (1 pt = ₹1)";
                                            }

                                            return '1 point = ₹1';
                                        })
                                        ->live(onBlur: true),

                                    TextInput::make('loyalty_otp')
                                        ->label('OTP Verification')
                                        ->placeholder('Enter 6-digit OTP')
                                        ->maxLength(6)
                                        ->required()
                                        ->prefixIcon('heroicon-o-lock-closed')
                                        ->helperText('OTP sent to client\'s registered mobile')
                                        ->visible(fn(Get $get) => $get('payment_method') === 'loyalty_points'),

                                    TextInput::make('reference_number')
                                        ->label('Ref / TXN ID')
                                        ->placeholder('Optional')
                                        ->hidden(fn(Get $get) => in_array($get('payment_method'), ['cash', 'loyalty_points']))
                                        ->prefixIcon('heroicon-o-hashtag'),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->addActionLabel('Add Payment Split')
                                ->live()
                                ->columnSpanFull()
                                ->rules([
                                    function (Get $get) use ($record, $isPackage, $invoice) {
                                        return function (string $attribute, $value, $fail) use ($get, $record, $isPackage, $invoice) {
                                            $remaining = $isPackage ? $record->getOutstandingAmount() : ($invoice ? $invoice->grand_total - $invoice->amount_paid : 0);
                                            $total = collect($value)->sum(fn($p) => floatval($p['amount'] ?? 0));

                                            if (round($total, 2) > round($remaining, 2)) {
                                                $fail("Total amount (₹" . number_format($total, 2) . ") exceeds remaining balance (₹" . number_format($remaining, 2) . ").");
                                            }
                                            if ($total <= 0) {
                                                $fail("Total payment amount must be greater than 0.");
                                            }

                                            // Validate loyalty points specific rules
                                            foreach ($value as $paymentEntry) {
                                                if (($paymentEntry['payment_method'] ?? '') === 'loyalty_points') {
                                                    $client = $isPackage ? $record->user : ($invoice ? $invoice->client : null);
                                                    if (!$client) {
                                                        $fail('Cannot find client for loyalty validation.');
                                                        return;
                                                    }

                                                    $balance = $client->getLoyaltyBalance();
                                                    $minRedeem = Setting::getLoyaltyMinRedeem();
                                                    $pointsToRedeem = (int) ($paymentEntry['amount'] ?? 0);

                                                    if ($balance < $minRedeem) {
                                                        $fail("Client has {$balance} loyalty points. Minimum {$minRedeem} required for redemption.");
                                                        return;
                                                    }

                                                    if ($pointsToRedeem > $balance) {
                                                        $fail("Cannot redeem {$pointsToRedeem} points. Available balance: {$balance}.");
                                                        return;
                                                    }

                                                    // Validate OTP
                                                    $otp = $paymentEntry['loyalty_otp'] ?? '';
                                                    if (empty($otp)) {
                                                        $fail('OTP is required for loyalty points redemption.');
                                                        return;
                                                    }

                                                    $otpService = app(LoyaltyOtpService::class);
                                                    if (!$otpService->verifyOtp($client, $otp)) {
                                                        $fail('Invalid or expired OTP. Please request a new one.');
                                                        return;
                                                    }
                                                }
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
                                }),

                            DateTimePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),

                            Textarea::make('notes')
                                ->label('Payment Notes')
                                ->placeholder('Add any relevant notes...')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                ];
            })
            ->action(function ($record, array $data, $livewire) {
                // Determine if package or invoice
                $isPackage = $record instanceof UserPackage;
                $isInvoice = $record instanceof Invoice;

                if (!$isPackage && !$isInvoice && $livewire && method_exists($livewire, 'getOwnerRecord')) {
                    $owner = $livewire->getOwnerRecord();
                    if ($owner instanceof UserPackage) {
                        $record = $owner;
                        $isPackage = true;
                    } elseif ($owner instanceof Invoice) {
                        $record = $owner;
                        $isInvoice = true;
                    }
                }

                if (!$isPackage && !$isInvoice) {
                    return;
                }

                $payments = $data['payments'] ?? [];
                $totalPaid = collect($payments)->sum(fn($p) => floatval($p['amount'] ?? 0));

                if ($isPackage) {
                    // Create partial/installment invoice for total paid
                    $invoice = $record->createInvoice($totalPaid);
                } else {
                    $invoice = $record;
                }

                // Sort payments so loyalty_points redemptions are processed first
                usort($payments, function ($a, $b) {
                    $aIsLoyalty = ($a['payment_method'] ?? '') === 'loyalty_points';
                    $bIsLoyalty = ($b['payment_method'] ?? '') === 'loyalty_points';
                    if ($aIsLoyalty && !$bIsLoyalty) {
                        return -1;
                    }
                    if (!$aIsLoyalty && $bIsLoyalty) {
                        return 1;
                    }
                    return 0;
                });

                $loyaltyService = app(LoyaltyPointService::class);

                foreach ($payments as $paymentData) {
                    if (($paymentData['payment_method'] ?? '') === 'loyalty_points') {
                        $client = $invoice->client;
                        $pointsToRedeem = (int) $paymentData['amount'];

                        try {
                            $loyaltyService->redeemPoints($client, $invoice->id, $pointsToRedeem, auth()->id());
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Loyalty Redemption Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            continue;
                        }
                    } else {
                        InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'payment_date' => $data['payment_date'],
                            'amount' => $paymentData['amount'],
                            'payment_method' => $paymentData['payment_method'],
                            'reference_number' => $paymentData['reference_number'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                $invoice->recalculatePaymentStatus();

                Notification::make()
                    ->title('Payment Processed ✅')
                    ->body('Payments have been recorded and invoice status updated.')
                    ->success()
                    ->send();
            });
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Invoice Information')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // ─── Clinic (super_admin only) ───────
                                Select::make('clinic_id')
                                    ->label('Clinic')
                                    ->options(Clinic::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select clinic')
                                    ->live()
                                    ->afterStateHydrated(function (Set $set, ?InvoicePayment $record) {
                                        if ($record && $record->invoice) {
                                            $set('clinic_id', $record->invoice->clinic_id);
                                        }
                                    })
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('user_id', null);
                                        $set('invoice_id', null);
                                    })
                                    ->visible(fn() => auth()->user()->hasRole('super_admin'))
                                    ->hidden(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords),

                                // ─── Client ──────────────────────────
                                Select::make('user_id')
                                    ->label('Client')
                                    ->options(function (Get $get) {
                                        $clinicId = $get('clinic_id') ?: auth()->user()->clinic_id;
                                        if (!$clinicId)
                                            return [];

                                        return User::where('clinic_id', $clinicId)
                                            ->role('client')
                                            ->orderBy('first_name')
                                            ->get()
                                            ->mapWithKeys(fn($u) => [$u->id => $u->name]);
                                    })
                                    ->searchable()
                                    ->placeholder('Select client')
                                    ->live()
                                    ->afterStateHydrated(function (Set $set, ?InvoicePayment $record) {
                                        if ($record && $record->invoice) {
                                            $set('user_id', $record->invoice->user_id);
                                        }
                                    })
                                    ->afterStateUpdated(fn(Set $set) => $set('invoice_id', null))
                                    ->hidden(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords),

                                // ─── Invoice ─────────────────────────
                                Select::make('invoice_id')
                                    ->label('Invoice')
                                    ->options(function (Get $get) {
                                        $userId = $get('user_id');
                                        $clinicId = $get('clinic_id') ?: auth()->user()->clinic_id;

                                        $query = Invoice::whereIn('status', ['unpaid', 'partial', 'pending']);

                                        if ($userId) {
                                            $query->where('user_id', $userId);
                                        } elseif ($clinicId) {
                                            $query->where('clinic_id', $clinicId);
                                        }

                                        return $query->get()->mapWithKeys(
                                            fn($inv) =>
                                            [$inv->id => "#{$inv->invoice_number} — {$inv->client?->name} (₹" . number_format($inv->grand_total, 2) . ")"]
                                        );
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->disabled(fn(?InvoicePayment $record) => $record !== null)
                                    ->hidden(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords)
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

                                // ─── Balance Due ──────────────────────────────
                                Placeholder::make('remaining_balance_display')
                                    ->label('Current Balance Due')
                                    ->extraAttributes(['class' => 'text-danger-600 font-bold text-xl'])
                                    ->content(function (Get $get, $livewire) {
                                        if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                            $invoice = $livewire->getOwnerRecord();
                                        } else {
                                            $invoiceId = $get('invoice_id');
                                            if (!$invoiceId)
                                                return 'Select an invoice first';
                                            $invoice = Invoice::find($invoiceId);
                                        }

                                        if (!$invoice)
                                            return 'Invoice not found';
                                        return new HtmlString('<span style="color: #dc2626; font-size: 1.25rem; font-weight: bold;">₹' . number_format($invoice->grand_total - $invoice->amount_paid, 2) . '</span>');
                                    }),
                            ]),
                    ]),

                Section::make('Payment Details')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Repeater::make('payments')
                                    ->label('Payment Methods')
                                    ->schema([
                                        Select::make('payment_method')
                                            ->label('Method')
                                            ->options([
                                                'cash' => '💵 Cash',
                                                'card' => '💳 Card',
                                                'upi' => '📱 UPI',
                                                'bank_transfer' => '🏦 Bank Transfer',
                                                'loyalty_points' => '⭐ Loyalty Points',
                                                'other' => '📋 Other',
                                            ])
                                            ->required()
                                            ->live()
                                            ->prefixIcon('heroicon-o-credit-card')
                                            ->afterStateUpdated(function (Set $set, Get $get, $state, $livewire) {
                                                if ($state === 'loyalty_points') {
                                                    // Get the invoice to find the client
                                                    $invoice = null;
                                                    if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                                        $invoice = $livewire->getOwnerRecord();
                                                    } else {
                                                        $invoiceId = $get('../../invoice_id');
                                                        if ($invoiceId) {
                                                            $invoice = Invoice::find($invoiceId);
                                                        }
                                                    }

                                                    if ($invoice && $invoice->client) {
                                                        $client = $invoice->client;
                                                        $balance = $client->getLoyaltyBalance();
                                                        $minRedeem = Setting::getLoyaltyMinRedeem();

                                                        if ($balance < $minRedeem) {
                                                            Notification::make()
                                                                ->title('Insufficient Loyalty Points')
                                                                ->body("Client has {$balance} points. Minimum {$minRedeem} points required for redemption.")
                                                                ->danger()
                                                                ->send();
                                                            $set('payment_method', null);
                                                            return;
                                                        }

                                                        // Send OTP automatically
                                                        $loyaltyOtp = app(LoyaltyOtpService::class)->sendOtp($client);

                                                        Notification::make()
                                                            ->title('OTP Sent 📲')
                                                            ->body("Verification OTP has been sent to {$client->mobile}. Available balance: {$balance} pts.")
                                                            ->info()
                                                            ->send();
                                                    }
                                                } else {
                                                    // Clear OTP field when switching away from loyalty
                                                    $set('loyalty_otp', null);
                                                }
                                            }),

                                        TextInput::make('amount')
                                            ->label(fn(Get $get) => $get('payment_method') === 'loyalty_points' ? 'Points to Redeem' : 'Amount')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.01)
                                            ->prefix(fn(Get $get) => $get('payment_method') === 'loyalty_points' ? '⭐' : '₹')
                                            ->live(onBlur: true)
                                            ->helperText(function (Get $get, $livewire) {
                                                if ($get('payment_method') !== 'loyalty_points') {
                                                    return null;
                                                }

                                                $invoice = null;
                                                if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                                    $invoice = $livewire->getOwnerRecord();
                                                } else {
                                                    $invoiceId = $get('../../invoice_id');
                                                    if ($invoiceId) {
                                                        $invoice = Invoice::find($invoiceId);
                                                    }
                                                }

                                                if ($invoice && $invoice->client) {
                                                    $balance = $invoice->client->getLoyaltyBalance();
                                                    return "Available: {$balance} pts (1 pt = ₹1)";
                                                }

                                                return '1 point = ₹1';
                                            }),

                                        TextInput::make('loyalty_otp')
                                            ->label('OTP Verification')
                                            ->placeholder('Enter 6-digit OTP')
                                            ->maxLength(6)
                                            ->required()
                                            ->prefixIcon('heroicon-o-lock-closed')
                                            ->visible(fn(Get $get) => $get('payment_method') === 'loyalty_points')
                                            ->helperText('OTP sent to client\'s registered mobile'),

                                        TextInput::make('reference_number')
                                            ->label('Ref / TXN ID')
                                            ->placeholder('Optional')
                                            ->hidden(fn(Get $get) => in_array($get('payment_method'), ['cash', 'loyalty_points']))
                                            // ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null)
                                            ->prefixIcon('heroicon-o-hashtag'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add Payment Split')
                                    ->live()
                                    ->visible(fn(?InvoicePayment $record) => $record === null)
                                    ->columnSpanFull()
                                    ->rules([
                                        function (Get $get, $livewire) {
                                            return function (string $attribute, $value, $fail) use ($get, $livewire) {
                                                if ($livewire instanceof \Filament\Resources\Pages\ManageRelatedRecords) {
                                                    $invoice = $livewire->getOwnerRecord();
                                                } else {
                                                    $invoiceId = $get('invoice_id');
                                                    if (!$invoiceId)
                                                        return;
                                                    $invoice = Invoice::find($invoiceId);
                                                }
                                                if (!$invoice)
                                                    return;

                                                $remaining = $invoice->grand_total - $invoice->amount_paid;
                                                $total = collect($value)->sum(fn($p) => floatval($p['amount'] ?? 0));

                                                if (round($total, 2) > round($remaining, 2)) {
                                                    $fail("Total amount (₹" . number_format($total, 2) . ") exceeds remaining balance (₹" . number_format($remaining, 2) . ").");
                                                }
                                                if ($total <= 0) {
                                                    $fail("Total payment amount must be greater than 0.");
                                                }

                                                // Validate loyalty points specific rules
                                                foreach ($value as $paymentEntry) {
                                                    if (($paymentEntry['payment_method'] ?? '') === 'loyalty_points') {
                                                        $client = $invoice->client;
                                                        if (!$client) {
                                                            $fail('Cannot find client for loyalty validation.');
                                                            return;
                                                        }

                                                        $balance = $client->getLoyaltyBalance();
                                                        $minRedeem = Setting::getLoyaltyMinRedeem();
                                                        $pointsToRedeem = (int) ($paymentEntry['amount'] ?? 0);

                                                        if ($balance < $minRedeem) {
                                                            $fail("Client has {$balance} loyalty points. Minimum {$minRedeem} required for redemption.");
                                                            return;
                                                        }

                                                        if ($pointsToRedeem > $balance) {
                                                            $fail("Cannot redeem {$pointsToRedeem} points. Available balance: {$balance}.");
                                                            return;
                                                        }

                                                        // Validate OTP
                                                        $otp = $paymentEntry['loyalty_otp'] ?? '';
                                                        if (empty($otp)) {
                                                            $fail('OTP is required for loyalty points redemption.');
                                                            return;
                                                        }

                                                        $otpService = app(LoyaltyOtpService::class);
                                                        if (!$otpService->verifyOtp($client, $otp)) {
                                                            $fail('Invalid or expired OTP. Please request a new one.');
                                                            return;
                                                        }
                                                    }
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
                                    ->visible(fn(?InvoicePayment $record) => $record === null),
                                // ->columnSpanFull(),

                                DateTimePicker::make('payment_date')
                                    ->label('Payment Date')
                                    ->default(now())
                                    ->required()
                                    ->prefixIcon('heroicon-o-calendar'),

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
