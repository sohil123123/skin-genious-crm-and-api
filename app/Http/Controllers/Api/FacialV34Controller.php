<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\TreatmentSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class FacialV34Controller extends Controller
{
    private const ENGINE_VERSION = 'facial_v3_9_existing_workflow';

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
                'message' => 'Facial V3.9 existing-workflow runner is available.',
                'results' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.9 existing-workflow self-test failed.');
        }
    }

    public function assessment(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
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
            $savedPath = $this->assessmentPath($assessment->id);
            if ($this->disk()->exists($savedPath)) {
                $previous = $this->getJson($savedPath);
                if (data_get($previous, 'skin_state.scoring_execution.formula_config_version') !== data_get($result, 'skin_state.scoring_execution.formula_config_version')) {
                    $archive = 'facial-v34/archive/assessment-' . $assessment->id . '-' . hash('sha256', json_encode($previous)) . '.json';
                    if (!$this->disk()->exists($archive)) $this->putJson($archive, $previous);
                }
            }
            $this->putJson($savedPath, $result);

            return response()->json([
                'success' => true,
                'message' => 'Facial assessment and scoring completed.',
                'results' => $this->clientResult($assessment, $result),
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.9 existing-workflow assessment failed.');
        }
    }

    public function treatmentPlan(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        $validated = $request->validate([
            'treatment_mode' => 'required|string|in:single,express,full,multiple',
            'selected_concerns' => 'nullable|array',
            'patient_history' => 'nullable|array',
            'regional_temperatures_c' => 'nullable|array',
            'zone_context' => 'nullable|array',
            'capabilities' => 'nullable|array',
            'maximum_sessions' => 'nullable|integer|min:5|max:8',
            'doctor_override_sessions' => 'nullable|integer|min:5|max:8',
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
                'message' => 'Facial V3.9 existing-workflow treatment plan generated.',
                'results' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.9 existing-workflow treatment-plan generation failed.');
        }
    }

    public function reassessment(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
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

            if ($treatmentSession && $treatmentSession->status !== 'completed') {
                throw new \RuntimeException('Complete the session before its end-of-session reassessment.');
            }
            $reference = [
                'skin_state' => data_get($baseline, 'skin_state'),
                'evidence_packet' => data_get($baseline, 'feature_packet'),
                'imagesByMode' => $this->mediaToImagesByMode($assessment->getMedia('assessment_images')),
            ];
            $referenceSource = 'baseline';
            if ($treatmentSession && $treatmentSession->session_number > 1) {
                $previous = TreatmentSession::query()->where('assessment_id', $assessment->id)
                    ->where('session_number', $treatmentSession->session_number - 1)->firstOrFail();
                $previousRun = $this->getJson($this->reassessmentPath($assessment->id, $previous->id));
                if (!is_array(data_get($previousRun, 'post_run.evidence_packet'))) {
                    throw new \RuntimeException('The preceding session needs a compatible saved reassessment before comparison.');
                }
                $reference = data_get($previousRun, 'post_run');
                $reference['imagesByMode'] = $this->mediaToImagesByMode($previous->getMedia('post_treatment_images'));
                $referenceSource = 'session_' . $previous->session_number;
            }
            $postImagesByMode = $this->mediaToImagesByMode($postMedia);
            $postScanSuffix = $treatmentSession
                ? 'session-' . $treatmentSession->id
                : 'assessment-post';

            // V3.12: days between the reference scan and this post scan. The engine uses it
            // to decide whether structural parameters (jawline, firmness) may be rescored.
            $referenceMedia = ($treatmentSession && $treatmentSession->session_number > 1 && isset($previous))
                ? $previous->getMedia('post_treatment_images')
                : $assessment->getMedia('assessment_images');
            $intervalDays = $this->scanIntervalDays($referenceMedia, $postMedia);

            $payload = [
                'assessment_id' => $assessment->id,
                'baseline_run' => $reference,
                'post_scan_id' => 'assessment-' . $assessment->id . '-' . $postScanSuffix,
                'post_images_by_mode' => $postImagesByMode,
                'post_image_set_hash' => $this->imageSetHash($postImagesByMode),
                'model' => $this->model($request),
                'interval_days' => $intervalDays,
            ];

            $result = $this->runNode('reassessment', $payload);
            $result['post_diagnosis']['metadata']['reference_source'] = $referenceSource;
            $result['post_diagnosis']['metadata']['treatment_session'] = $treatmentSession?->session_number;
            $result['completed_session_id'] = $treatmentSession?->id;
            $this->putJson(
                $this->reassessmentPath($assessment->id, $treatmentSession?->id),
                $result
            );

            return response()->json([
                'success' => true,
                'message' => 'Facial reassessment completed.',
                'results' => $this->clientResult($assessment, $result),
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Facial V3.9 existing-workflow reassessment failed.');
        }
    }


    /** Same planning prompt as the existing workflow, supplied with server-held scores. */
    public function planningContext(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        $request->validate(['after_session_id' => 'nullable|integer']);
        try {
            $context = $this->courseContext($assessment, $request->input('after_session_id'));
            $run = isset($context['after_session_id'])
                ? $this->getJson($this->reassessmentPath($assessment->id, $context['after_session_id']))
                : $this->getJson($this->assessmentPath($assessment->id));
            $planning = [
                'course_context' => $context,
                'skin_state' => data_get($run, 'post_run.skin_state') ?? data_get($run, 'skin_state'),
                'feature_packet' => data_get($run, 'post_run.evidence_packet') ?? data_get($run, 'feature_packet'),
                'diagnosis' => data_get($run, 'post_diagnosis') ?? data_get($run, 'diagnosis'),
                'released_course' => $this->arrayValue($assessment->treatment_plans),
            ];
            return response()->json(['success' => true, 'results' => $this->runNode('project_planning_context', ['context' => $planning])]);
        } catch (Throwable $e) { return $this->errorResponse($e, 'Planning context unavailable.'); }
    }

    private function courseContext(Assessment $assessment, ?int $afterSessionId): array
    {
        if (!$afterSessionId) return ['next_session_number' => 1, 'previous_day_offset' => 0];
        $previous = TreatmentSession::query()->where('assessment_id', $assessment->id)->whereKey($afterSessionId)->firstOrFail();
        $number = (int) $previous->session_number;
        $plan = $this->arrayValue($assessment->treatment_plans);
        $total = data_get($plan, 'treatment_plan.estimated_sessions');
        if (data_get($plan, 'workflow_v39.mode') !== 'multiple' || $number < 2 || $number % 2 || $number >= $total) {
            throw new \RuntimeException('The next block is available only after an unfinished course reaches session 2, 4 or 6.');
        }
        // Check both sessions in the completed block; one completed row cannot release the next pair.
        $completed = TreatmentSession::query()->where('assessment_id', $assessment->id)
            ->whereIn('session_number', [$number - 1, $number])->where('status', 'completed')->count();
        if ($completed !== 2 || !$previous->post_diagnosis) {
            throw new \RuntimeException('Finish both released sessions and save the end-of-block reassessment first.');
        }
        $scan = $this->getJson($this->reassessmentPath($assessment->id, $previous->id));
        if (data_get($scan, 'completed_session_id') !== $previous->id || !data_get($scan, 'post_run.skin_state.scan.scan_id')) {
            throw new \RuntimeException('A completed-session scan is required to release the next block.');
        }
        $released = collect(data_get($plan, 'treatment_plan.treatments', []))->firstWhere('session_number', $number);
        if (!is_int(data_get($released, 'day_offset'))) throw new \RuntimeException('Previous session schedule is missing.');
        return [
            'after_session_id' => $previous->id, 'reassessed_after_session' => $number,
            'next_session_number' => $number + 1, 'estimated_sessions' => $total,
            'previous_day_offset' => $released['day_offset'],
            'reassessment_scan_id' => data_get($scan, 'post_run.skin_state.scan.scan_id'),
            'reassessment_fingerprint' => hash('sha256', json_encode(data_get($scan, 'post_run'))),
        ];
    }

    /** Append only. No DELETE, no replication of clinical execution, no updates to old session rows. */
    public function saveCourseBlock(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        $request->validate(['plan' => 'required|array', 'plan.workflow_v39.mode' => 'required|in:single,express,multiple',
            'plan.workflow_v39.selected_concerns' => 'present|array']);
        try {
            $results = DB::transaction(function () use ($request, $assessment) {
                $locked = Assessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
                $incoming = $request->input('plan');
                $existing = $this->arrayValue($locked->treatment_plans);
                $after = data_get($incoming, 'workflow_v39.course_context.after_session_id');
                $context = $this->courseContext($locked, $after);
                $mode = data_get($incoming, 'workflow_v39.mode');
                $selected = data_get($incoming, 'workflow_v39.selected_concerns');
                if ($after && $mode !== 'multiple') throw new \RuntimeException('Only packages can release another block.');
                if ($after && data_get($incoming, 'workflow_v39.course_context.reassessment_fingerprint') !== $context['reassessment_fingerprint']) {
                    throw new \RuntimeException('Reassessment changed while planning. Regenerate the pending block using the latest scan.');
                }
                $key = 'after_' . ($after ?: 'baseline');
                $hash = hash('sha256', json_encode($incoming));
                $receipt = data_get($existing, 'workflow_v39.release_receipts.' . $key);
                if ($receipt) {
                    if ($receipt !== $hash) throw new \RuntimeException('This block has already been saved; existing sessions cannot be overwritten.');
                    return $this->courseResponse($locked, $existing); // Network retry, same result and IDs.
                }
                if (!$after && (data_get($existing, 'treatment_plan.treatments') || TreatmentSession::where('assessment_id', $locked->id)->exists())) {
                    throw new \RuntimeException('A course already exists. Do not replace existing session records.');
                }
                $check = $this->runNode('validate_course_block', [
                    'plan' => $incoming, 'mode' => $mode, 'selected_concerns' => $selected,
                    'course_context' => $context, 'existing_plan' => $after ? $existing : null,
                ]);
                $merged = $check['merged_plan'];
                $merged['workflow_v39']['mode'] = $mode;
                $merged['workflow_v39']['selected_concerns'] = $selected;
                $merged['workflow_v39']['release_receipts'][$key] = $hash;
                $merged['treatment_plan']['total_time'] = array_sum(array_column($merged['treatment_plan']['treatments'], 'treatment_time')) . ' mins (released sessions)';
                foreach ($incoming['treatment_plan']['treatments'] as $session) $this->createCourseSession($locked, $session);
                $this->setJsonAttribute($locked, 'treatment_plans', $merged);
                $locked->save();
                return $this->courseResponse($locked, $merged);
            });
            return response()->json(['success' => true, 'results' => $results]);
        } catch (Throwable $e) { return $this->errorResponse($e, 'Treatment block could not be saved.'); }
    }

    private function createCourseSession(Assessment $assessment, array $payload): void
    {
        $session = new TreatmentSession();
        $columns = Schema::getColumnListing($session->getTable());
        // This supplied frontend exposes flat session fields. Fail atomically if the host
        // schema differs; do not silently drop steps or guess another JSON storage column.
        foreach (['assessment_id', 'session_number', 'steps', 'status'] as $required) {
            if (!in_array($required, $columns, true)) throw new \RuntimeException('TreatmentSession schema adapter required for column: ' . $required);
        }
        if (TreatmentSession::where('assessment_id', $assessment->id)->where('session_number', $payload['session_number'])->exists()) {
            throw new \RuntimeException('Session number already exists.');
        }
        $session->assessment_id = $assessment->id;
        if (in_array('user_id', $columns, true)) $session->user_id = $assessment->user_id;
        $session->status = 'pending';
        foreach (['session_number', 'title', 'week', 'day_offset', 'gap_days_from_previous', 'spacing_reason',
            'treatment_time', 'step_duration_total', 'timing_validation', 'preparations_checklist_for_therapist',
            'concerns_addressed', 'steps', 'script', 'spoken_script', 'description'] as $field) {
            if (in_array($field, $columns, true) && array_key_exists($field, $payload)) {
                if (is_array($payload[$field])) $this->setJsonAttribute($session, $field, $payload[$field]);
                else $session->setAttribute($field, $payload[$field]);
            }
        }
        $session->save();
    }

    private function courseResponse(Assessment $assessment, array $plan): array
    {
        $rows = TreatmentSession::where('assessment_id', $assessment->id)->orderBy('session_number')->get()->keyBy('session_number');
        $display = $plan['treatment_plan'];
        $display['treatments'] = array_map(static function ($definition) use ($rows) {
            $row = $rows->get($definition['session_number']);
            // Clinical execution fields belong to the DB; plan-only fields stay in the saved JSON.
            return $row ? array_merge($definition, $row->toArray()) : $definition;
        }, $display['treatments']);
        return ['treatment_plans' => $plan, 'treatment_sessions' => $display];
    }

    private function arrayValue($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) return json_decode($value, true) ?: [];
        return $value ? json_decode(json_encode($value), true) : [];
    }

    private function setJsonAttribute($model, string $key, array $value): void
    {
        $model->setAttribute($key, $model->hasCast($key) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    private function clientResult(Assessment $assessment, array $result): array
    {
        return $this->runNode('project_transport', ['assessment_id' => $assessment->id, 'result' => $result]);
    }

    // Recover a completed baseline after a client/DB save failure without paying for
    // another vision pass. The media fingerprint must still match the saved scan.
    public function assessmentResult(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        if (!$this->disk()->exists($this->assessmentPath($assessment->id))) {
            return response()->json(['success' => false, 'error' => ['message' => 'No saved baseline.']], 404);
        }
        try {
            $result = $this->getJson($this->assessmentPath($assessment->id));
            $expectedModel = $this->model($request);
            if ($expectedModel === 'gpt-5.2') $expectedModel = 'gpt-5.2-2025-12-11';
            if (data_get($result, 'skin_state.scoring_execution.formula_config_version') !== 'aia_regional_legacy_equations_v3.10.0'
                || data_get($result, 'feature_packet.model_execution.model_version') !== $expectedModel) {
                return response()->json(['success' => false, 'error' => ['message' => 'Saved baseline requires the current measurement version. Regenerate from the original images.']], 409);
            }
            $images = $this->mediaToImagesByMode($assessment->getMedia('assessment_images'));
            if (data_get($result, 'feature_packet.scan.image_set_hash') !== $this->imageSetHash($images)) {
                return response()->json(['success' => false, 'error' => ['message' => 'Images changed since the saved baseline.']], 409);
            }
            $result = $this->runNode('refresh_concern_preview', ['result' => $result]);
            return response()->json(['success' => true, 'results' => $this->clientResult($assessment, $result)]);
        } catch (Throwable $e) { return $this->errorResponse($e, 'Saved assessment could not be loaded.'); }
    }

    private function runNode(string $command, array $payload): array
    {
        $runner = base_path('facial-engine/v3_4/facialV34Runner.mjs');
        if (!is_file($runner)) {
            throw new \RuntimeException('V3.4 Node runner was not found at ' . $runner);
        }

        $nodeBinary = (string) config('project.node_binary', env('NODE_BINARY', 'node'));
        $apiKey = trim((string) config('project.openai_api_key'));
        if (!in_array($command, ['self_test', 'validate_course_block', 'project_transport', 'project_planning_context', 'refresh_concern_preview'], true) && $apiKey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $process = new Process(
            [$nodeBinary, $runner, $command],
            base_path(),
            array_filter([
                'OPENAI_API_KEY' => $apiKey ?: null,
                'FACIAL_V310_CACHE_DIR' => storage_path('app/facial-v310-measurements'),
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
            Log::error('Facial V3.9 existing-workflow runner failed', [
                'command' => $command,
                'assessment_id' => $payload['assessment_id'] ?? null,
                'exit_code' => $process->getExitCode(),
                'message' => $message,
            ]);
            throw new \RuntimeException($message);
        }

        return $decoded['result'];
    }

    /**
     * Days between the earliest reference capture and the earliest post capture,
     * rounded to one decimal. Returns null when either side has no timestamp.
     */
    private function scanIntervalDays($referenceMedia, $postMedia): ?float
    {
        $first = static function ($collection) {
            $timestamps = collect($collection)->map(fn ($m) => $m->created_at)->filter()->sort()->values();
            return $timestamps->first();
        };
        $from = $first($referenceMedia);
        $to = $first($postMedia);
        if (!$from || !$to) {
            return null;
        }
        return round(max(0, $from->diffInMinutes($to)) / 1440, 1);
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
            $mode = $this->normalizeMode($media->getCustomProperty('mode'))
                ?? $this->normalizeMode(pathinfo($media->file_name, PATHINFO_FILENAME));
            $fileId = trim((string) $media->getCustomProperty('openai_file_id'));
            if ($mode && $fileId !== '') {
                $byMode[$mode] = ['file_id' => $fileId];
            }
        }

        // Backward-compatible fallback for scans uploaded before mode metadata
        // was stored. Satish confirmed the A5 order below is authoritative.
        if (count($byMode) !== count(self::CANONICAL_MODES)) {
            if (count($byMode) > 0 || $items->count() !== 5) {
                throw new \RuntimeException('Image mode labels are incomplete or duplicated. Provide one image for each canonical mode.');
            }
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
            'parallel_polarized' => 'surface_polarized',
            'cross_polarized' => 'subsurface_polarized',
            'woods_uva_365' => 'woods_uv',
            'red_vascular_630' => 'red',
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
