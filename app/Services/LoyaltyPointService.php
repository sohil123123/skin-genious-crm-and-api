<?php

namespace App\Services;

use App\Models\InvoicePayment;
use App\Models\LoyaltyPointTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyPointService
{
    /**
     * Earn loyalty points after a successful non-loyalty payment.
     * Called automatically from InvoicePayment::booted().
     */
    public function earnPoints(InvoicePayment $payment): ?LoyaltyPointTransaction
    {
        // Don't earn points on loyalty_points payments (avoid circular earning)
        if ($payment->payment_method === 'loyalty_points') {
            return null;
        }

        $invoice = $payment->invoice;
        if (!$invoice) {
            return null;
        }

        $client = $invoice->client;
        if (!$client) {
            return null;
        }

        $rate = Setting::getLoyaltyRate();
        if ($rate <= 0) {
            return null;
        }

        $pointsEarned = (int) floor((float) $payment->amount * $rate / 100);
        if ($pointsEarned <= 0) {
            return null;
        }

        return DB::transaction(function () use ($client, $payment, $pointsEarned) {
            // Lock the user row to prevent race conditions
            $lockedClient = User::lockForUpdate()->find($client->id);

            $currentBalance = $lockedClient->getLoyaltyBalance();
            $newBalance = $currentBalance + $pointsEarned;

            // Create ledger entry
            $transaction = LoyaltyPointTransaction::create([
                'user_id' => $lockedClient->id,
                'invoice_payment_id' => $payment->id,
                'type' => 'earn',
                'points' => $pointsEarned,
                'balance_after' => $newBalance,
                'description' => "Earned {$pointsEarned} points on payment #{$payment->transaction_id}",
                'metadata' => [
                    'payment_amount' => $payment->amount,
                    'rate_percent' => Setting::getLoyaltyRate(),
                    'invoice_id' => $payment->invoice_id,
                ],
                'created_by' => $payment->created_by,
            ]);

            // Update user's cached balance
            $lockedClient->update(['loyalty_points' => $newBalance]);

            return $transaction;
        });
    }

    /**
     * Redeem loyalty points during invoice payment.
     * Returns the created InvoicePayment and LoyaltyPointTransaction.
     */
    public function redeemPoints(
        User $client,
        int $invoiceId,
        int $pointsToRedeem,
        ?int $createdBy = null
    ): array {
        $minRedeem = Setting::getLoyaltyMinRedeem();

        return DB::transaction(function () use ($client, $invoiceId, $pointsToRedeem, $createdBy, $minRedeem) {
            // Lock the user row
            $lockedClient = User::lockForUpdate()->find($client->id);
            $currentBalance = $lockedClient->getLoyaltyBalance();

            // Validate balance threshold
            if ($currentBalance < $minRedeem) {
                throw new InvalidArgumentException(
                    "Insufficient loyalty points. Minimum {$minRedeem} required, current balance: {$currentBalance}."
                );
            }

            // Validate sufficient points
            if ($pointsToRedeem > $currentBalance) {
                throw new InvalidArgumentException(
                    "Cannot redeem {$pointsToRedeem} points. Available balance: {$currentBalance}."
                );
            }

            if ($pointsToRedeem <= 0) {
                throw new InvalidArgumentException('Points to redeem must be greater than 0.');
            }

            $newBalance = $currentBalance - $pointsToRedeem;

            // Create the invoice payment record (1 point = ₹1)
            $payment = InvoicePayment::create([
                'invoice_id' => $invoiceId,
                'payment_date' => now()->toDateString(),
                'amount' => $pointsToRedeem, // 1 point = ₹1
                'payment_method' => 'loyalty_points',
                'reference_number' => null,
                'notes' => "Redeemed {$pointsToRedeem} loyalty points",
                'created_by' => $createdBy,
            ]);

            // Create ledger entry (negative points for redemption)
            $transaction = LoyaltyPointTransaction::create([
                'user_id' => $lockedClient->id,
                'invoice_payment_id' => $payment->id,
                'type' => 'redeem',
                'points' => -$pointsToRedeem,
                'balance_after' => $newBalance,
                'description' => "Redeemed {$pointsToRedeem} points for payment #{$payment->transaction_id}",
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'amount_redeemed' => $pointsToRedeem,
                ],
                'created_by' => $createdBy,
            ]);

            // Update user's cached balance
            $lockedClient->update(['loyalty_points' => $newBalance]);

            return [
                'payment' => $payment,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Reverse loyalty points (e.g., on payment deletion/refund).
     */
    public function reversePoints(InvoicePayment $payment): ?LoyaltyPointTransaction
    {
        $invoice = $payment->invoice;
        if (!$invoice) {
            return null;
        }

        $client = $invoice->client;
        if (!$client) {
            return null;
        }

        // Find the original earn/redeem transaction for this payment
        $originalTransaction = LoyaltyPointTransaction::where('invoice_payment_id', $payment->id)
            ->whereIn('type', ['earn', 'redeem'])
            ->first();

        if (!$originalTransaction) {
            return null;
        }

        return DB::transaction(function () use ($client, $payment, $originalTransaction) {
            $lockedClient = User::lockForUpdate()->find($client->id);
            $currentBalance = $lockedClient->getLoyaltyBalance();

            // Reverse the points (negate the original)
            $reversePoints = -$originalTransaction->points;
            $newBalance = $currentBalance + $reversePoints;

            $transaction = LoyaltyPointTransaction::create([
                'user_id' => $lockedClient->id,
                'invoice_payment_id' => $payment->id,
                'type' => 'reverse',
                'points' => $reversePoints,
                'balance_after' => $newBalance,
                'description' => "Reversed {$originalTransaction->points} points from delete payment #{$payment->transaction_id}",
                'metadata' => [
                    'original_transaction_id' => $originalTransaction->id,
                    'original_type' => $originalTransaction->type,
                ],
                'created_by' => auth()->id(),
            ]);

            $lockedClient->update(['loyalty_points' => $newBalance]);

            return $transaction;
        });
    }

    /**
     * Get the client's current loyalty balance.
     */
    public function getBalance(User $client): int
    {
        return $client->getLoyaltyBalance();
    }

    /**
     * Check if client is eligible for redemption.
     */
    public function canRedeem(User $client): bool
    {
        return $client->getLoyaltyBalance() >= Setting::getLoyaltyMinRedeem();
    }
}
