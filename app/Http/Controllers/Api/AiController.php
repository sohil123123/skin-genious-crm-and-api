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

        $validated = $request->validate([
            'model' => ['required', 'string', 'max:255'],
            'input' => ['required', 'array', 'min:1'],
            'max_output_tokens' => ['sometimes', 'integer', 'min:1'],
            'reasoning' => ['sometimes', 'array'],
            'text' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
            'prompt_cache_key' => ['sometimes', 'string', 'max:255'],
            'prompt_cache_retention' => ['sometimes', 'string', 'max:32'],
            'conversation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'previous_response_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $clientRequestId = $this->clientRequestId($request);

        $payload = $this->buildResponsesPayload($validated);

        $pipelineVersion = (string) data_get(
            $payload,
            'metadata.pipeline_version',
            '',
        );

        $stage = (string) data_get($payload, 'metadata.stage', 'unknown');
        $attempt = data_get($payload, 'metadata.attempt');

        $isPigmentationPipeline = $this->isPigmentationPipeline(
            pipelineVersion: $pipelineVersion,
            stage: $stage,
        );

        if ($isPigmentationPipeline) {
            if (
                array_key_exists('conversation', $payload)
                || array_key_exists('previous_response_id', $payload)
            ) {
                Log::warning(
                    'Stateful identifiers removed from pigmentation request',
                    [
                        'backend_build' => self::BACKEND_BUILD,
                        'client_request_id' => $clientRequestId,
                        'pipeline_version' => $pipelineVersion,
                        'stage' => $stage,
                        'attempt' => $attempt,
                        'had_conversation' => array_key_exists(
                            'conversation',
                            $payload,
                        ),
                        'had_previous_response_id' => array_key_exists(
                            'previous_response_id',
                            $payload,
                        ),
                    ],
                );
            }

            unset($payload['conversation'], $payload['previous_response_id']);
        }

        // Log only safe request metadata. Never log prompts, images, patient data,
        // API keys, or the full request body.
        Log::info('FINAL OpenAI Responses request', [
            'backend_build' => self::BACKEND_BUILD,
            'client_request_id' => $clientRequestId,
            'pipeline_version' => $pipelineVersion ?: null,
            'stage' => $stage,
            'attempt' => $attempt,
            'model' => $payload['model'] ?? null,
            'payload_keys' => array_keys($payload),
            'has_conversation' => array_key_exists('conversation', $payload),
            'conversation_value' => $payload['conversation'] ?? null,
            'has_previous_response_id' => array_key_exists(
                'previous_response_id',
                $payload,
            ),
            'max_output_tokens' => $payload['max_output_tokens'] ?? null,
            'reasoning_effort' => data_get($payload, 'reasoning.effort'),
            'input_item_count' => is_array($payload['input'] ?? null)
                ? count($payload['input'])
                : null,
        ]);

        try {
            $response = $this->openAiClient(
                apiKey: $apiKey,
                clientRequestId: $clientRequestId,
                timeoutSeconds: 600,
            )->post(self::OPENAI_BASE_URL . '/responses', $payload);
        } catch (ConnectionException $exception) {
            Log::error('OpenAI Responses API connection error', [
                'backend_build' => self::BACKEND_BUILD,
                'client_request_id' => $clientRequestId,
                'pipeline_version' => $pipelineVersion ?: null,
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonError(
                message: 'Could not connect to OpenAI.',
                status: 502,
                clientRequestId: $clientRequestId,
            );
        } catch (Throwable $exception) {
            Log::error('OpenAI Responses API unexpected error', [
                'backend_build' => self::BACKEND_BUILD,
                'client_request_id' => $clientRequestId,
                'pipeline_version' => $pipelineVersion ?: null,
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonError(
                message: 'Unexpected error while calling OpenAI.',
                status: 500,
                clientRequestId: $clientRequestId,
            );
        }

        $openAiRequestId = $response->header('x-request-id');
        $responseJson = $response->json();

        if ($response->successful()) {
            Log::info('OpenAI Responses API success', [
                'backend_build' => self::BACKEND_BUILD,
                'client_request_id' => $clientRequestId,
                'openai_request_id' => $openAiRequestId,
                'pipeline_version' => $pipelineVersion ?: null,
                'stage' => $stage,
                'status' => $response->status(),
                'response_id' => data_get($responseJson, 'id'),
                'response_status' => data_get($responseJson, 'status'),
                'input_tokens' => data_get($responseJson, 'usage.input_tokens'),
                'output_tokens' => data_get($responseJson, 'usage.output_tokens'),
                'reasoning_tokens' => data_get(
                    $responseJson,
                    'usage.output_tokens_details.reasoning_tokens',
                ),
            ]);

            return response()
                ->json($responseJson, $response->status())
                ->withHeaders($this->responseHeaders(
                    clientRequestId: $clientRequestId,
                    openAiRequestId: $openAiRequestId,
                ));
        }

        $openAiMessage = (string) data_get(
            $responseJson,
            'error.message',
            'OpenAI Responses API failed.',
        );

        Log::error('OpenAI Responses API error', [
            'backend_build' => self::BACKEND_BUILD,
            'client_request_id' => $clientRequestId,
            'openai_request_id' => $openAiRequestId,
            'pipeline_version' => $pipelineVersion ?: null,
            'stage' => $stage,
            'status' => $response->status(),
            'has_conversation_in_final_payload' => array_key_exists(
                'conversation',
                $payload,
            ),
            'has_previous_response_id_in_final_payload' => array_key_exists(
                'previous_response_id',
                $payload,
            ),
            'openai_error_type' => data_get($responseJson, 'error.type'),
            'openai_error_code' => data_get($responseJson, 'error.code'),
            'openai_error_message' => $openAiMessage,
            'conversation_lock_error' => Str::contains(
                Str::lower($openAiMessage),
                'another process is currently operating on this conversation',
            ),
        ]);

        return $this->upstreamErrorResponse(
            responseJson: $responseJson,
            status: $response->status(),
            clientRequestId: $clientRequestId,
            openAiRequestId: $openAiRequestId,
            diagnostics: [
                'pipeline_version' => $pipelineVersion ?: null,
                'stage' => $stage,
                'final_payload_had_conversation' => array_key_exists(
                    'conversation',
                    $payload,
                ),
                'final_payload_had_previous_response_id' => array_key_exists(
                    'previous_response_id',
                    $payload,
                ),
                'backend_build' => self::BACKEND_BUILD,
            ],
        );
    }

    /**
     * Build the final OpenAI request using an explicit allow-list.
     */
    private function buildResponsesPayload(array $validated): array
    {
        $allowedKeys = [
            'model',
            'input',
            'max_output_tokens',
            'reasoning',
            'text',
            'metadata',
            'prompt_cache_key',
            'prompt_cache_retention',
            'conversation',
            'previous_response_id',
        ];

        $payload = [];

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];

            if ($value === null || $value === '') {
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    private function isPigmentationPipeline(
        string $pipelineVersion,
        string $stage,
    ): bool {
        return Str::startsWith($pipelineVersion, 'pigmentation_')
            || Str::contains(Str::lower($stage), 'pigmentation')
            || in_array($stage, [
                'baseline_morphology_census',
                'phenotype_measurement',
                'dynamic_questions',
                'diagnosis',
                'treatment_plan',
                'formal_reassessment',
                'reassessment_questions',
                'backend_stateless_test',
            ], true);
    }

    private function openAiApiKey(): ?string
    {
        $apiKey = config('project.openai_api_key');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        return trim($apiKey);
    }

    private function openAiClient(
        string $apiKey,
        string $clientRequestId,
        int $timeoutSeconds,
    ): PendingRequest {
        $verifySsl = (bool) config('project.openai_verify_ssl', true);

        return Http::withOptions([
            'verify' => $verifySsl,
        ])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'X-Client-Request-Id' => $clientRequestId,
            ])
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout($timeoutSeconds);
    }

    private function clientRequestId(Request $request): string
    {
        $supplied = trim((string) $request->header('X-Client-Request-Id'));

        if ($supplied !== '') {
            return Str::limit($supplied, 255, '');
        }

        return (string) Str::uuid();
    }

    private function responseHeaders(
        string $clientRequestId,
        ?string $openAiRequestId = null,
    ): array {
        return array_filter([
            'X-AI-Controller-Build' => self::BACKEND_BUILD,
            'X-Client-Request-Id' => $clientRequestId,
            'X-OpenAI-Request-Id' => $openAiRequestId,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function jsonError(
        string $message,
        int $status,
        ?string $clientRequestId = null,
    ) {
        $clientRequestId ??= (string) Str::uuid();

        return response()
            ->json([
                'error' => [
                    'message' => $message,
                    'backend_build' => self::BACKEND_BUILD,
                    'client_request_id' => $clientRequestId,
                ],
            ], $status)
            ->withHeaders($this->responseHeaders($clientRequestId));
    }

    private function upstreamErrorResponse(
        array $responseJson,
        int $status,
        string $clientRequestId,
        ?string $openAiRequestId = null,
        array $diagnostics = [],
    ) {
        return response()
            ->json([
                'error' => [
                    'message' => data_get(
                        $responseJson,
                        'error.message',
                        'OpenAI request failed.',
                    ),
                    'type' => data_get($responseJson, 'error.type'),
                    'code' => data_get($responseJson, 'error.code'),
                    'param' => data_get($responseJson, 'error.param'),
                    'openai_request_id' => $openAiRequestId,
                    'client_request_id' => $clientRequestId,
                    'backend_build' => self::BACKEND_BUILD,
                    'diagnostics' => $diagnostics,
                ],
            ], $status)
            ->withHeaders($this->responseHeaders(
                clientRequestId: $clientRequestId,
                openAiRequestId: $openAiRequestId,
            ));
    }
}
