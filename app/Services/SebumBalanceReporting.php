<?php

namespace App\Services;

// Report interpretation only. No scoring equations or stored measurements change.
class SebumBalanceReporting
{
    private static function score(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) && $number >= 1 && $number <= 100 ? $number : null;
    }

    public static function isSebum(array $row, string $key = ''): bool
    {
        foreach ([$key, $row['parameter_name'] ?? '', $row['parameter'] ?? '', $row['concern'] ?? ''] as $name) {
            $name = preg_replace('/[^a-z0-9]/', '', strtolower((string) $name));
            if (in_array($name, ['skinsebumcontent', 'skinsebumindex', 'skinsebumindexv60'], true)) {
                return true;
            }
        }
        return false;
    }

    public static function normalizeReference(mixed $reference): ?array
    {
        if (!is_array($reference)) {
            return null;
        }
        if (($reference['status'] ?? '') === 'review_required') {
            return ['status' => 'review_required'];
        }
        if (array_key_exists('range', $reference)) {
            $range = $reference['range'];
            if (!is_array($range) || !array_is_list($range) || count($range) !== 2) {
                return ['status' => 'review_required'];
            }
            $low = self::score($range[0]);
            $high = self::score($range[1]);
            return $low !== null && $high !== null && $low <= $high
                ? ['range' => [$low, $high]] : ['status' => 'review_required'];
        }
        if (array_key_exists('target', $reference)) {
            $target = self::score($reference['target']);
            return $target === null ? ['status' => 'review_required'] : ['target' => $target];
        }
        return null;
    }

    private static function originalRow(array $row): array
    {
        $metadata = $row['_facial_score_display'] ?? null;
        return is_array($metadata) && is_array($metadata['raw'] ?? null)
            ? array_replace($row, $metadata['raw']) : $row;
    }

    public static function resolveReference(mixed $payload): ?array
    {
        $candidates = [];
        $visit = function (mixed $value, string $key = '') use (&$visit, &$candidates): void {
            if (!is_array($value)) {
                return;
            }
            if (self::isSebum($value, $key)) {
                $raw = self::originalRow($value);
                $reference = null;
                if (isset($raw['balance_target_range'])) {
                    $reference = self::normalizeReference(['range' => $raw['balance_target_range']]);
                } else {
                    $target = $raw['balance_target_score'] ?? $raw['target_single_session_score'] ?? $raw['target_score'] ?? null;
                    if ($target !== null) {
                        $reference = self::normalizeReference(['target' => $target]);
                    }
                }
                $reference ??= self::normalizeReference($value['_facial_score_display']['reporting_reference'] ?? null);
                if ($reference !== null) {
                    $candidates[] = $reference;
                }
            }
            foreach ($value as $childKey => $child) {
                if ($childKey !== '_facial_score_display') {
                    $visit($child, (string) $childKey);
                }
            }
        };
        $visit($payload);
        if (!$candidates) {
            return null;
        }
        $first = $candidates[0];
        foreach ($candidates as $candidate) {
            if ($candidate !== $first) {
                return ['status' => 'review_required'];
            }
        }
        return $first;
    }

    public static function compare(mixed $beforeValue, mixed $afterValue, mixed $reference): array
    {
        $before = self::score($beforeValue);
        $after = self::score($afterValue);
        $reference = self::normalizeReference($reference);
        if ($before === null || $after === null || $reference === null || ($reference['status'] ?? '') === 'review_required') {
            return ['result' => 'stable', 'status' => 'review_required', 'change_points' => 0,
                'note' => 'Your oil balance needs review against an established balance target. A numerical increase or decrease alone does not establish improvement.'];
        }
        $distance = static fn (float $value): float => isset($reference['range'])
            ? max($reference['range'][0] - $value, 0, $value - $reference['range'][1])
            : abs($value - $reference['target']);
        $distanceBefore = $distance($before);
        $distanceAfter = $distance($after);
        $common = ['distance_before' => $distanceBefore, 'distance_after' => $distanceAfter];
        if ($distanceBefore - $distanceAfter > 1e-9) {
            return $common + ['result' => 'improved', 'status' => 'toward_existing_target', 'change_points' => $distanceBefore - $distanceAfter,
                'note' => 'Your oil level has moved closer to the established balance target. Improvement can involve either an increase or a decrease.'];
        }
        if ($distanceBefore <= 1e-9 && $distanceAfter <= 1e-9) {
            return $common + ['result' => 'stable', 'status' => 'balanced', 'change_points' => 0,
                'note' => 'Your oil level remains balanced within the established target.'];
        }
        if (abs($distanceBefore - $distanceAfter) <= 1e-9) {
            return $common + ['result' => 'stable', 'status' => 'unchanged_balance', 'change_points' => 0,
                'note' => 'Your oil level has the same distance from the balance target. Higher or lower is not automatically better.'];
        }
        return $common + ['result' => 'stable', 'status' => 'review_required', 'change_points' => 0,
            'note' => 'Your oil level is farther from the established balance target and needs review. The measured scores are retained for clinical interpretation.'];
    }

    // Both PDF paths compare with the original baseline. Only sebum rows are
    // projected; caller arrays/models are never saved or mutated by this method.
    public static function forReportRows(array $rows, array $baseline): array
    {
        $reference = self::resolveReference($baseline);
        $diagnosis = $baseline['diagnosis_report'] ?? [];
        foreach ($rows as $key => &$row) {
            if (!is_array($row) || !self::isSebum($row, (string) $key)) {
                continue;
            }
            $raw = self::originalRow($row);
            $before = $raw['before_treatment_score_or_label'] ?? null;
            foreach ($diagnosis as $baselineKey => $baselineRow) {
                if (is_array($baselineRow) && self::isSebum($baselineRow, (string) $baselineKey)) {
                    $baselineRaw = self::originalRow($baselineRow);
                    $baselineScore = self::score($baselineRaw['score_or_label'] ?? $baselineRaw['current_score'] ?? null);
                    if ($baselineScore !== null) {
                        $before = $baselineScore;
                        $row['before_treatment_score_or_label'] = (int) round($before);
                    }
                    break;
                }
            }
            $comparison = self::compare($before, $raw['post_treatment_score_or_label'] ?? null,
                $reference ?? self::resolveReference([(string) $key => $row]));
            $row['result'] = $comparison['result'];
            $row['patient_facing_change_points'] = (int) round($comparison['change_points']);
            $row['report_comparison_status'] = $comparison['status'];
            $row['score_explanation'] = $comparison['note'];
        }
        unset($row);
        return $rows;
    }
}
