<?php
 
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Models\TreatmentSession;
use Illuminate\Http\Request;
 
class TreatmentSessionController extends Controller
{
    public function saveIvPrepData(Request $request, TreatmentSession $treatmentSession)
    {
        $request->validate([
            'iv_prep_data' => 'required|array',
        ]);
 
        $treatmentSession->update([
            'iv_prep_data' => $request->iv_prep_data,
        ]);
 
        return response()->json([
            'success' => true,
            'message' => 'IV Preparation data saved successfully.',
            'results' => $treatmentSession
        ]);
    }

    public function updateStatus(Request $request, TreatmentSession $treatmentSession)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $treatmentSession->update([
            'status' => $request->status,
        ]);

        if ($treatmentSession->ivSession) {
            $treatmentSession->ivSession->update([
                'status' => $request->status,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Treatment Session status updated successfully.',
            'results' => $treatmentSession
        ]);
    }
}
