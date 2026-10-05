<?php

use App\Http\Controllers\Api\AiController;
use App\Models\Assessment;
use App\Models\TreatmentSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('disables the upstream treatment timeout when omitted or explicitly zero', function () {
    config(['project.openai_api_key' => 'test-key']);
    Http::fake(function ($request, $options) {
        expect($options['timeout'])->toBe(0);
        return Http::response(['status' => 'completed', 'output' => []]);
    });
    foreach ([[], ['timeout_ms' => 0]] as $controls) {
        $request = Request::create('/ai/responses', 'POST', array_merge([
            'model' => 'gpt-5.4',
            'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'fixture']]]],
            'metadata' => ['stage' => 'facial_treatment_v5'],
        ], $controls));
        expect((new AiController())->responses($request)->getStatusCode())->toBe(200);
    }
    Http::assertSentCount(2);
});

it('forwards v5 instructions and stateless structured output without timeout control fields', function () {
    config(['project.openai_api_key' => 'test-key']);
    Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => []])]);
    $request = Request::create('/ai/responses', 'POST', [
        'model' => 'gpt-5.4',
        'instructions' => 'Static v5 instructions',
        'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'fixture']]]],
        'metadata' => ['stage' => 'facial_treatment_v5'],
        'conversation' => 'old-conversation',
        'previous_response_id' => 'old-response',
        'timeout_ms' => 65000,
        'service_tier' => 'auto',
        'max_output_tokens' => 14000,
    ]);
    $response = (new AiController())->responses($request);
    expect($response->getStatusCode())->toBe(200);
    Http::assertSent(fn ($upstream) => $upstream['instructions'] === 'Static v5 instructions'
        && $upstream['service_tier'] === 'auto'
        && $upstream['max_output_tokens'] === 14000
        && !isset($upstream['timeout_ms'])
        && !isset($upstream['conversation'])
        && !isset($upstream['previous_response_id']));
    Http::assertSentCount(1);
});

it('restores v5 course and session metadata while preserving real session records', function () {
    Storage::fake('files');
    Storage::disk('files')->put('treatment-plans/treatment_plans_#741.json', json_encode([
        'treatment_plan' => [
            'course_outline' => [['session_number' => 3, 'reassessment_required' => true]],
            'treatments' => [[
                'session_number' => 1, 'why_today' => 'Recorded pigment finding',
                'expectation_card' => ['tonight' => 'Fixture expectation'],
                'steps' => [['step_number' => 99]],
            ]],
        ],
    ]));
    $session = new TreatmentSession();
    $session->forceFill(['id' => 123, 'session_number' => 1, 'steps' => [['step_number' => 1]]]);
    $relation = Mockery::mock();
    $relation->shouldReceive('orderBy')->with('session_number')->andReturnSelf();
    $relation->shouldReceive('get')->andReturn(collect([$session]));
    $assessment = Mockery::mock(Assessment::class)->makePartial();
    $assessment->forceFill(['id' => 741, 'total_time' => 'Two detailed sessions']);
    $assessment->shouldReceive('treatmentSessions')->andReturn($relation);
    $result = $assessment->getTreatmentSessionsAttribute();
    expect($result['course_outline'][0]['reassessment_required'])->toBeTrue()
        ->and($result['treatments'][0]['why_today'])->toBe('Recorded pigment finding')
        ->and($result['treatments'][0]['id'])->toBe(123)
        ->and($result['treatments'][0]['steps'][0]['step_number'])->toBe(1);
});
