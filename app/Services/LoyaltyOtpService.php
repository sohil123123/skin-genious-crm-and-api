<?php

namespace App\Services;

use App\Models\LoyaltyOtp;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LoyaltyOtpService
{
    /**
     * Generate and send OTP to the client's mobile.
     */
    public function sendOtp(User $client): LoyaltyOtp
    {
        // Invalidate any existing unused OTPs for this user+purpose
        LoyaltyOtp::where('user_id', $client->id)
            ->where('purpose', 'loyalty_redeem')
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = Setting::getLoyaltyOtpExpiry();

        $loyaltyOtp = LoyaltyOtp::create([
            'user_id'    => $client->id,
            'otp'        => $otp,
            'purpose'    => 'loyalty_redeem',
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        // TODO: Integrate with your SMS provider here
        // For now, log the OTP for development
        Log::info("Loyalty OTP for client #{$client->id} ({$client->mobile}): {$otp}");

        return $loyaltyOtp;
    }

    /**
     * Verify OTP for loyalty redemption.
     */
    public function verifyOtp(User $client, string $otp): bool
    {
        $loyaltyOtp = LoyaltyOtp::where('user_id', $client->id)
            ->where('otp', $otp)
            ->where('purpose', 'loyalty_redeem')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$loyaltyOtp) {
            return false;
        }

        // // Mark as used (single-use)
        // $loyaltyOtp->markUsed();

        // Delete after verification (single-use)
        $loyaltyOtp->delete();

        return true;
    }

    /**
     * Check if client has a pending (valid) OTP.
     */
    public function hasPendingOtp(User $client): bool
    {
        return LoyaltyOtp::where('user_id', $client->id)
            ->where('purpose', 'loyalty_redeem')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
