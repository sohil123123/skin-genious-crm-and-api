<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Str;

use App\Http\Controllers\Api\AppointmentController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// ----------------- public (Frontend) -------------------------
Route::namespace('App\Http\Controllers\Api')->group(function () {

    Route::post('/login', 'AuthController@login');

    Route::get('device/connect', 'AutoCaptureController@capturePhotos');

    // Protected API routes with sanctum middleware
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/logout', 'AuthController@logout');

        // INFO: User CRUD Route
        Route::apiResource('users', 'UserController')->only(['index', 'show']);

        // INFO: Assessment CRUD Route
        Route::get('/assessments/get-in-progress-assessment/{user_id}', 'AssessmentController@getInProgressAssessment');
        Route::delete('/assessments/{assessment}/images/{assessment_type}', 'AssessmentController@deleteAllImage');
        Route::delete('/assessments/{assessment}/images/{media}/{assessment_type}', 'AssessmentController@deleteImage');
        Route::post('/assessments/{assessment}/images', 'AssessmentController@storeImage');
        Route::apiResource('assessments', 'AssessmentController');

        // INFO: Treatment Plan CRUD Route
        Route::apiResource('treatment-plans', 'TreatmentPlanController')->only(['index', 'show', 'destroy']);

        // INFO: Appointment CRUD Route
        // Availability engine
        Route::get('/availability/slots', [AppointmentController::class, 'getSlots']);
        Route::post('appointments/update-treatment-session-id/{appointment_id}', [AppointmentController::class, 'updateTreatmentSessionId']);
        Route::post('appointments/status/{id}', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::apiResource('appointments', 'AppointmentController');

        // INFO: Common Route
        Route::get('get-clinics', 'CommonController@getClinics');
        Route::get('get-users', 'CommonController@getUsers');

    });

    // Route::get('/availability/slots', [AppointmentController::class, 'slots']);
    // Route::apiResource('appointments', 'AppointmentController');

});

Route::middleware('auth:sanctum')->get('/validate-assessment-token', function (Request $request) {
    // Sanctum middleware auto-validates the token
    $token = $request->bearerToken();
    $personalToken = PersonalAccessToken::findToken($token);

    // if (!$personalToken || !$personalToken->tokenable || $personalToken->expires_at->isPast()) {
    if (!$personalToken || !$personalToken->tokenable) {
        return response()->json(['valid' => false], 401);
    }

    // Check scope/ability
    if (!$personalToken->can('assessment')) {
        return response()->json(['valid' => false], 403);
    }

    return response()->json([
        'valid' => true,
        'access_token' => $token,
        'auth_user_id' => $personalToken->tokenable->id,
    ]);
});


Route::get('/vue-sso', function (Request $request) {

    // ❌ Invalid or expired signature
    if (! $request->hasValidSignature())
        return response()->json(['message' => 'Invalid or expired link'], 401);

    $user = \App\Models\User::findOrFail($request->user_id);

    // (Optional but recommended) Revoke old tokens
    $user->tokens()
        ->where('name', 'like', 'appointment-token-%')
        ->delete();

    // 🔐 Issue Sanctum token
    $token = $user->createToken(
        'appointment-token-' . Str::random(10),
        ['assessment'],
        now()->addHour() // optional expiry
    )->plainTextToken;

    // 🌍 Frontend base URL
    $frontend = rtrim(config('project.frontend_url'), '/');

    $url = $frontend . '/authenticate?token=' . $token . '&type=appointment';

    // 🔁 Role-based redirect
    if(in_array($request->role, ['clinic_manager', 'therapist']))
        $url .= '&clinic_id=' . $user->clinic_id;

    if ($request->role === 'therapist')
        $url .= '&therapist_id=' . $user->id;

    return redirect()->away($url);
})
->name('vue.sso');
