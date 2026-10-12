<?php

use App\Enums\AssessmentSessionType;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Requests\AssessmentRequest;
use App\Models\Assessment;
use App\Models\TreatmentSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

it('writes JSON treatment sessions into the database with the selected plan type', function () {
    $previousConnection = DB::getDefaultConnection();
    config(['database.connections.treatment_save_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::setDefaultConnection('treatment_save_test');
    try {
        Schema::create('treatment_sessions', function (Blueprint $table) {
            $table->id();
            $table->integer('assessment_id');
            $table->integer('user_id');
            $table->string('plan_type');
            $table->integer('session_number');
            $table->string('title');
            $table->string('treatment_time')->nullable();
            $table->integer('week')->nullable();
            foreach (['preparations_checklist_for_therapist', 'concerns_addressed', 'steps', 'daily_home_care_routine'] as $field) {
                $table->json($field)->nullable();
            }
            $table->text('audio_text')->nullable();
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            foreach (['assessment_id', 'treatment_session_id', 'clinic_id', 'user_id', 'therapist_id', 'duration_minutes'] as $field) {
                $table->integer($field)->nullable();
            }
            foreach (['type', 'status', 'start_datetime', 'end_datetime'] as $field) {
                $table->string($field)->nullable();
            }
            $table->softDeletes();
            $table->timestamps();
        });
        Storage::fake('files');
        $session = [
            'session_number' => 1, 'title' => 'Saved fixture', 'treatment_time' => 60, 'week' => 1,
            'steps' => [['step_number' => 1, 'duration' => 2, 'settings_note' => null]],
            'concerns_addressed' => [['concern' => 'Fixture', 'target_value' => null]],
        ];
        $payload = ['user_id' => 21, 'selected_plan_type' => 'multiple', 'treatment_plans' => [
            'treatment_plan' => ['total_time' => 'Two sessions', 'treatments' => [
                $session, array_merge($session, ['session_number' => 2, 'week' => 3]),
            ]],
        ]];
        $request = new AssessmentRequest(
            [], [], [], [], [], ['REQUEST_METHOD' => 'PUT', 'CONTENT_TYPE' => 'application/json'], json_encode($payload),
        );
        $request->setValidator(\Illuminate\Support\Facades\Validator::make($payload, [
            'user_id' => 'required|integer', 'selected_plan_type' => 'required|in:single,express,multiple',
            'treatment_plans' => 'required|array',
        ]));
        $assessment = Mockery::mock(Assessment::class)->makePartial();
        $assessment->shouldReceive('getForeignKey')->andReturn('assessment_id');
        $assessment->forceFill(['id' => 87, 'user_id' => 21, 'clinic_id' => 1, 'created_by' => 1]);
        $assessment->shouldReceive('update')->once()->andReturnUsing(function ($attributes) use ($assessment) {
            $assessment->forceFill($attributes);
            return true;
        });
        $controller = Mockery::mock(AssessmentController::class, [new Assessment(), $request])->makePartial();
        $controller->shouldReceive('success')->once()->andReturn(response()->json(['saved' => true]));
        TreatmentSession::withoutEvents(fn () => $controller->update($request, $assessment));
        $saved = DB::table('treatment_sessions')->orderBy('session_number')->get();
        expect($saved)->toHaveCount(2)
            ->and($saved[0]->plan_type)->toBe(AssessmentSessionType::Multiple->value)
            ->and(json_decode($saved[0]->steps, true)[0]['settings_note'])->toBeNull()
            ->and(json_decode($saved[0]->concerns_addressed, true)[0]['target_value'])->toBeNull()
            ->and(DB::table('appointments')->count())->toBe(1)
            ->and(Storage::disk('files')->exists('treatment-plans/treatment_plans_#87.json'))->toBeTrue();
    } finally {
        DB::setDefaultConnection($previousConnection);
        DB::purge('treatment_save_test');
    }
});
