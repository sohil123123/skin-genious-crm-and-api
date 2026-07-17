<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Traits\ResponseAPI;
use App\Models\Assessment;
use App\Models\TreatmentSession;

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
        $conversationId = $request->input('conversation');

        // 1. Initial request attempt
        $response = $this->executeOpenAiCall($apiKey, $payload);

        $newConversationId = null;

        // 2. Check if context window limit was exceeded
        if ($this->isContextLimitError($response) && !empty($conversationId)) {
            // Find the assessment using the conversation_id
            $assessment = Assessment::where('conversation_id', $conversationId)->first();

            if ($assessment) {
                Log::warning("OpenAI Context Limit Exceeded for Conversation ID: {$conversationId}. Initiating Reactive Auto-Recovery for Assessment ID: {$assessment->id}...");

                // Retrieve or dynamically generate the summary
                $summaryText = $assessment->ai_summary;
                if (empty($summaryText)) {
                    $summaryText = $assessment->updateAiSummary();
                    $assessment->save();
                }

                // Create a new clean conversation thread on OpenAI
                $newConversationId = $this->createNewThread($apiKey, $assessment);

                if ($newConversationId) {
                    // Seed the new thread with the clinical summary context
                    $this->seedThreadWithSummary($apiKey, $newConversationId, $summaryText);

                    // Update the assessment with the new conversation ID and backup summary
                    $assessment->update([
                        'conversation_id' => $newConversationId,
                        'ai_summary' => $summaryText
                    ]);

                    // Swap conversation ID in payload and retry the original call
                    $payload['conversation'] = $newConversationId;
                    Log::info("Retrying OpenAI request on new Thread: {$newConversationId}...");
                    $response = $this->executeOpenAiCall($apiKey, $payload);
                }
            } else {
                Log::error("Context limit exceeded but no assessment found with conversation ID: {$conversationId}");
            }
        }

        if ($response->successful()) {
            $jsonRes = $response->json();
            $resObj = response()->json($jsonRes, 200);
            if ($newConversationId) {
                $resObj->header('X-New-Conversation-Id', $newConversationId);
            }
            return $resObj;
        }

        Log::error('OpenAI responses API error', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return response()->json([
            'error' => 'OpenAI responses failed'
        ], $response->status());
    }

    /**
     * Executes the API call to OpenAI responses endpoint.
     */
    private function executeOpenAiCall($apiKey, $payload)
    {
        return Http::withOptions([
            'verify' => false,
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->connectTimeout(10)
        ->timeout(180)
        ->retry(2, 1000)
        ->post('https://api.openai.com/v1/responses', $payload);
    }

    /**
     * Checks if the response indicates a context window limit issue.
     */
    private function isContextLimitError($response)
    {
        if ($response->status() === 400) {
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? $body['message'] ?? '';
            return str_contains(strtolower($errorMsg), 'context') || str_contains(strtolower($errorMsg), 'limit');
        }
        return false;
    }

    /**
     * Requests a new conversation/thread from OpenAI.
     */
    private function createNewThread($apiKey, Assessment $assessment)
    {
        $payload = [
            'metadata' => [
                'patient_id'    => $assessment->user_id,
                'patient_name'  => $assessment->user->name ?? 'N/A',
                'assessment_id' => $assessment->id,
                'recovered_from_conversation' => $assessment->conversation_id
            ],
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(180)
        ->post('https://api.openai.com/v1/conversations', $payload);

        if ($response->successful()) {
            return $response->json()['id'] ?? null;
        }

        Log::error("Failed to create new recovery thread", [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return null;
    }

    /**
     * Seeds the conversation thread with the latest summary.
     */
    private function seedThreadWithSummary($apiKey, $conversationId, $summaryText)
    {
        $response = Http::withOptions([
            'verify' => false,
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(180)
        ->post("https://api.openai.com/v1/conversations/{$conversationId}/items", [
            'role' => 'system',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => $summaryText
                ]
            ]
        ]);

        if (!$response->successful()) {
            Log::error("Failed to seed recovery thread {$conversationId} with summary", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }
    }
}
