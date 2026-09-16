<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AssessmentImageDownloadService
{
    public const IMAGE_TYPES = [
        'woods_uv' => 'Woods UV',
        'white' => 'White',
        'red' => 'Red',
        'subsurface_polarized' => 'Subsurface Polarized',
        'surface_polarized' => 'Surface Polarized',
    ];

    public function download(Assessment $assessment, ?array $imageTypes = null): StreamedResponse
    {
        $imageTypes ??= array_keys(self::IMAGE_TYPES);
        if ($imageTypes === [] || array_diff($imageTypes, array_keys(self::IMAGE_TYPES)) !== []) {
            throw ValidationException::withMessages(['image_types' => 'Select at least one valid image type.']);
        }

        $assessment->loadMissing('user');
        // The model's treatment_sessions accessor shadows the relationship property.
        if (! $assessment->relationLoaded('treatmentSessions')) {
            $assessment->load('treatmentSessions.media');
        }
        $sessions = $assessment->getRelation('treatmentSessions');
        $sessions->loadMissing('media');
        $pigmentation = $assessment->assessment_type === 'pigmentation';
        $before = $assessment->getMedia($pigmentation ? 'pigmentation_pre_assessment_images' : 'assessment_images');
        $after = $assessment->getMedia($pigmentation ? 'pigmentation_post_assessment_images' : 'post_assessment_images');

        foreach ($sessions->where('status', 'completed')->sortByDesc('session_number') as $session) {
            $images = $session->getMedia('post_treatment_images');
            if ($images->isEmpty()) {
                $images = $session->getMedia('user_post_assessment_images');
            }
            if ($images->isNotEmpty()) {
                $after = $images;
                break;
            }
        }

        $client = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/u', '_', $assessment->user?->name ?? 'Client');
        $client = trim(mb_substr($client, 0, 100), " .\t\n\r\0\x0B");
        if ($client === '' || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $client)) {
            $client = 'Client_'.$assessment->id;
        }

        $path = tempnam(sys_get_temp_dir(), 'assessment_images_');
        if ($path === false) {
            throw new \RuntimeException('Unable to create the image download.');
        }

        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create the image archive.');
            }
            $opened = true;
            $count = 0;
            foreach (['before' => $before, 'after' => $after] as $folder => $images) {
                $zip->addEmptyDir("{$client}/{$folder}");
                $mapped = [];
                foreach ($images as $media) {
                    $mode = $this->mode($media);
                    if ($mode !== null && in_array($mode, $imageTypes, true)) {
                        $mapped[$mode] = $media;
                    }
                }
                foreach ($mapped as $mode => $media) {
                    $contents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
                    if (! is_string($contents) || $contents === '') {
                        throw new \RuntimeException('An assessment image could not be read.');
                    }
                    if (! $zip->addFromString("{$client}/{$folder}/{$mode}.jpg", $this->jpeg($contents))) {
                        throw new \RuntimeException('Unable to add an assessment image to the archive.');
                    }
                    $count++;
                }
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['images' => 'No assessment images are available for the selected image types.']);
            }
            if (! $zip->close()) {
                throw new \RuntimeException('Unable to finish the image archive.');
            }
            $opened = false;
        } catch (\Throwable $exception) {
            if ($opened) {
                $zip->close();
            }
            @unlink($path);
            throw $exception;
        }

        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, "{$client}_assessment_{$assessment->id}_images.zip", ['Content-Type' => 'application/zip']);
    }

    private function mode(Media $media): ?string
    {
        $aliases = [
            'woods_uv' => ['woods_uv', 'woods', 'uv'],
            'white' => ['white'],
            'red' => ['red', 'positive'],
            'subsurface_polarized' => ['subsurface_polarized', 'subsurface', 'negative', 'blue', 'xpl', 'cross', 'cpl', 'brown'],
            'surface_polarized' => ['surface_polarized', 'surface', 'ppl', 'parallel'],
        ];
        foreach ([$media->getCustomProperty('mode'), $media->file_name, $media->name] as $value) {
            if (! is_string($value)) {
                continue;
            }
            $value = strtolower(preg_replace('/[\s-]+/', '_', $value));
            foreach ($aliases as $mode => $names) {
                foreach ($names as $name) {
                    if (preg_match('/(?:^|[^a-z])'.preg_quote($name, '/').'(?:$|[^a-z])/', $value)) {
                        return $mode;
                    }
                }
            }
        }

        return null;
    }

    private function jpeg(string $contents): string
    {
        if (@getimagesizefromstring($contents)[2] === IMAGETYPE_JPEG) {
            return $contents;
        }
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new \RuntimeException('An assessment image could not be converted to JPEG.');
        }
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        ob_start();
        try {
            if (! imagejpeg($canvas, null, 95)) {
                throw new \RuntimeException('Unable to encode an assessment image.');
            }

            return ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($canvas);
            imagedestroy($image);
        }
    }
}
