<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyOtpService;
use App\Services\LoyaltyPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoyaltyController extends Controller
{
    protected LoyaltyPointService $loyaltyService;
    protected LoyaltyOtpService $otpService;

    public function __construct(LoyaltyPointService $loyaltyService, LoyaltyOtpService $otpService)
    {
        $this->loyaltyService = $loyaltyService;
        $this->otpService = $otpService;
    }

    /**
     * Get the authenticated client's loyalty balance and settings.
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'          => $user->getLoyaltyBalance(),
                'can_redeem'       => $this->loyaltyService->canRedeem($user),
                'min_redeem_points' => Setting::getLoyaltyMinRedeem(),
                'earning_rate'     => Setting::getLoyaltyRate(),
            ],
        ]);
    }

    /**
     * Get the authenticated client's loyalty point transaction history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = $user->loyaltyTransactions()
            ->with('invoicePayment:id,transaction_id,invoice_id')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    /**
     * Request OTP for loyalty redemption.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->loyaltyService->canRedeem($user)) {
            $minRedeem = Setting::getLoyaltyMinRedeem();
            return response()->json([
                'success' => false,
                'message' => "Insufficient points. Minimum {$minRedeem} required. Current balance: {$user->getLoyaltyBalance()}.",
            ], 422);
        }

        $this->otpService->sendOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your registered mobile number.',
            'data'    => [
                'expires_in_minutes' => Setting::getLoyaltyOtpExpiry(),
            ],
        ]);
    }

    /**
     * Verify OTP for loyalty redemption (pre-check only, does not redeem).
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $isValid = $this->otpService->verifyOtp($user, $request->otp);

        return response()->json([
            'success' => $isValid,
            'message' => $isValid ? 'OTP verified successfully.' : 'Invalid or expired OTP.',
        ], $isValid ? 200 : 422);
    }
}
