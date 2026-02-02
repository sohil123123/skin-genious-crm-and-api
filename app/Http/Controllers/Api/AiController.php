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

        $payload = $request->all(); // pass-through from frontend

        $response =  Http::withOptions([
            'verify' => false,   // 🔥 THIS MUST BE HERE
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->connectTimeout(10)
        ->timeout(180)
        ->retry(2, 1000)
        ->post('https://api.openai.com/v1/responses', $payload);

        if ($response->successful()) {
            // IMPORTANT: return AS-IS
            return response()->json($response->json(), 200);
        }

        Log::error('OpenAI responses API error', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return response()->json([
            'error' => 'OpenAI responses failed'
        ], $response->status());
    }
}
