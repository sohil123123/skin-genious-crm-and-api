<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Models\Assessment;

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
    public function store(Request $request)
    {
        // Validate the request (adjust as needed)
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'images.*' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // Each image: max 2MB
        ]);

        // Create the assessment record
        $assessment = $this->model->create([
            'user_id' => $validated['user_id'],
        ]);

        // Upload multiple images to the 'assessment_images' collection
        if ($request->hasFile('images')) {
            $assessment->addMultipleMediaFromRequest(['images'])
                       ->each(function ($fileAdder) {
                           $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                       });
        }

        return $this->success('Assessment created successfully', $assessment);
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
    public function update(Request $request, Assessment $assessment)
    {
        // Validate the request (adjust as needed)
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'images.*' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // Each image: max 2MB
        ]);

        // Create the assessment record
        $assessment->update([
            'user_id' => $validated['user_id'],
        ]);

        // Upload multiple images to the 'assessment_images' collection
        if ($request->hasFile('images')) {
            $assessment->addMultipleMediaFromRequest(['images'])
                       ->each(function ($fileAdder) {
                           $fileAdder->toMediaCollection('assessment_images', 'user_assessment_images');
                       });
        }

        return $this->success('Assessment updated successfully', $assessment);
    }

    /**
     * Remove the specified resource from storage.
     */
    // public function destroy(string $id)
    // {
    //     //
    // }

    public function deleteImage(Assessment $assessment, $mediaId)
    {
        $mediaItem = $assessment->getMedia('assessment_images')->where('id', $mediaId)->first();

        if (!$mediaItem) {
            return $this->error('Not Found Error.', ['Image not found'], HTTP_NOT_FOUND);
        }

        $mediaItem->delete();

        return $this->success('Image deleted successfully');
    }
}
