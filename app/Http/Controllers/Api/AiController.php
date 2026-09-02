<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ResponseAPI;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

use App\Traits\ResponseAPI;
use App\Models\Assessment;
use App\Models\TreatmentSession;

class AiController extends Controller
{
    use ResponseAPI;

    private const BACKEND_BUILD = 'ai-controller-stateless-v1-2026-07-24';

    private const OPENAI_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Create an OpenAI Conversation for legacy workflows that explicitly need
     * persistent multi-turn state.
     */
    public function conversations(Request $request)
    {
        $apiKey = $this->openAiApiKey();

        if ($apiKey === null) {
            return $this->jsonError(
                message: 'OPENAI_API_KEY not set',
                status: 500,
            );
        }

        $validated = $request->validate([
            'patient_id' => ['nullable'],
            'patient_name' => ['nullable', 'string', 'max:512'],
            'assessment_id' => ['nullable'],
        ]);

        $payload = [
            'metadata' => array_filter([
                'patient_id' => isset($validated['patient_id'])
                    ? (string) $validated['patient_id']
                    : null,
                'patient_name' => isset($validated['patient_name'])
                    ? (string) $validated['patient_name']
                    : null,
                'assessment_id' => isset($validated['assessment_id'])
                    ? (string) $validated['assessment_id']
                    : null,
            ], static fn ($value) => $value !== null && $value !== ''),
        ];

        $clientRequestId = $this->clientRequestId($request);

        try {
            $response = $this->openAiClient(
                apiKey: $apiKey,
                clientRequestId: $clientRequestId,
                timeoutSeconds: 180,
            )->post(self::OPENAI_BASE_URL . '/conversations', $payload);
        } catch (ConnectionException $exception) {
            Log::error('OpenAI conversation connection error', [
                'backend_build' => self::BACKEND_BUILD,
                'client_request_id' => $clientRequestId,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonError(
                message: 'Could not connect to OpenAI.',
                status: 502,
                clientRequestId: $clientRequestId,
            );
        } catch (Throwable $exception) {
            Log::error('OpenAI conversation unexpected error', [
                'backend_build' => self::BACKEND_BUILD,
                'client_request_id' => $clientRequestId,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonError(
                message: 'Unexpected error while creating an OpenAI conversation.',
                status: 500,
                clientRequestId: $clientRequestId,
            );
        }

        $openAiRequestId = $response->header('x-request-id');

        if ($response->successful()) {
            Log::info('OpenAI conversation API success', [
                'backend_build' => self::BACKEND_BUILD,
                'client_request_id' => $clientRequestId,
                'openai_request_id' => $openAiRequestId,
                'status' => $response->status(),
            ]);

            return response()
                ->json($response->json(), $response->status())
                ->withHeaders($this->responseHeaders(
                    clientRequestId: $clientRequestId,
                    openAiRequestId: $openAiRequestId,
                ));
        }

        Log::error('OpenAI conversation API error', [
            'backend_build' => self::BACKEND_BUILD,
            'client_request_id' => $clientRequestId,
            'openai_request_id' => $openAiRequestId,
            'status' => $response->status(),
            'openai_error_type' => data_get($response->json(), 'error.type'),
            'openai_error_code' => data_get($response->json(), 'error.code'),
            'openai_error_message' => data_get($response->json(), 'error.message'),
        ]);

        return $this->upstreamErrorResponse(
            responseJson: $response->json(),
            status: $response->status(),
            clientRequestId: $clientRequestId,
            openAiRequestId: $openAiRequestId,
        );
    }

    /**
     * Forward a Responses API request to OpenAI.
     *
     * Pigmentation pipeline requests are always forced to be stateless. This
     * controller never creates or injects a Conversation on their behalf.
     */
    public function responses(Request $request)
    {
        $apiKey = $this->openAiApiKey();

        if ($apiKey === null) {
            return $this->jsonError(
                message: 'OPENAI_API_KEY not set',
                status: 500,
            );
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
        // Ensure PHP itself does not kill the process before OpenAI responds.
        // Treatment-plan generation with reasoning can legitimately take 3-5 minutes.
        if (function_exists('set_time_limit')) {
            set_time_limit(420);
        }

        try {
            return Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->connectTimeout(15)
            ->timeout(360)
            ->post('https://api.openai.com/v1/responses', $payload);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return $e->response;
        }
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
                'patient_id'    => (string) $assessment->user_id,
                'patient_name'  => (string) ($assessment->user->name ?? 'N/A'),
                'assessment_id' => (string) $assessment->id,
                'recovered_from_conversation' => (string) $assessment->conversation_id
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
