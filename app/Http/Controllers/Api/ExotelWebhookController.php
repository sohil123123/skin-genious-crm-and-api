<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
// use App\Models\ExotelCallLog;

class ExotelWebhookController extends Controller
{
    public function saveExotelWebhookDataForPopup(Request $request)
    {
        $data = $request->all();
        $exists = false;

        // Save incoming call details if valid incoming call
        if (isset($data['CallSid']) && isset($data['Direction']) && strtolower($data['Direction']) === 'incoming') {
            // $createdDate = null;
            // if (!empty($data['Created'])) {
            //     $time = strtotime($data['Created']);
            //     $createdDate = $time ? date('Y-m-d H:i:s', $time) : date('Y-m-d H:i:s');
            // }

            // ExotelCallLog::create([
            //     'CallSid' => $data['CallSid'] ?? null,
            //     'CallFrom' => $data['CallFrom'] ?? null,
            //     'DialWhomNumber' => $data['DialWhomNumber'] ?? null,
            //     'Direction' => $data['Direction'] ?? null,
            //     'Created' => $createdDate ?? now(),
            // ]);

            $mobile = $data['CallFrom'] ?? null;
            if (!empty($mobile)) {
                $cleanDigits = preg_replace('/\D/', '', $mobile);
                $last10 = strlen($cleanDigits) >= 10 ? substr($cleanDigits, -10) : $cleanDigits;
                $exists = User::where(function ($query) use ($mobile, $last10) {
                    $query->where('mobile', $mobile)
                        ->orWhere('mobile', $last10)
                        ->orWhere('mobile', '0' . $last10)
                        ->orWhere('mobile', '91' . $last10)
                        ->orWhere('mobile', '+91' . $last10);

                    if (strlen($last10) === 10) {
                        $query->orWhere('mobile', 'LIKE', '%' . $last10);
                    }
                })->exists();
            }
        }

        return response()->json($exists);
    }
}
