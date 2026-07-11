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

            $managersQuery = User::role(['clinic_manager', 'clinic_head']);
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

    public function storeImage(Request $request, TreatmentSession $treatmentSession)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp',
        ]);

        $apiKey = config('project.openai_api_key');
        $image  = $request->file('image');

        $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
            ->timeout(60)
            ->attach(
                'file',
                fopen($image->getPathname(), 'r'),
                $image->getClientOriginalName()
            )
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'vision',
            ]);

        if (! $response->successful()) {
            \Illuminate\Support\Facades\Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'error' => 'OpenAI upload failed',
                'details' => $response->json(),
            ], 500);
        }

        $openaiFileId = $response->json('id');

        $treatmentSession->addMedia($image)
            ->withCustomProperties(['openai_file_id' => $openaiFileId])
            ->toMediaCollection('post_treatment_images', 'user_post_assessment_images');

        $data = [
            'post_images' => $treatmentSession->post_images,
            'file_id' => $openaiFileId
        ];

        return response()->json([
            'success' => true,
            'message' => 'Treatment session post images added successfully',
            'results' => $data
        ]);
    }

    public function deleteImage(TreatmentSession $treatmentSession, $mediaId)
    {
        $mediaItem = $treatmentSession->getMedia('post_treatment_images')->where('id', $mediaId)->first();

        if (!$mediaItem) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }

        $mediaItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    }

    public function deleteAllImage(TreatmentSession $treatmentSession)
    {
        $treatmentSession->clearMediaCollection('post_treatment_images');

        return response()->json([
            'success' => true,
            'message' => 'All images deleted successfully'
        ]);
    }

    public function savePostAssessment(Request $request, TreatmentSession $treatmentSession)
    {
        $request->validate([
            'post_feature_packet' => 'nullable|array',
            'post_diagnosis' => 'nullable|array',
        ]);

        $treatmentSession->update([
            'post_feature_packet' => $request->post_feature_packet,
            'post_diagnosis' => $request->post_diagnosis,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post-assessment saved successfully.',
            'results' => $treatmentSession
        ]);
    }
}
