<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\TreatmentSession;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;

class ReportAssetHelper
{
    public static function getUiAssets()
    {
        $base = public_path('images/v5');
        $files = [
            'brand_icon' => 'brand_icon.png',
            'ring_15' => 'ring_15.png',
            'arrow' => 'arrow_right.png',
            'icon_hydration' => 'icon_hydration.png',
            'icon_barrier' => 'icon_barrier.png',
            'icon_pores' => 'icon_pores.png',
            'icon_glow' => 'icon_glow.png',
            'icon_eye' => 'icon_eye.png',
            'icon_skin_type' => 'icon_skin_type.png',
            'icon_sun' => 'icon_sun.png',
            'icon_cleanse' => 'icon_cleanse.png',
            'icon_renewal' => 'icon_renewal.png',
            'icon_user' => 'icon_user.png',
            'icon_calendar' => 'icon_calendar.png',
            'icon_camera' => 'icon_camera.png',
            'icon_check' => 'icon_check.png',
            'icon_stable' => 'icon_stable.png',
            'icon_target' => 'icon_target.png'
        ];
        $assets = [];
        foreach ($files as $key => $file) {
            $assets[$key] = $base . '/' . $file;
        }
        return $assets;
    }

    public static function buildReportAssets(Assessment $record, $session = null)
    {
        $baselineMedia = $record->getMedia('assessment_images');

        if ($session instanceof TreatmentSession) {
            $postMedia = $session->getMedia('post_treatment_images');
            if ($postMedia->isEmpty()) {
                $postMedia = $session->getMedia('user_post_assessment_images');
            }
        } else {
            $postMedia = $record->getMedia('post_assessment_images');
        }

        $baselineMapped = self::mapMediaToModes($baselineMedia);
        $postMapped = self::mapMediaToModes($postMedia);

        $baseDir = storage_path('report_assets_v5/' . $record->id . ($session ? '_sess_' . $session->id : ''));
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0777, true);
        }

        $sizesBySet = [
            'baseline' => [
                'cover_large' => [288, 384],
                'baseline_small' => [137, 183],
                'mode_strip' => [80, 106],
                'mode_pair' => [55, 73],
                'mode_main' => [300, 400],
                'mode_grid' => [135, 180],
                'parameter' => [144, 192],
                'parameter_wide' => [155, 205],
                'result_large' => [119, 159],
                'result_compact' => [88, 117],
                'compare_large' => [155, 206],
            ],
            'post' => [
                'cover_tall' => [328, 437],
                'mode_pair' => [55, 73],
                'result_large' => [119, 159],
                'result_compact' => [88, 117],
                'compare_large' => [155, 206],
            ],
        ];

        $assets = ['baseline' => [], 'post' => []];

        foreach (['baseline' => $baselineMapped, 'post' => $postMapped] as $set => $paths) {
            foreach ($sizesBySet[$set] as $variant => $wh) {
                foreach ($paths as $mode => $sourcePath) {
                    $destPath = $baseDir . '/' . $set . '/' . $variant . '/' . $mode . '_' . substr(md5($sourcePath), 0, 8) . '.jpg';
                    $assets[$set][$variant][$mode] = self::prepareRaster($sourcePath, $destPath, $wh[0], $wh[1]);
                }
            }
        }

        return $assets;
    }

    public static function mapMediaToModes($mediaCollection)
    {
        $modes = ['red', 'subsurface_polarized', 'surface_polarized', 'white', 'woods_uv'];
        $v4Fallbacks = [
            'white' => ['white'],
            'red' => ['positive', 'red'],
            'subsurface_polarized' => ['subsurface', 'negative', 'blue', 'xpl', 'cross', 'cpl', 'brown'],
            'surface_polarized' => ['surface', 'ppl', 'parallel'],
            'woods_uv' => ['woods', 'uv']
        ];

        $mapped = [];
        $usedMediaIds = [];
        $fallbackIndex = 0;

        foreach ($modes as $mode) {
            $found = null;

            // 1. Try exact mode key matching
            foreach ($mediaCollection as $media) {
                $name = strtolower($media->file_name ?? $media->name ?? '');

                // Ensure 'surface_polarized' matching does NOT match 'subsurface_polarized'
                if ($mode === 'surface_polarized' && strpos($name, 'subsurface') !== false) {
                    continue;
                }

                if (strpos($name, $mode) !== false) {
                    $found = $media;
                    break;
                }
            }

            // 2. Try fallbacks if direct mode match wasn't found
            if (!$found && isset($v4Fallbacks[$mode])) {
                foreach ($v4Fallbacks[$mode] as $fb) {
                    foreach ($mediaCollection as $media) {
                        $name = strtolower($media->file_name ?? $media->name ?? '');

                        // Ensure 'surface' fallback does NOT match 'subsurface'
                        if (($fb === 'surface' || $fb === 'surface_polarized') && strpos($name, 'subsurface') !== false) {
                            continue;
                        }

                        if (strpos($name, $fb) !== false) {
                            $found = $media;
                            break;
                        }
                    }
                    if ($found) break;
                }
            }

            // 3. Fallback to picking an unused media item if available, or cycling through
            if (!$found && count($mediaCollection) > 0) {
                foreach ($mediaCollection as $media) {
                    $mId = $media->id ?? $media->getPath();
                    if (!in_array($mId, $usedMediaIds, true)) {
                        $found = $media;
                        break;
                    }
                }
                if (!$found) {
                    $found = $mediaCollection[$fallbackIndex % count($mediaCollection)];
                    $fallbackIndex++;
                }
            }

            if ($found) {
                $mId = $found->id ?? $found->getPath();
                $usedMediaIds[] = $mId;
                $mapped[$mode] = $found->getPath();
            }
        }
        return $mapped;
    }

    private static function prepareRaster(string $source, string $destination, int $width, int $height): string
    {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (is_file($destination) && filemtime($destination) >= filemtime($source)) {
            return realpath($destination) ?: $destination;
        }

        $ok = false;
        if (class_exists('Imagick')) {
            try {
                $img = new \Imagick($source);
                $img->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
                $img->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, true);
                $img->setImageFormat(strtolower(pathinfo($destination, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg');
                $img->setImageCompressionQuality(90);
                $img->writeImage($destination);
                $img->clear();
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }

        if (!$ok && function_exists('imagecreatefromjpeg')) {
            try {
                $info = @getimagesize($source);
                $src = null;
                if ($info) {
                    if ($info[2] === IMAGETYPE_JPEG) {
                        $src = @imagecreatefromjpeg($source);
                    } elseif ($info[2] === IMAGETYPE_PNG) {
                        $src = @imagecreatefrompng($source);
                    }
                }
                if ($src) {
                    $dst = imagecreatetruecolor($width, $height);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
                    if (strtolower(pathinfo($destination, PATHINFO_EXTENSION)) === 'png') {
                        imagepng($dst, $destination, 6);
                    } else {
                        imagejpeg($dst, $destination, 90);
                    }
                    unset($src, $dst);
                    $ok = true;
                }
            } catch (\Throwable $e) {
                $ok = false;
            }
        }

        if (!$ok) {
            copy($source, $destination);
        }

        return realpath($destination) ?: $destination;
    }

    public static function createMpdfInstance(): \Mpdf\Mpdf
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontVars = (new \Mpdf\Config\FontVariables())->getDefaults();

        $fontDirs = array_values(array_unique(array_merge(
            $defaultConfig['fontDir'],
            [
                storage_path('fonts'),
                public_path('fonts/Montserrat'),
            ]
        )));

        $fontData = $fontVars['fontdata'];

        // Add Playfair font
        $fontData['playfair'] = [
            'R' => 'PlayfairDisplay-Regular.ttf',
            'B' => 'PlayfairDisplay-Bold.ttf',
            'I' => 'PlayfairDisplay-Italic.ttf',
            'BI' => 'PlayfairDisplay-BoldItalic.ttf'
        ];

        // Add Montserrat font
        $fontData['montserrat'] = [
            'R' => 'Montserrat-Regular.ttf',
            'B' => 'Montserrat-Bold.ttf',
            'I' => 'Montserrat-Italic.ttf',
            'BI' => 'Montserrat-BoldItalic.ttf'
        ];

        $temp = storage_path('app/public/tmp');
        if (!is_dir($temp)) {
            @mkdir($temp, 0777, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [215.9, 279.4], // letter page size
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 6,
            'margin_bottom' => 11,
            'margin_header' => 0,
            'margin_footer' => 3,
            'tempDir' => $temp,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => 'dejavusans',
            'allow_local_filepath' => true,
            'showImageErrors' => true,
            'shrink_tables_to_fit' => 0,
        ]);

        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->showImageErrors = true;

        return $mpdf;
    }

    public static function getSkinAnalysisPdfContent(Assessment $record): string
    {
        $data = [];
        $data['patient'] = $record->user ? $record->user->toArray() : [];
        $data['patient']['name'] = $record->user ? $record->user->name : 'N/A';
        $data['patient']['age'] = ($record->user && $record->user->date_of_birth) ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
        $data['data'] = $record;
        $data['diagnosis'] = $record->diagnosis;
        $data['key_parametrs'] = collect($record->parameters_with_abnormal_scores['parameters_with_abnormal_scores'] ?? []);
        $data['assessmentImages'] = $record->images;

        $data['reportAssets'] = self::buildReportAssets($record);
        $data['uiAssets'] = self::getUiAssets();

        $html  = view('pdf.facial.skin_analysis_v2', $data)->render();

        $mpdf = self::createMpdfInstance();
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    public static function getReassessmentPdfContent(Assessment $record, $session = null, string $compareTo = 'baseline'): string
    {
        $patient = $record->user;
        $data = [];
        $data['patient'] = $patient ? $patient->toArray() : [];
        if ($patient) {
            $data['patient']['name'] = $patient->name;
            $data['patient']['age'] = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A';
        } else {
            $data['patient']['name'] = 'N/A';
            $data['patient']['age'] = 'N/A';
        }

        $data['assessment'] = $record;

        if ($session) {
            $data['report_date'] = $session->updated_at ?? $record->created_at;
            $reassessment = $session->post_diagnosis['reassessment'] ?? [];
            $postAssessmentImages = $session->post_images;
        } else {
            $data['report_date'] = $record->created_at;
            $reassessment = $record->post_diagnosis['reassessment'] ?? [];
            $postAssessmentImages = $record->post_images;
        }

        // We only compare to baseline as requested by the user
        $baselineDiagnosis = $record->diagnosis['diagnosis_report'] ?? [];
        foreach ($reassessment as $key => &$item) {
            $baselineScore = null;
            if (isset($baselineDiagnosis[$key])) {
                $baselineScore = $baselineDiagnosis[$key]['score_or_label'] ?? null;
            }
            // V3.12: engine-produced items (they carry a `raw` block) already hold the correct
            // reference score and a validated result. Overwriting the before score with the
            // original baseline diagnosis is wrong for session N vs session N-1, and recomputing
            // result from after != before re-labelled below-threshold changes as improved.
            if (isset($item['raw']) && is_array($item['raw'])) {
                continue;
            }
            if ($baselineScore !== null) {
                $item['before_treatment_score_or_label'] = $baselineScore;

                // Recalculate result against baseline
                $before = $baselineScore;
                $after = $item['post_treatment_score_or_label'] ?? '';
                $result = 'stable';
                if (strtolower(trim($before)) !== strtolower(trim($after))) {
                    preg_match('/\d+/', $before, $mBefore);
                    preg_match('/\d+/', $after, $mAfter);

                    if (isset($mBefore[0]) && isset($mAfter[0])) {
                        $valBefore = intval($mBefore[0]);
                        $valAfter = intval($mAfter[0]);

                        // Determine polarity (higher is better vs higher is worse)
                        $polarity = $baselineDiagnosis[$key]['score_polarity'] ?? null;
                        if (!$polarity && isset($item['score_polarity'])) {
                            $polarity = $item['score_polarity'];
                        }

                        $higherIsBetter = false;
                        if ($polarity === 'higher_is_better') {
                            $higherIsBetter = true;
                        } elseif ($polarity === 'higher_is_worse') {
                            $higherIsBetter = false;
                        } else {
                            // Fallback to key-based matching
                            $higherIsBetter = strpos(strtolower($key), 'glow') !== false || 
                                             strpos(strtolower($key), 'luminosity') !== false || 
                                             strpos(strtolower($key), 'hydration') !== false;
                        }

                        if ($higherIsBetter) {
                            $result = $valAfter > $valBefore ? 'improved' : ($valAfter < $valBefore ? 'declined' : 'stable');
                        } else {
                            $result = $valAfter < $valBefore ? 'improved' : ($valAfter > $valBefore ? 'declined' : 'stable');
                        }
                    } else {
                        if (strtolower($before) === 'present' && strtolower($after) === 'absent') {
                            $result = 'improved';
                        } else if (strtolower($before) === 'absent' && strtolower($after) === 'present') {
                            $result = 'declined';
                        }
                    }
                }
                $item['result'] = $result;
            }
        }
        unset($item);
        $data['reassessment'] = $reassessment;
        $data['counts'] = collect($data['reassessment'])->pluck('result')->countBy();

        $data['assessmentImages'] = $record->images;
        $data['postAssessmentImages'] = $postAssessmentImages;
        $data['compare_to'] = 'baseline'; // Always compare to baseline

        $data['reportAssets'] = self::buildReportAssets($record, $session);
        $data['uiAssets'] = self::getUiAssets();

        $html = view('pdf.facial.reassessment_v2', $data)->render();

        $mpdf = self::createMpdfInstance();
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
