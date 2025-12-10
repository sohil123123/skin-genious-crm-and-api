<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Http\Requests\AssessmentRequest;

use App\Http\Resources\AssessmentResource;

use App\Models\Assessment;

use Carbon\Carbon;

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

        if($request->has('selected_plan_type') && $request->selected_plan_type == 'single'){
            $update_input['recommended_full_plan'] = !empty($request->treatment_plans['recommended_full_plan']) ? $request->treatment_plans['recommended_full_plan'] : NULL;
        }

        $assessment->update($update_input);
        // dd($request->treatment_plans);
        // \Log::info($request->all());
        // Create treatment planes record
        if($request->has('treatment_plans') && !empty($request->treatment_plans['treatment_plan']['treatments'])){
            foreach ($request->treatment_plans['treatment_plan']['treatments'] as $key => $treatment) {
                if($assessment->selected_plan_type == 'single' && $key > 0) continue;
                $assessment->treatmentSessions()->updateOrCreate(
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
                ]);
            }
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

    public function storeImage(Request $request, Assessment $assessment)
    {
        // Validate the request (adjust as needed)
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,gif,webp',
            'assessment_type' => 'required|in:pre,post'
        ]);

        if($request->assessment_type == 'pre'){
            $assessment->addMultipleMediaFromRequest(['images'])
                        ->each(function ($fileAdder) {
                            $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                        });
        }else{
            if ($request->hasFile('images')) {
                $assessment->addMultipleMediaFromRequest(['images'])
                        ->each(function ($fileAdder) {
                            $fileAdder->toMediaCollection('post_assessment_images', 'user_post_assessment_images');
                        });
            }
        }

        // Wrap in resource for clean, formatted API output
        $resource = new AssessmentResource($assessment);

        return $this->success('Assessment user images added successfully', $resource);
    }

    public function deleteImage(Assessment $assessment, $mediaId, $assessment_type)
    {
        $collection_name = $assessment_type == 'pre' ? 'assessment_images' : 'post_assessment_images';

        $mediaItem = $assessment->getMedia($collection_name)->where('id', $mediaId)->first();

        if (!$mediaItem) {
            return $this->error('Not Found Error.', ['Image not found'], HTTP_NOT_FOUND);
        }

        $mediaItem->delete();

        return $this->success('Image deleted successfully');
    }

    public function deleteAllImage(Assessment $assessment, $assessment_type)
    {
        $collection_name = $assessment_type == 'pre' ? 'assessment_images' : 'post_assessment_images';

        $assessment->clearMediaCollection($collection_name);

        return $this->success('All Image deleted successfully');
    }

    public function getInProgressAssessment(Request $request, $user_id){
        $yesterday = Carbon::yesterday();
        $assessment = $this->model->whereBetween('created_at', [$yesterday, now()])
            ->where('user_id', $user_id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();
        if(!$assessment)
            return $this->error('No in progress assessment found', null, config('constants.NOT_FOUND'));
        else
            return $this->success('In Progress Assessment get successfully', ['assessment_id' => $assessment->id, 'created_at' => $assessment->created_at]);
    }
}
