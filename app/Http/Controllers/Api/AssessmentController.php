<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Http\Requests\AssessmentRequest;

use App\Http\Resources\AssessmentResource;

use App\Models\Assessment;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Storage;

use App\Models\IvSession;
use App\Models\IvSessionBag;
use App\Models\IvSessionIngredient;
use App\Models\IvSessionSnapshot;
use App\Models\TreatmentSession;

class AssessmentController extends BaseApiController
{
    public function __construct(Assessment $model, Request $request)
    {
        parent::__construct($model, $request, 'Assessment', 'Api');
    }

    public function store(AssessmentRequest $request)
    {
        // Create the assessment record
        $assessment = $this->model->create($request->validated());

        // Upload multiple images to the 'assessment_images' collection
        if ($request->hasFile('images')) {
            $assessment->addMultipleMediaFromRequest(['images'])
                       ->each(function ($fileAdder) {
                           $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                       });
        }

        // Wrap in resource for clean, formatted API output
        $resource = new AssessmentResource($assessment);

        return $this->success('Assessment created successfully', $resource);
    }

    public function update(AssessmentRequest $request, Assessment $assessment)
    {
        // Create the assessment record
        $update_input = $request->validated();
        unset($update_input['treatment_plans']);
        $update_input['total_time'] = $request->treatment_plans['treatment_plans']['total_time'] ?? NULL;

        if($request->has('selected_plan_type') && ($request->selected_plan_type == 'single' || $request->selected_plan_type == 'multiple')){
            $update_input['recommended_full_plan'] = !empty($request->treatment_plans['recommended_full_plan']) ? $request->treatment_plans['recommended_full_plan'] : NULL;
        }

        $assessment->update($update_input);

        // Update therapist_id for the associated appointments if therapist_id is provided in the request
        if ($request->filled('therapist_id')) {
            $treatmentSessions = isset($assessment->treatmentSessions['treatments'][0]) ? $assessment->treatmentSessions['treatments'][0] : null;
            if($treatmentSessions){
                \App\Models\Appointment::where('assessment_id', $assessment->id)
                    ->where('treatment_session_id', $treatmentSessions['id'])
                    ->where('type', 'treatment')
                    ->get()
                    ->each(function ($appointment) use ($request) {
                        $appointment->update(['therapist_id' => $request->input('therapist_id')]);
                    });
            }
        }

        // Save Treatment Plans json file
        if ($request->filled('treatment_plans')) {
            $fileName = 'treatment_plans_#' . $assessment->id . '.json';
            $jsonData = json_encode($request->treatment_plans, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            create_directory_if_not_exist('files', 'treatment-plans');
            Storage::disk('files')->put('treatment-plans/' . $fileName, $jsonData);
        }

        // Create treatment planes record
        if($request->has('treatment_plans') && !empty($request->treatment_plans['treatment_plan']['treatments'])){
            foreach ($request->treatment_plans['treatment_plan']['treatments'] as $key => $treatment) {
                if(($assessment->selected_plan_type == 'single' || $assessment->selected_plan_type == 'express') && $key > 0) continue;
                $treatmentSession = $assessment->treatmentSessions()->updateOrCreate(
                    ['assessment_id' => $assessment->id, 'session_number' => $treatment['session_number']],
                    [
                    'user_id' => $assessment->user_id,
                    'plan_type' => $assessment->selected_plan_type,
                    'title' => $treatment['title'] ?? '',
                    'treatment_time' => $treatment['treatment_time'] ?? null,
                    'week' => $treatment['week'] ?? null,
                    'preparations_checklist_for_therapist' => $treatment['preparations_checklist_for_therapist'] ?? [],
                    'concerns_addressed' => $treatment['concerns_addressed'] ?? [],
                    'steps' => $treatment['steps'] ?? [],
                    'daily_home_care_routine' => $treatment['daily_home_care_routine'] ?? [],
                    'audio_text' => $treatment['script'] ?? null,
                ]);

                // Create appointment for the first session only if therapist is selected
                if ($key == 0) {
                    \App\Models\Appointment::firstOrCreate(
                        [
                            'assessment_id' => $assessment->id,
                            'treatment_session_id' => $treatmentSession->id,
                        ],
                        [
                            'type' => 'treatment',
                            'clinic_id' => $assessment->clinic_id,
                            'user_id' => $assessment->user_id,
                            'therapist_id' => $request->input('therapist_id'),
                            'status' => 'confirmed',
                            'start_datetime' => now(),
                            'end_datetime' => now()->addMinutes(isset($treatment['treatment_time']) ? (int) filter_var($treatment['treatment_time'], FILTER_SANITIZE_NUMBER_INT) : null),
                            'duration_minutes' => isset($treatment['treatment_time']) ? (int) filter_var($treatment['treatment_time'], FILTER_SANITIZE_NUMBER_INT) : null,
                        ]
                    );
                }
            }
        }

        // Process IV Treatment Sessions
        if ($request->has('iv_selected_option') && is_array($request->iv_selected_option)) {
             $this->processIvTreatmentSessions($request->iv_selected_option, $assessment);
        } elseif ($request->has('treatment_sessions')) {
             $this->processIvTreatmentSessions($request->treatment_sessions, $assessment);
        }

        // Upload multiple images to the 'assessment_images' collection
        if ($request->hasFile('images')) {
            $assessment->addMultipleMediaFromRequest(['images'])
                       ->each(function ($fileAdder) {
                           $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                       });
        }

        // Upload multiple images to the 'post_assessment_images' collection
        if ($request->hasFile('post_images')) {
            $assessment->addMultipleMediaFromRequest(['post_images'])
                       ->each(function ($fileAdder) {
                           $fileAdder->toMediaCollection('post_assessment_images', 'user_post_assessment_images');
                       });
        }

        // Wrap in resource for clean, formatted API output
        $resource = new AssessmentResource($assessment);

        return $this->success('Assessment updated successfully', $resource);
    }

    private function processIvTreatmentSessions($treatmentSessionsData, Assessment $assessment)
    {
        // Handle if it's a string (e.g. "single_session_option_1")
        if (!is_array($treatmentSessionsData)) {
            return;
        }

        // Determine if we are dealing with the new iv_selected_option object structure
        $isNewFormat = isset($treatmentSessionsData['option_type']);

        $isPlan = false;
        $sessionsToProcess = [];
        $selectedOptionType = 'single_session_option_1';
        $engineVersions = null;
        $planMetadata = [];

        if ($isNewFormat) {
            $isPlan = ($treatmentSessionsData['option_type'] ?? '') === 'plan_option';

            if ($isPlan) {
                if (!empty($treatmentSessionsData['protocols'][0]['sessions'])) {
                    $sessionsToProcess = $treatmentSessionsData['protocols'][0]['sessions'];
                } elseif (!empty($treatmentSessionsData['sessions'])) {
                    $sessionsToProcess = $treatmentSessionsData['sessions'];
                } else {
                    $sessionsToProcess = [];
                }
            } else {
                $sessionsToProcess = $treatmentSessionsData['protocols'] ?? ($treatmentSessionsData['sessions'] ?? []);
            }

            $selectedOptionType = $treatmentSessionsData['option_type'] ?? ($isPlan ? 'plan_option' : 'single_session_option_1');

            // Plan-level metadata for snapshots
            $planMetadata = [
                'constraint_report' => $treatmentSessionsData['constraint_report'] ?? null,
                'dominant_axis_explainability' => $treatmentSessionsData['dominant_axis_explainability'] ?? null,
                'outcome_intent_structured' => $treatmentSessionsData['outcome_intent_structured'] ?? null,
                'client_facing_explanation' => $treatmentSessionsData['client_facing_explanation'] ?? null,
                'ui_constraint_flags' => $treatmentSessionsData['ui_constraint_flags'] ?? null,
                'plan_duration_weeks' => $treatmentSessionsData['protocols'][0]['plan_duration_weeks'] ?? ($treatmentSessionsData['plan_duration_weeks'] ?? null),
                'schedule_description' => $treatmentSessionsData['protocols'][0]['schedule_description'] ?? ($treatmentSessionsData['schedule_description'] ?? null),
                'name' => $treatmentSessionsData['name'] ?? null,
            ];
        } else {
            $ivSessionData = $treatmentSessionsData['iv_session_data'] ?? [];
            $isPlanInput = $ivSessionData['is_plan'] ?? false;
            $isPlan = filter_var($isPlanInput, FILTER_VALIDATE_BOOLEAN);

            // Legacy support: check protocol_id to detect plan
            if (!$isPlan && !empty($treatmentSessionsData['treatments'])) {
                $firstTreatment = $treatmentSessionsData['treatments'][0] ?? [];
                $isPlan = Str::contains($firstTreatment['protocol_id'] ?? '', 'PLAN');
            }

            if ($isPlan && !empty($ivSessionData['all_plan_sessions'])) {
                $sessionsToProcess = $ivSessionData['all_plan_sessions'];
            } elseif (!empty($treatmentSessionsData['treatments'])) {
                $sessionsToProcess = $treatmentSessionsData['treatments'];
            }

            $selectedOptionType = $ivSessionData['selected_option_type'] ?? ($isPlan ? 'plan_option' : 'single_session_option_1');
            $engineVersions = $ivSessionData['engine_versions'] ?? null;
        }

        // Override selected_option_type if top-level iv_selected_option is a string
        if (request()->has('iv_selected_option') && is_string(request()->iv_selected_option)) {
            $selectedOptionType = request()->iv_selected_option;
        }

        if (empty($sessionsToProcess)) {
            return;
        }

        // Delete existing IV-related treatment sessions to clear previous plan selections
        $assessment->treatmentSessions()
            ->whereHas('ivSession')
            ->get()
            ->each(function ($session) {
                if ($session->ivSession) {
                    $session->ivSession->ingredients()->delete();
                    $session->ivSession->bags()->delete();
                    $session->ivSession->snapshots()->delete();
                    $session->ivSession->delete();
                }
                $session->delete();
            });

        foreach ($sessionsToProcess as $index => $sessionItem) {
            // Determine if we're looking at a plan session item or a direct treatment object
            $isPlanSessionItem = isset($sessionItem['recommended_protocol']);
            $treatment = $isPlanSessionItem ? $sessionItem['recommended_protocol'] : $sessionItem;

            if (empty($treatment)) {
                continue;
            }

            $planType = $isPlan ? 'multiple' : 'single';
            $sessionNumber = $index + 1;

            $treatmentTimeStr = null;
            if (isset($treatment['ui_summary']['estimated_total_duration_minutes'])) {
                $treatmentTimeStr = $treatment['ui_summary']['estimated_total_duration_minutes'] . ' mins';
            } elseif (isset($treatment['bags']) && is_array($treatment['bags'])) {
                $minutes = 0;
                foreach ($treatment['bags'] as $bag) {
                    $minutes += $bag['min_duration_minutes'] ?? 0;
                }
                if ($minutes > 0) {
                    $treatmentTimeStr = $minutes . ' mins';
                }
            }

            $treatmentSession = $assessment->treatmentSessions()->updateOrCreate(
                [
                    'session_number' => $sessionNumber,
                ],
                [
                    'user_id' => $assessment->user_id,
                    'plan_type' => $planType,
                    'title' => $treatment['label_short'] ?? 'IV Session',
                    'status' => 'pending',
                    'week' => $isPlan ? ($sessionItem['week_index'] ?? $sessionNumber) : null,
                    'treatment_time' => $treatmentTimeStr,
                    'steps' => null,
                    'concerns_addressed' => null,
                    'preparations_checklist_for_therapist' => null,
                    'daily_home_care_routine' => null,
                    'audio_text' => null,
                ]
            );

            // IV Session
            $ivSession = IvSession::updateOrCreate(
                ['treatment_session_id' => $treatmentSession->id],
                [
                    'assessment_id' => $assessment->id,
                    'user_id' => $assessment->user_id,
                    'selected_protocol_id' => $treatment['protocol_id'] ?? null,
                    'selected_option_type' => $selectedOptionType,
                    'is_plan' => $isPlan,
                    'plan_week_index' => $isPlan ? ($sessionItem['week_index'] ?? $index) : null,
                    'status' => 'pending',
                    'engine_versions' => $engineVersions,
                ]
            );

            // Snapshots
            $generationOutput = [
                'ui_summary' => $treatment['ui_summary'] ?? null,
                'axis_targeting_intent' => $treatment['axis_targeting_intent'] ?? null,
                'budget_candidate_optional' => $treatment['budget_candidate_optional'] ?? null,
                'intended_benefits_tags' => $treatment['intended_benefits_tags'] ?? null,
                'phase_id' => $sessionItem['phase_id'] ?? null,
                'session_goal_summary' => $sessionItem['session_goal_summary'] ?? null,
                'candidate_generation_hint' => $sessionItem['candidate_generation_hint'] ?? null,
                'note' => $sessionItem['note'] ?? null,
            ];

            if (!empty($planMetadata)) {
                $generationOutput['plan_metadata'] = $planMetadata;
            }

            IvSessionSnapshot::updateOrCreate(
                ['iv_session_id' => $ivSession->id],
                [
                    'generation_output' => $generationOutput
                ]
            );

            // Clear old children (bags/ingredients) for fresh update
            $ivSession->ingredients()->delete();
            $ivSession->bags()->delete();

            if (isset($treatment['bags']) && is_array($treatment['bags'])) {
                foreach ($treatment['bags'] as $bagData) {
                    $bag = $ivSession->bags()->create([
                        'bag_label' => $bagData['bag_id'] ?? null,
                        'carrier' => $bagData['carrier'] ?? null,
                        'volume_ml' => $bagData['bag_size_ml'] ?? null,
                        'min_duration_minutes' => $bagData['min_duration_minutes'] ?? null,
                        'rate_profile' => isset($bagData['rate_profile']) ? ['profile' => $bagData['rate_profile']] : null,
                    ]);

                    if (isset($bagData['ingredients'])) {
                        foreach ($bagData['ingredients'] as $ing) {
                            $isHero = in_array($ing['name'], $treatment['hero_ingredients'] ?? []);

                            $ivSession->ingredients()->create([
                                'iv_session_bag_id' => $bag->id,
                                'ingredient_name' => $ing['name'],
                                'dose_value' => $ing['dose_mg_optional'] ?? null,
                                'dose_unit' => $ing['dose_units_optional'] ?? null,
                                'is_hero' => $isHero,
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function storeImage(Request $request, Assessment $assessment)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp',
            'assessment_type' => 'required|in:pre,post,pigmentation-pre,pigmentation-post',
            'mode' => 'nullable|string'
        ]);

        $apiKey = config('project.openai_api_key');
        $image  = $request->file('image');

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->attach(
                'file',
                fopen($image->getPathname(), 'r'),
                $image->getClientOriginalName()
            )
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'vision', // REQUIRED
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'error' => 'OpenAI upload failed',
                'details' => $response->json(),
            ], 500);
        }

        $openaiFileId = $response->json('id');

        // Save media (your existing logic)
        if ($request->assessment_type === 'pre') {
            $assessment->addMedia($image)
                ->withCustomProperties(['openai_file_id' => $openaiFileId, 'mode' => $request->mode])
                ->toMediaCollection('assessment_images', 'user_assessment_images');
        } elseif ($request->assessment_type === 'pigmentation-pre') {
            $assessment->addMedia($image)
                ->withCustomProperties(['openai_file_id' => $openaiFileId, 'mode' => $request->mode])
                ->toMediaCollection('pigmentation_pre_assessment_images', 'user_pigmentation_pre_assessment_images');
        } elseif ($request->assessment_type === 'post') {
            $assessment->addMedia($image)
                ->withCustomProperties(['openai_file_id' => $openaiFileId, 'mode' => $request->mode])
                ->toMediaCollection('post_assessment_images', 'user_post_assessment_images');
        } elseif ($request->assessment_type === 'pigmentation-post') {
            $customProps = ['openai_file_id' => $openaiFileId, 'mode' => $request->mode];
            if ($request->has('is_panel')) {
                $customProps['is_panel'] = filter_var($request->is_panel, FILTER_VALIDATE_BOOLEAN);
            }
            $assessment->addMedia($image)
                ->withCustomProperties($customProps)
                ->toMediaCollection('pigmentation_post_assessment_images', 'user_pigmentation_post_assessment_images');
        }

        $data = [
            'images' => $assessment->images,
            'post_images' => $assessment->post_images,
            'file_id' => $openaiFileId
        ];

        return $this->success('Assessment user images added successfully', $data);
    }

    public function deleteImage(Assessment $assessment, $mediaId, $assessment_type)
    {
        if ($assessment_type === 'pre') {
            $collection_name = 'assessment_images';
        } elseif ($assessment_type === 'pigmentation-pre') {
            $collection_name = 'pigmentation_pre_assessment_images';
        } elseif ($assessment_type === 'pigmentation-post') {
            $collection_name = 'pigmentation_post_assessment_images';
        } else {
            $collection_name = 'post_assessment_images';
        }

        $mediaItem = $assessment->getMedia($collection_name)->where('id', $mediaId)->first();

        if (!$mediaItem) {
            return $this->error('Not Found Error.', ['Image not found'], HTTP_NOT_FOUND);
        }

        $mediaItem->delete();

        return $this->success('Image deleted successfully');
    }

    public function deleteAllImage(Assessment $assessment, $assessment_type)
    {
        if ($assessment_type === 'pre') {
            $collection_name = 'assessment_images';
        } elseif ($assessment_type === 'pigmentation-pre') {
            $collection_name = 'pigmentation_pre_assessment_images';
        } elseif ($assessment_type === 'pigmentation-post') {
            $collection_name = 'pigmentation_post_assessment_images';
        } else {
            $collection_name = 'post_assessment_images';
        }

        $assessment->clearMediaCollection($collection_name);

        return $this->success('All Image deleted successfully');
    }

    public function getInProgressAssessment(Request $request, $user_id){
        $assessment_type = $request->type;

        if($assessment_type == 'iv'){
            $filter_types = ['iv', 'instant-iv'];
        }else{
            $filter_types = ['instant-normal', 'normal'];
        }

        // $yesterday = Carbon::yesterday();
        // $assessment = $this->model->whereBetween('created_at', [$yesterday, now()])
        $assessment = $this->model->where('user_id', $user_id)
            ->where('status', 'in_progress')
            ->whereIn('assessment_type', $filter_types)
            ->latest()
            ->first();
        if(!$assessment)
            return $this->error('No in progress assessment found', null, config('constants.NOT_FOUND'));
        else
            return $this->success('In Progress Assessment get successfully', ['assessment_id' => $assessment->id, 'created_at' => $assessment->created_at]);
    }

    public function clearConversationId(Request $request)
    {
        $request->validate([
            'assessment_id' => 'required|exists:assessments,id',
        ]);

        $assessment = $this->model->find($request->assessment_id);

        if (!$assessment) {
            return $this->error('Assessment not found', null, config('constants.NOT_FOUND', 404));
        }

        $assessment->update(['conversation_id' => null]);

        return $this->success('Conversation ID cleared successfully');
    }
}
