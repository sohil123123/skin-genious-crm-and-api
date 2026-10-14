<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\TreatmentSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FacialCourseContinuation
{
    private const VERSION = 'reassessment_pairs_v1';
    private const LEASE_SECONDS = 900;

    // Generation stays in the existing JS planner. The database owns admission,
    // the immutable course length, the lease, and the append-only commit.
    private function locked(TreatmentSession $bound, callable $action)
    {
        return DB::transaction(function () use ($bound, $action) {
            $assessment = Assessment::whereKey($bound->assessment_id)->lockForUpdate()->firstOrFail();
            $source = $assessment->treatmentSessions()->whereKey($bound->id)->lockForUpdate()->firstOrFail();
            return $action($assessment, $source);
        }, 3);
    }

    private function reject(string $message): void
    {
        throw ValidationException::withMessages(['course_continuation' => $message]);
    }

    private function originalPlan(Assessment $assessment): array
    {
        $path = 'treatment-plans/treatment_plans_#'.$assessment->id.'.json';
        if (!Storage::disk('files')->exists($path)) {
            return [];
        }
        return json_decode(Storage::disk('files')->get($path), true)['treatment_plan'] ?? [];
    }

    private function progress(Assessment $assessment): array
    {
        $progress = $assessment->facial_course_progress;
        if (is_string($progress)) {
            $progress = json_decode($progress, true);
        }
        if (is_array($progress) && ($progress['version'] ?? null) === self::VERSION) {
            return $progress;
        }
        return ['version' => self::VERSION, 'plan' => $this->originalPlan($assessment), 'batches' => []];
    }

    private function saveProgress(Assessment $assessment, array $progress): void
    {
        $assessment->forceFill(['facial_course_progress' => $progress])->saveQuietly();
    }

    private function scope(Assessment $assessment, TreatmentSession $source, array $progress): ?array
    {
        $type = $assessment->getRawOriginal('selected_plan_type');
        $outline = $progress['plan']['course_outline'] ?? [];
        $total = count($outline);
        if ($type !== 'multiple' || $total < 5 || $total > 8) {
            return null;
        }
        foreach ($outline as $i => $slot) {
            if (($slot['session_number'] ?? null) !== $i + 1 || !is_numeric($slot['week'] ?? null) || $slot['week'] < 1 ||
                $i > 0 && $slot['week'] <= $outline[$i - 1]['week']) {
                $this->reject('The original course outline is incomplete or invalid; it cannot be extended safely.');
            }
        }
        $after = (int) $source->session_number;
        if ($after >= $total) {
            return ['after_session_number' => $after, 'total_sessions' => $total, 'session_numbers' => [], 'course_outline' => $outline];
        }
        if ($after < 2 || $after % 2 !== 0) {
            return null;
        }
        return ['after_session_number' => $after, 'total_sessions' => $total,
            'session_numbers' => range($after + 1, min($after + 2, $total)), 'course_outline' => $outline];
    }

    private function fingerprint(Assessment $assessment, TreatmentSession $source): string
    {
        $intake = $assessment->only(['diagnosis', 'parameters_with_abnormal_scores', 'age',
            'daily_sun_exposure_hours', 'social_event', 'upcoming_travel', 'medical_history', 'allergies',
            'is_pregnant', 'breastfeeding', 'skin_temp_for_head', 'left_cheek_temp', 'right_cheek_temp',
            'recent_peel_or_laser', 'retinol_used_last_night']);
        $history = $assessment->treatmentSessions()->where('session_number', '<=', $source->session_number)
            ->orderBy('session_number')->get()->map(fn ($row) => $row->only([
                'id', 'session_number', 'week', 'title', 'steps', 'concerns_addressed', 'post_diagnosis',
            ]))->all();
        return hash('sha256', json_encode([$intake, $source->post_diagnosis, $source->post_feature_packet,
            collect($source->post_images)->pluck('id')->all(), $history], JSON_THROW_ON_ERROR));
    }

    private function state(Assessment $assessment, TreatmentSession $source, array $progress): array
    {
        $scope = $this->scope($assessment, $source, $progress);
        if ($scope === null) {
            return ['status' => 'not_applicable'];
        }
        $result = ['course_continuation' => $scope];
        if (!$scope['session_numbers']) {
            return $result + ['status' => 'complete'];
        }
        $existing = $assessment->treatmentSessions()->whereIn('session_number', $scope['session_numbers'])
            ->orderBy('session_number')->pluck('session_number')->map(fn ($number) => (int) $number)->all();
        if ($existing === $scope['session_numbers']) {
            return $result + ['status' => 'ready'];
        }
        if ($existing) {
            $this->reject('Only part of the next batch exists. Review those records; no existing session will be overwritten.');
        }
        if (!is_array($source->post_diagnosis) || empty($source->post_diagnosis['reassessment'])) {
            return $result + ['status' => 'awaiting_reassessment'];
        }
        foreach ($scope['session_numbers'] as $number) {
            if (($scope['course_outline'][$number - 1]['session_kind'] ?? null) !== 'facial') {
                return $result + ['status' => 'blocked', 'message' => 'This course includes a separately assessed clinician session in the next batch. Use that assessment pathway before releasing it.'];
            }
        }
        $batch = $progress['batches'][(string) $source->session_number] ?? [];
        if (($batch['status'] ?? null) === 'generating' && ($batch['lease_until'] ?? 0) > time() &&
            hash_equals($batch['context_hash'], $this->fingerprint($assessment, $source))) {
            return $result + ['status' => 'generating'];
        }
        return $result + ['status' => ($batch['status'] ?? null) === 'failed' ? 'failed' : 'pending',
            'message' => $batch['last_error'] ?? null];
    }

    public function inspect(TreatmentSession $source): array
    {
        return $this->locked($source, fn ($assessment, $current) => $this->state($assessment, $current, $this->progress($assessment)));
    }

    public function claim(TreatmentSession $source): array
    {
        return $this->locked($source, function ($assessment, $current) {
            $progress = $this->progress($assessment);
            $state = $this->state($assessment, $current, $progress);
            if (!in_array($state['status'], ['pending', 'failed'], true)) {
                return $state;
            }
            $after = (int) $current->session_number;
            $previous = $assessment->treatmentSessions()->where('session_number', '<=', $after)->orderBy('session_number')->get();
            if ($previous->pluck('session_number')->map(fn ($n) => (int) $n)->all() !== range(1, $after)) {
                $this->reject('The preceding course sessions must exist in sequence before another batch can be released.');
            }
            if ($assessment->treatmentSessions()->where('session_number', '>', max($state['course_continuation']['session_numbers']))->exists()) {
                $this->reject('Later sessions already exist out of sequence; review them before releasing another batch.');
            }
            if ($after > 2 && ($progress['batches'][(string) ($after - 2)]['status'] ?? null) !== 'ready') {
                $this->reject('The previous reassessment-gated batch has not been committed.');
            }
            $token = (string) Str::uuid();
            $progress['batches'][(string) $after] = [
                'status' => 'generating', 'token' => $token, 'source_session_id' => $current->id,
                'context_hash' => $this->fingerprint($assessment, $current),
                'session_numbers' => $state['course_continuation']['session_numbers'],
                'lease_until' => time() + self::LEASE_SECONDS,
            ];
            $this->saveProgress($assessment, $progress);
            $intake = $assessment->only(['id', 'user_id', 'name', 'age', 'conversation_id', 'diagnosis',
                'parameters_with_abnormal_scores', 'daily_sun_exposure_hours', 'social_event', 'upcoming_travel',
                'medical_history', 'allergies', 'is_pregnant', 'breastfeeding', 'skin_temp_for_head',
                'left_cheek_temp', 'right_cheek_temp', 'recent_peel_or_laser', 'retinol_used_last_night']);
            $intake['gender'] = $assessment->user->gender ?? null;
            $intake['face_scan_machine'] = $assessment->user->face_scan_machine ?? null;
            return ['status' => 'claimed', 'token' => $token, 'course_continuation' => $state['course_continuation'],
                'context' => ['assessment' => $intake,
                    'source_session' => array_merge($current->only(['id', 'session_number', 'post_diagnosis', 'post_feature_packet']), ['post_images' => $current->post_images]),
                    'course_continuation' => $state['course_continuation'],
                    'previous_treatments' => $previous->map(fn ($row) => $row->only([
                        'session_number', 'title', 'week', 'treatment_time', 'steps', 'post_diagnosis',
                    ]))->all()]];
        });
    }

    public function complete(TreatmentSession $source, string $token, array $generated, array $features): array
    {
        return $this->locked($source, function ($assessment, $current) use ($token, $generated, $features) {
            $progress = $this->progress($assessment);
            $scope = $this->scope($assessment, $current, $progress);
            $batch = $progress['batches'][(string) $current->session_number] ?? [];
            if (($batch['status'] ?? null) === 'ready') {
                return ['status' => 'ready', 'course_continuation' => $scope];
            }
            if (($batch['status'] ?? null) !== 'generating' || !hash_equals($batch['token'] ?? '', $token)) {
                abort(409, 'This continuation claim is no longer active. Reload before retrying.');
            }
            if (!hash_equals($batch['context_hash'], $this->fingerprint($assessment, $current))) {
                abort(409, 'The reassessment, images, intake or previous treatments changed during generation. Retry using the saved current data.');
            }
            $root = $generated['treatment_plan'] ?? [];
            $sessions = $root['treatments'] ?? [];
            if ($scope === null || ($root['course_outline'] ?? []) != $scope['course_outline'] ||
                array_column($sessions, 'session_number') !== $scope['session_numbers']) {
                $this->reject('The generated batch must match exactly the next session numbers and unchanged original course outline.');
            }
            if (!is_array($features['proxies'] ?? null) || empty($features['proxies']) ||
                !is_array($features['diagnosis_report'] ?? null) || empty($features['diagnosis_report'])) {
                $this->reject('Current checkpoint scan features are required with this continuation.');
            }
            foreach ($sessions as $session) {
                $slot = $scope['course_outline'][$session['session_number'] - 1];
                $steps = $session['steps'] ?? [];
                $sum = array_sum(array_column($steps, 'duration'));
                if ($slot['session_kind'] !== 'facial' || ($session['week'] ?? null) != $slot['week'] || !$steps ||
                    $sum < 60 || $sum > 75 || abs($sum - ($session['treatment_time'] ?? 0)) > 0.000001 ||
                    ($steps[count($steps) - 1]['step_id'] ?? null) !== 'FINISH.SMS') {
                    $this->reject('Each appended facial must retain its original week, actual steps and validated 60–75 minute total.');
                }
                if ($assessment->treatmentSessions()->where('session_number', $session['session_number'])->exists()) {
                    abort(409, 'A requested session already exists; it will not be overwritten.');
                }
            }
            // CREATE only: never call the initial plan's updateOrCreate path here.
            foreach ($sessions as $session) {
                $assessment->treatmentSessions()->create([
                    'user_id' => $assessment->user_id, 'plan_type' => 'multiple',
                    'session_number' => $session['session_number'], 'title' => $session['title'],
                    'week' => $session['week'], 'treatment_time' => $session['treatment_time'],
                    'preparations_checklist_for_therapist' => $session['preparations_checklist_for_therapist'] ?? [],
                    'concerns_addressed' => $session['concerns_addressed'] ?? [], 'steps' => $session['steps'],
                    'daily_home_care_routine' => [], 'audio_text' => $session['script'] ?? null, 'status' => 'pending',
                ]);
                $progress['plan']['treatments'][] = $session;
            }
            foreach ($root['modality_omission_explanation'] ?? [] as $key => $text) {
                $progress['plan']['modality_omission_explanation'][$key] = trim(($progress['plan']['modality_omission_explanation'][$key] ?? '').' '.$text);
            }
            $progress['plan']['total_time'] = implode('; ', array_map(fn ($row) => 'Session '.$row['session_number'].': '.$row['treatment_time'].' minutes', $progress['plan']['treatments']));
            $progress['batches'][(string) $current->session_number] = [
                'status' => 'ready', 'source_session_id' => $current->id,
                'session_numbers' => $scope['session_numbers'], 'completed_at' => now()->toIso8601String(),
            ];
            $current->update(['post_feature_packet' => $features]);
            $assessment->forceFill(['total_time' => $progress['plan']['total_time'], 'facial_course_progress' => $progress])->saveQuietly();
            // DB owns the commit; legacy JSON consumers are refreshed only afterwards.
            return ['status' => 'ready', 'course_continuation' => $scope];
        });
    }

    // Serialize mirroring under the same assessment lock, read the newest DB
    // state, and retain the original file. Filesystem failure cannot undo a
    // committed clinical batch; a ready-state refresh retries this mirror.
    public function syncLegacyPlan(int $assessmentId): void
    {
        try {
            DB::transaction(function () use ($assessmentId) {
                $assessment = Assessment::whereKey($assessmentId)->lockForUpdate()->firstOrFail();
                $progress = $assessment->facial_course_progress;
                if (($progress['version'] ?? null) !== self::VERSION || empty($progress['plan'])) {
                    return;
                }
                $disk = Storage::disk('files');
                $path = 'treatment-plans/treatment_plans_#'.$assessmentId.'.json';
                $original = $disk->exists($path) ? $disk->get($path) : '{}';
                if (!$disk->exists($path.'.before-continuation.json')) {
                    if (!$disk->put($path.'.before-continuation.json', $original)) {
                        throw new \RuntimeException('Could not retain the original course JSON.');
                    }
                }
                $document = json_decode($original, true) ?: [];
                $document['treatment_plan'] = $progress['plan'];
                if (!$disk->put($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Could not refresh the course JSON mirror.');
                }
            }, 3);
        } catch (\Throwable $error) {
            Log::warning('Facial course committed; JSON mirror needs retry.', ['assessment_id' => $assessmentId, 'message' => $error->getMessage()]);
        }
    }

    public function release(TreatmentSession $source, string $token, string $message): array
    {
        return $this->locked($source, function ($assessment, $current) use ($token, $message) {
            $progress = $this->progress($assessment);
            $key = (string) $current->session_number;
            $batch = $progress['batches'][$key] ?? [];
            if (($batch['status'] ?? null) === 'generating' && hash_equals($batch['token'] ?? '', $token)) {
                $progress['batches'][$key] = ['status' => 'failed', 'last_error' => mb_substr($message, 0, 800)];
                $this->saveProgress($assessment, $progress);
            }
            return $this->state($assessment, $current, $progress);
        });
    }
}
