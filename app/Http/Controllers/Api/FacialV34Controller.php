<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\TreatmentSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class FacialV34Controller extends Controller
{
    private const ENGINE_VERSION = 'facial_v3_6_calibrated';

    private const CANONICAL_MODES = [
        'red',
        'subsurface_polarized',
        'surface_polarized',
        'white',
        'woods_uv',
    ];

    public function selfTest()
    {
        try {
            $result = $this->runNode('self_test', []);
            return response()->json([
                'success' => true,
                'message' => 'Facial V3.6-calibrated runner is available.',
                'results' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.6-calibrated self-test failed.');
        }
    }

    public function assessment(Request $request, Assessment $assessment)
    {
        $request->validate([
            'model' => 'nullable|string|max:255',
            'stated_concerns' => 'nullable|array',
        ]);

        try {
            $imagesByMode = $this->mediaToImagesByMode(
                $assessment->getMedia('assessment_images')
            );

            $payload = [
                'assessment_id' => $assessment->id,
                'scan_id' => 'assessment-' . $assessment->id . '-baseline',
                'images_by_mode' => $imagesByMode,
                'image_set_hash' => $this->imageSetHash($imagesByMode),
                'model' => $this->model($request),
                'client' => [
                    'client_id' => $assessment->user_id,
                    'display_name' => data_get($assessment, 'user.name')
                        ?? trim((string) data_get($assessment, 'name', ''))
                        ?: null,
                ],
                'clinic' => [
                    'name' => 'AI Aesthetics',
                    'medical_lead' => 'Dr. Aakriti Mehra',
                    'location' => data_get($assessment, 'clinic.name'),
                ],
                'stated_concerns' => $request->input('stated_concerns', []),
            ];

            $result = $this->runNode('assessment', $payload);
            $this->putJson($this->assessmentPath($assessment->id), $result);

            return response()->json([
                'success' => true,
                'message' => 'Facial V3.6-calibrated assessment completed.',
                'results' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.6-calibrated assessment failed.');
        }
    }

    public function treatmentPlan(Request $request, Assessment $assessment)
    {
        $validated = $request->validate([
            'treatment_mode' => 'required|string|in:single,express,full,multiple',
            'selected_concerns' => 'nullable|array',
            'patient_history' => 'nullable|array',
            'regional_temperatures_c' => 'nullable|array',
            'zone_context' => 'nullable|array',
            'capabilities' => 'nullable|array',
            'maximum_sessions' => 'nullable|integer|min:2|max:8',
            'doctor_override_sessions' => 'nullable|integer|min:2|max:8',
            'model' => 'nullable|string|max:255',
        ]);

        try {
            $baseline = $this->getJson($this->assessmentPath($assessment->id));
            $skinState = data_get($baseline, 'skin_state');
            if (!is_array($skinState)) {
                throw new \RuntimeException(
                    'No stored V3.4 baseline Skin State exists. Run the V3.4 assessment first.'
                );
            }

            $payload = [
                'assessment_id' => $assessment->id,
                'skin_state' => $skinState,
                'treatment_mode' => $validated['treatment_mode'],
                'selected_concerns' => $validated['selected_concerns'] ?? [],
                'patient_history' => $validated['patient_history'] ?? [],
                'regional_temperatures_c' => $validated['regional_temperatures_c'] ?? [],
                'zone_context' => $validated['zone_context'] ?? [],
                'capabilities' => $validated['capabilities'] ?? [],
                'maximum_sessions' => $validated['maximum_sessions'] ?? 8,
                'doctor_override_sessions' => $validated['doctor_override_sessions'] ?? null,
                'model' => $this->model($request),
                'client' => [
                    'client_id' => $assessment->user_id,
                    'display_name' => data_get($assessment, 'user.name')
                        ?? trim((string) data_get($assessment, 'name', ''))
                        ?: null,
                ],
                'clinic' => [
                    'name' => 'AI Aesthetics',
                    'medical_lead' => 'Dr. Aakriti Mehra',
                    'location' => data_get($assessment, 'clinic.name'),
                ],
            ];

            $result = $this->runNode('treatment_plan', $payload);
            $this->putJson($this->treatmentPlanPath($assessment->id), $result);

            return response()->json([
                'success' => true,
                'message' => 'Facial V3.6-calibrated treatment plan generated.',
                'results' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.6-calibrated treatment-plan generation failed.');
        }
    }

    public function reassessment(Request $request, Assessment $assessment)
    {
        $validated = $request->validate([
            'treatment_session_id' => 'nullable|integer',
            'model' => 'nullable|string|max:255',
        ]);

        try {
            $baseline = $this->getJson($this->assessmentPath($assessment->id));
            if (!is_array(data_get($baseline, 'skin_state'))) {
                throw new \RuntimeException(
                    'No stored V3.4 baseline assessment exists for reassessment.'
                );
            }

            $treatmentSession = null;
            if (!empty($validated['treatment_session_id'])) {
                $treatmentSession = TreatmentSession::query()
                    ->where('assessment_id', $assessment->id)
                    ->whereKey($validated['treatment_session_id'])
                    ->firstOrFail();
                $postMedia = $treatmentSession->getMedia('post_treatment_images');
            } else {
                $postMedia = $assessment->getMedia('post_assessment_images');
            }

            $postImagesByMode = $this->mediaToImagesByMode($postMedia);
            $postScanSuffix = $treatmentSession
                ? 'session-' . $treatmentSession->id
                : 'assessment-post';

            $payload = [
                'assessment_id' => $assessment->id,
                'baseline_run' => [
                    'skin_state' => data_get($baseline, 'skin_state'),
                    'evidence_packet' => data_get($baseline, 'feature_packet'),
                    'imagesByMode' => $this->mediaToImagesByMode($assessment->getMedia('assessment_images')),
                ],
                'post_scan_id' => 'assessment-' . $assessment->id . '-' . $postScanSuffix,
                'post_images_by_mode' => $postImagesByMode,
                'post_image_set_hash' => $this->imageSetHash($postImagesByMode),
                'model' => $this->model($request),
            ];

            $result = $this->runNode('reassessment', $payload);
            $this->putJson(
                $this->reassessmentPath($assessment->id, $treatmentSession?->id),
                $result
            );

            return response()->json([
                'success' => true,
                'message' => 'Facial V3.6-calibrated reassessment completed.',
                'results' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.6-calibrated reassessment failed.');
        }
    }

    private function runNode(string $command, array $payload): array
    {
        $runner = base_path('facial-engine/v3_4/facialV34Runner.mjs');
        if (!is_file($runner)) {
            throw new \RuntimeException('V3.4 Node runner was not found at ' . $runner);
        }

        $nodeBinary = (string) config('project.node_binary', env('NODE_BINARY', 'node'));
        $apiKey = trim((string) config('project.openai_api_key'));
        if ($command !== 'self_test' && $apiKey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $process = new Process(
            [$nodeBinary, $runner, $command],
            base_path(),
            array_filter([
                'OPENAI_API_KEY' => $apiKey ?: null,
                'FACIAL_V34_MODEL' => (string) ($payload['model'] ?? 'gpt-5.2'),
                'FACIAL_V34_DEBUG' => app()->environment('local', 'staging') ? '1' : '0',
            ], static fn ($value) => $value !== null)
        );
        $process->setTimeout(900);
        $process->setInput(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $process->run();

        $raw = trim($process->getOutput());
        $decoded = json_decode($raw, true);

        if (!$process->isSuccessful() || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            $message = data_get($decoded, 'error.message')
                ?: trim($process->getErrorOutput())
                ?: 'Unknown V3.4 runner failure.';
            Log::error('Facial V3.6-calibrated runner failed', [
                'command' => $command,
                'assessment_id' => $payload['assessment_id'] ?? null,
                'exit_code' => $process->getExitCode(),
                'message' => $message,
            ]);
            throw new \RuntimeException($message);
        }

        return $decoded['result'];
    }

    private function mediaToImagesByMode($mediaCollection): array
    {
        $items = collect($mediaCollection)->values();
        if ($items->count() < count(self::CANONICAL_MODES)) {
            throw new \RuntimeException(
                'Five images are required. Found ' . $items->count() . '.'
            );
        }

        $byMode = [];
        foreach ($items as $media) {
            $mode = $this->normalizeMode($media->getCustomProperty('mode'));
            $fileId = trim((string) $media->getCustomProperty('openai_file_id'));
            if ($mode && $fileId !== '') {
                $byMode[$mode] = ['file_id' => $fileId];
            }
        }

        // Backward-compatible fallback for scans uploaded before mode metadata
        // was stored. Satish confirmed the A5 order below is authoritative.
        if (count($byMode) !== count(self::CANONICAL_MODES)) {
            $byMode = [];
            foreach (self::CANONICAL_MODES as $index => $mode) {
                $media = $items->get($index);
                $fileId = $media
                    ? trim((string) $media->getCustomProperty('openai_file_id'))
                    : '';
                if ($fileId === '') {
                    throw new \RuntimeException(
                        "Missing OpenAI file_id for {$mode} image at position {$index}."
                    );
                }
                $byMode[$mode] = ['file_id' => $fileId];
            }
        }

        foreach (self::CANONICAL_MODES as $mode) {
            if (empty($byMode[$mode]['file_id'])) {
                throw new \RuntimeException("Missing canonical image mode: {$mode}");
            }
        }

        return $byMode;
    }

    private function normalizeMode($value): ?string
    {
        $mode = strtolower(trim((string) $value));
        $mode = str_replace([' ', '-'], '_', $mode);
        $aliases = [
            'uv' => 'woods_uv',
            'woods' => 'woods_uv',
            'wood_uv' => 'woods_uv',
            'subsurface' => 'subsurface_polarized',
            'surface' => 'surface_polarized',
        ];
        $mode = $aliases[$mode] ?? $mode;
        return in_array($mode, self::CANONICAL_MODES, true) ? $mode : null;
    }

    private function imageSetHash(array $imagesByMode): string
    {
        $parts = [];
        foreach (self::CANONICAL_MODES as $mode) {
            $parts[] = $mode . ':' . data_get($imagesByMode, $mode . '.file_id');
        }
        return hash('sha256', implode("\n", $parts));
    }

    private function model(Request $request): string
    {
        return (string) (
            $request->input('model')
            ?: config('project.openai_model')
            ?: env('FACIAL_V34_MODEL', 'gpt-5.2')
        );
    }

    private function disk()
    {
        return Storage::disk('files');
    }

    private function putJson(string $path, array $value): void
    {
        $this->disk()->put(
            $path,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function getJson(string $path): array
    {
        if (!$this->disk()->exists($path)) {
            throw new \RuntimeException("Required V3.4 staging state does not exist: {$path}");
        }
        $decoded = json_decode($this->disk()->get($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Stored V3.4 staging state is invalid: {$path}");
        }
        return $decoded;
    }

    private function assessmentPath(int $assessmentId): string
    {
        return "facial-v34/assessment_#{$assessmentId}.json";
    }

    private function treatmentPlanPath(int $assessmentId): string
    {
        return "facial-v34/treatment_plan_#{$assessmentId}.json";
    }

    private function reassessmentPath(int $assessmentId, ?int $sessionId): string
    {
        $suffix = $sessionId ? "session_#{$sessionId}" : 'assessment_post';
        return "facial-v34/reassessment_#{$assessmentId}_{$suffix}.json";
    }

    private function errorResponse(Throwable $e, string $fallback)
    {
        Log::error($fallback, [
            'engine_version' => self::ENGINE_VERSION,
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'message' => app()->environment('local', 'staging')
                    ? $e->getMessage()
                    : $fallback,
                'engine_version' => self::ENGINE_VERSION,
            ],
        ], 500);
    }
}
