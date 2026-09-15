<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Str;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutoCaptureController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\CommonController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\FacialV34Controller;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TreatmentSessionController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\VisionQuantifierController;
use App\Http\Controllers\Api\FeaturePacketCvController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\MetaLeadWebhookController;
use App\Http\Controllers\Api\ExotelWebhookController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// ----------------- public (Frontend) -------------------------
Route::
        namespace('App\Http\Controllers\Api')->group(function () {

            Route::post('/login', [AuthController::class, 'login']);
            // Exotel Webhook
            Route::match(['get', 'post'], '/exotel/webhook', [ExotelWebhookController::class, 'saveExotelWebhookDataForPopup']);

            // WhatsApp Webhooks
            Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
            Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

            // Meta Lead Ads Webhooks
            // Public by design: Meta authenticates with the verify token on GET
            // and an HMAC signature on POST, both checked in the controller.
            Route::get('/webhooks/meta', [MetaLeadWebhookController::class, 'verify']);
            Route::post('/webhooks/meta', [MetaLeadWebhookController::class, 'handle']);

            // Protected API routes with sanctum middleware
            Route::middleware(['auth:sanctum'])->group(function () {

                Route::get('device/connect/{clinic_id}', [AutoCaptureController::class, 'capturePhotos']);
                Route::get('device/pull-last-images/{clinic_id}', [AutoCaptureController::class, 'pullLastImages']);

                Route::get('/logout', [AuthController::class, 'logout']);

                // INFO: User CRUD Route
                Route::apiResource('users', 'UserController')->only(['index', 'show', 'update']);

                // INFO: Assessment CRUD Route
                Route::get('/assessments/get-in-progress-assessment/{user_id}', [AssessmentController::class, 'getInProgressAssessment']);
                Route::delete('/assessments/{assessment}/images/{assessment_type}', [AssessmentController::class, 'deleteAllImage']);
                Route::delete('/assessments/{assessment}/images/{media}/{assessment_type}', [AssessmentController::class, 'deleteImage']);
                Route::post('/assessments/{assessment}/images', [AssessmentController::class, 'storeImage']);
                Route::post('/assessments/clear-conversation-id', [AssessmentController::class, 'clearConversationId']);
                Route::apiResource('assessments', 'AssessmentController');

                // INFO: Treatment Plan CRUD Route
                // Route::apiResource('treatment-plans', 'TreatmentPlanController')->only(['index', 'show', 'destroy']);

                // INFO: Appointment CRUD Route
                // Availability engine
                Route::get('/availability/slots', [AppointmentController::class, 'getSlots']);
                Route::post('appointments/update-treatment-session-id/{appointment_id}', [AppointmentController::class, 'updateTreatmentSessionId']);
                Route::post('appointments/status/{id}', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
                Route::apiResource('appointments', 'AppointmentController');

                // INFO: Common Route
                Route::get('get-clinics', [CommonController::class, 'getClinics']);
                Route::get('get-users', [CommonController::class, 'getUsers']);
                Route::get('get-assessments', [CommonController::class, 'getAssessments']);
                Route::get('get-treatment-sessions', [CommonController::class, 'getTreatmentSessions']);

                // INFO: AI Route
                Route::post('/ai/conversations', [AiController::class, 'conversations']);
                Route::post('/ai/responses', [AiController::class, 'responses']);

                // INFO: Facial V3.4 staging routes (5-light-mode only)
                Route::prefix('facial-v34')->group(function () {
                    Route::get('/self-test', [FacialV34Controller::class, 'selfTest']);
                    Route::post('/assessment/{assessment}', [FacialV34Controller::class, 'assessment']);
                    Route::get('/assessment/{assessment}', [FacialV34Controller::class, 'assessmentResult']);
                    Route::post('/treatment-plan/{assessment}', [FacialV34Controller::class, 'treatmentPlan']);
                    Route::post('/reassessment/{assessment}', [FacialV34Controller::class, 'reassessment']);
                    Route::post('/planning-context/{assessment}', [FacialV34Controller::class, 'planningContext']);
                    Route::post('/course-block/{assessment}', [FacialV34Controller::class, 'saveCourseBlock']);
                });

                Route::post('/treatment-sessions/{treatmentSession}/iv-prep-data', [TreatmentSessionController::class, 'saveIvPrepData']);
                Route::post('/treatment-sessions/status/{treatmentSession}', [TreatmentSessionController::class, 'updateStatus']);
                Route::post('/treatment-sessions/{treatmentSession}/images', [TreatmentSessionController::class, 'storeImage']);
                Route::delete('/treatment-sessions/{treatmentSession}/images/{media}', [TreatmentSessionController::class, 'deleteImage']);
                Route::delete('/treatment-sessions/{treatmentSession}/images', [TreatmentSessionController::class, 'deleteAllImage']);
                Route::post('/treatment-sessions/{treatmentSession}/post-assessment', [TreatmentSessionController::class, 'savePostAssessment']);

                // INFO: Download Report
                Route::get('download-facial-report/{type}/{assessment_id}', [ReportController::class, 'downloadFacialReport'])->name('download-facial-report');
                Route::get('download-homecare-routine/{assessment_id}/{session_id}', [ReportController::class, 'downloadHomeCareRoutine'])->name('download-homecare-routine');
                Route::get('download-client-journey/{assessment_id}', [ReportController::class, 'downloadClientJourney'])->name('download-client-journey');
                Route::get('download-reassessment-zip/{assessment_id}', [ReportController::class, 'downloadAllReassessmentZip'])->name('download-all-reassessment-zip');

                Route::get('download-iv-report/{type}/{assessment_id}', [ReportController::class, 'downloadIvReport'])->name('download-iv-report');

                // INFO: Pigmentation Report PDF Downloads
                Route::get('download-pigmentation-report/diagnosis/{id}', [ReportController::class, 'downloadPigmentationDiagnosis'])->name('download-pigmentation-report.diagnosis');
                Route::get('download-pigmentation-report/treatment-plan/{id}', [ReportController::class, 'downloadPigmentationTreatmentPlan'])->name('download-pigmentation-report.treatment-plan');
                Route::get('download-pigmentation-report/reassessment/{id}', [ReportController::class, 'downloadPigmentationPostDiagnosis'])->name('download-pigmentation-report.reassessment');

                // INFO: Loyalty Points Routes
                Route::prefix('loyalty')->group(function () {
                    Route::get('/balance', [LoyaltyController::class, 'balance']);
                    Route::get('/history', [LoyaltyController::class, 'history']);
                    Route::post('/request-otp', [LoyaltyController::class, 'requestOtp']);
                    Route::post('/verify-otp', [LoyaltyController::class, 'verifyOtp']);
                });

                // INFO: Vision Quantifier Route
                Route::post('/vision/quantify', [VisionQuantifierController::class, 'quantify']);

                // INFO: Feature Packet CV Route
                Route::post('/feature-packet-cv/quantify', [FeaturePacketCvController::class, 'quantify']);

                // // INFO: WhatsApp Template Management Routes
                // Route::prefix('whatsapp/templates')->group(function () {
                //     Route::get('/', [WhatsAppTemplateController::class, 'index']);
                //     Route::post('/', [WhatsAppTemplateController::class, 'store']);
                //     Route::post('/sync', [WhatsAppTemplateController::class, 'sync']);
                //     Route::get('/{id}', [WhatsAppTemplateController::class, 'show']);
                //     Route::put('/{id}', [WhatsAppTemplateController::class, 'update']);
                //     Route::delete('/{id}', [WhatsAppTemplateController::class, 'destroy']);
                // });

            });

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
    if (!$request->hasValidSignature())
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
    if (in_array($request->role, ['clinic_manager', 'therapist', 'clinic_head']))
        $url .= '&clinic_id=' . $user->clinic_id;

    if ($request->role === 'therapist')
        $url .= '&therapist_id=' . $user->id;

    return redirect()->away($url);
})
    ->name('vue.sso');
