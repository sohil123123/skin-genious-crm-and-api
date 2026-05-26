<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TreatmentSession;
use Illuminate\Http\Request;
use Filament\Notifications\Notification;
use App\Models\User;

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

        if ($request->status === 'completed') {
            $clinicId = $treatmentSession->user->clinic_id ?? ($treatmentSession->assessment->clinic_id ?? null);

            $managersQuery = User::role('clinic_manager');
            if ($clinicId) {
                $managersQuery->where('clinic_id', $clinicId);
            }
            $managers = $managersQuery->get();

            $superAdmins = User::role('super_admin')->get();
            $recipients = $managers->merge($superAdmins)->unique('id');

            if ($recipients->isNotEmpty()) {
                $clientName = $treatmentSession->user ? $treatmentSession->user->name : 'Unknown Client';
                Notification::make()
                    ->title('Treatment Completed')
                    ->body("Treatment session for {$clientName} has been completed. Please perform the post-assessment and generate the home care routine.")
                    ->success()
                    ->sendToDatabase($recipients)
                    ->broadcast($recipients);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Treatment Session status updated successfully.',
            'results' => $treatmentSession
        ]);
    }
}
