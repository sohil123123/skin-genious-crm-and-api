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
        $validated = $request->validate([
            'post_feature_packet' => 'nullable|array',
            'post_diagnosis' => 'nullable|array',
        ]);

        // A failed save must never be treated as a completed checkpoint.
        // Partial feature updates must not clear an already saved reassessment.
        if (array_key_exists('post_diagnosis', $validated) && !array_key_exists('post_feature_packet', $validated)) {
            $validated['post_feature_packet'] = null;
        }
        $treatmentSession->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Post-assessment saved successfully.',
            'results' => $treatmentSession
        ]);
    }

    private function courseContinuationResponse(TreatmentSession $session, array $result)
    {
        if (($result['status'] ?? null) === 'ready') {
            app(\App\Services\FacialCourseContinuation::class)->syncLegacyPlan((int) $session->assessment_id);
            $result['assessment'] = new \App\Http\Resources\AssessmentResource($session->assessment()->firstOrFail());
        }
        return response()->json(['success' => true, 'results' => $result]);
    }

    public function getCourseContinuation(TreatmentSession $treatmentSession, \App\Services\FacialCourseContinuation $continuation)
    {
        return $this->courseContinuationResponse($treatmentSession, $continuation->inspect($treatmentSession));
    }

    public function claimCourseContinuation(TreatmentSession $treatmentSession, \App\Services\FacialCourseContinuation $continuation)
    {
        return $this->courseContinuationResponse($treatmentSession, $continuation->claim($treatmentSession));
    }

    public function completeCourseContinuation(Request $request, TreatmentSession $treatmentSession, \App\Services\FacialCourseContinuation $continuation)
    {
        $data = $request->validate([
            'token' => 'required|uuid',
            'generated_plan' => 'required|array',
            'generated_plan.treatment_plan' => 'required|array',
            'generated_plan.treatment_plan.course_outline' => 'required|array|min:5|max:8',
            'generated_plan.treatment_plan.treatments' => 'required|array|min:1|max:2',
            'generated_plan.treatment_plan.treatments.*.session_number' => 'required|integer|min:3|max:8',
            'generated_plan.treatment_plan.treatments.*.title' => 'required|string',
            'generated_plan.treatment_plan.treatments.*.week' => 'required|numeric|min:1',
            'generated_plan.treatment_plan.treatments.*.treatment_time' => 'required|numeric|min:60|max:75',
            'generated_plan.treatment_plan.treatments.*.preparations_checklist_for_therapist' => 'present|array',
            'generated_plan.treatment_plan.treatments.*.concerns_addressed' => 'present|array',
            'generated_plan.treatment_plan.treatments.*.steps' => 'required|array|min:1',
            'generated_plan.treatment_plan.treatments.*.steps.*.step_number' => 'required|integer|min:1',
            'generated_plan.treatment_plan.treatments.*.steps.*.ingredients_equipments' => 'present|array',
            'generated_plan.treatment_plan.treatments.*.steps.*.how_to_do' => 'required|string',
            'generated_plan.treatment_plan.treatments.*.steps.*.step_id' => 'required|string',
            'generated_plan.treatment_plan.treatments.*.steps.*.duration' => 'required|numeric|gt:0',
            'post_feature_packet' => 'required|array',
        ]);
        // Nested validation checks admission, but validated() omits unlisted keys.
        // Keep the complete planner output, as the initial plan save does.
        return $this->courseContinuationResponse($treatmentSession, $continuation->complete(
            $treatmentSession, $data['token'], $request->input('generated_plan'), $data['post_feature_packet']
        ));
    }

    public function releaseCourseContinuation(Request $request, TreatmentSession $treatmentSession, \App\Services\FacialCourseContinuation $continuation)
    {
        $data = $request->validate(['token' => 'required|uuid', 'message' => 'required|string|max:800']);
        return $this->courseContinuationResponse($treatmentSession, $continuation->release($treatmentSession, $data['token'], $data['message']));
    }
}
