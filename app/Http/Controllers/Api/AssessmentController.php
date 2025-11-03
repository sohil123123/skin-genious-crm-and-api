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

    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     //
    // }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    // public function show(string $id)
    // {
    //     //
    // }

    /**
     * Update the specified resource in storage.
     */
    public function update(AssessmentRequest $request, Assessment $assessment)
    {
        // Create the assessment record
        $assessment->update($request->validated());

        // Upload multiple images to the 'assessment_images' collection
        if ($request->hasFile('images')) {
            $assessment->addMultipleMediaFromRequest(['images'])
                       ->each(function ($fileAdder) {
                           $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                       });
        }

        // Wrap in resource for clean, formatted API output
        $resource = new AssessmentResource($assessment);

        return $this->success('Assessment updated successfully', $resource);
    }

    /**
     * Remove the specified resource from storage.
     */
    // public function destroy(string $id)
    // {
    //     //
    // }

    public function storeImage(Request $request, Assessment $assessment)
    {
        // Validate the request (adjust as needed)
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // Each image: max 2MB
        ]);

        $assessment->addMultipleMediaFromRequest(['images'])
                    ->each(function ($fileAdder) {
                        $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                    });

        // Wrap in resource for clean, formatted API output
        $resource = new AssessmentResource($assessment);

        return $this->success('Assessment user images added successfully', $resource);
    }

    public function deleteImage(Assessment $assessment, $mediaId)
    {
        $mediaItem = $assessment->getMedia('assessment_images')->where('id', $mediaId)->first();

        if (!$mediaItem) {
            return $this->error('Not Found Error.', ['Image not found'], HTTP_NOT_FOUND);
        }

        $mediaItem->delete();

        return $this->success('Image deleted successfully');
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
