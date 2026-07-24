<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Traits\ResponseAPI;

class AiController extends Controller
{
    use ResponseAPI;

    //INFO: Generate the conversations using open ai
    public function conversations(Request $request)
    {
        $apiKey = config('project.openai_api_key');
        if (!$apiKey) {
            return $this->error('Error', ['OPENAI_API_KEY not set'], 500);
        }

        $payload = [
            'metadata' => [
                'patient_id'    => $request->patient_id,
                'patient_name'  => $request->patient_name,
                'assessment_id' => $request->assessment_id,
            ],
        ];

        $response =  Http::withOptions([
        'verify' => false,   // 🔥 THIS MUST BE HERE
    ])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(180)
        ->post('https://api.openai.com/v1/conversations', $payload);

        if ($response->successful()) {
            return response()->json($response->json(), 200);
        }

            Log::error('OpenAI conversation error', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return response()->json([
            'error' => 'OpenAI conversation failed'
            ], $response->status());
    }

    //INFO: Responses API
    public function responses(Request $request)
    {
        $apiKey = config('project.openai_api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'OPENAI_API_KEY not set'
            ], 500);
        }

        $keys = [
            'model',
            'input',
            'max_output_tokens',
            'reasoning',
            'text',
            'metadata',
            'prompt_cache_key',
            'prompt_cache_retention',
        ];

        $payload = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $payload[$key] = $request->input($key);
            }
        }

        if (!empty($request->input('conversation'))) {
            $payload['conversation'] = $request->input('conversation');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];

        if ($request->hasHeader('X-Client-Request-Id')) {
            $headers['X-Client-Request-Id'] = $request->header('X-Client-Request-Id');
        }

        $response =  Http::withOptions([
            'verify' => false,   // 🔥 THIS MUST BE HERE
        ])->withHeaders($headers)
        ->connectTimeout(10)
        ->timeout(600)
        ->post('https://api.openai.com/v1/responses', $payload);

        $openaiRequestId = $response->header('x-request-id');

        if ($response->successful()) {
            Log::info('OpenAI responses API success', [
                'x-request-id' => $openaiRequestId,
            ]);
            // IMPORTANT: return AS-IS
            return response()->json($response->json(), 200);
        }

        Log::error('OpenAI responses API error', [
            'x-request-id' => $openaiRequestId,
            'status'       => $response->status(),
            'body'         => $response->body(),
        ]);

        return response()->json([
            'error' => 'OpenAI responses failed'
        ], $response->status());
    }
}
