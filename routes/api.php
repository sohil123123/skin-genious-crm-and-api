<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// ----------------- public (Frontend) -------------------------
Route::namespace('App\Http\Controllers\Api')->group(function () {

    Route::post('/login', 'AuthController@login');

    // Protected API routes with sanctum middleware
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/logout', 'AuthController@logout');

        // INFO: User CRUD Route
        Route::apiResource('users', 'UserController')->only(['index', 'show']);
    });

});

Route::middleware('auth:sanctum')->get('/validate-assessment-token', function (Request $request) {
    // Sanctum middleware auto-validates the token
    $token = $request->bearerToken();
    $personalToken = PersonalAccessToken::findToken($token);

    if (!$personalToken || !$personalToken->tokenable || $personalToken->expires_at->isPast()) {
        return response()->json(['valid' => false], 401);
    }

    // Check scope/ability
    if (!$personalToken->can('assessment')) {
        return response()->json(['valid' => false], 403);
    }

    // Return user/patient details if needed (e.g., for fetching patient data)
    return response()->json([
        'valid' => true,
        'user_id' => $personalToken->tokenable->id,
    ]);
});
