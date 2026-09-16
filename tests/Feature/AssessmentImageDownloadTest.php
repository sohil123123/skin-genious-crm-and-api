<?php

use App\Models\Assessment;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\AssessmentImageDownloadService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function downloadImageMedia(int $id, string $name, string $collection, ?string $mode = null): Media
{
    $media = new Media;
    $media->forceFill([
        'id' => $id, 'file_name' => $name, 'name' => $name,
        'disk' => 'assessment_download_test', 'collection_name' => $collection,
        'custom_properties' => ['mode' => $mode],
    ]);
    $image = imagecreatetruecolor(2, 3);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);
    Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), $png);

    return $media;
}

function downloadAssessmentFixture(array $media, array $sessions = []): Assessment
{
    $assessment = new Assessment;
    $assessment->forceFill(['id' => 42, 'assessment_type' => 'normal']);
    $assessment->exists = true;
    $assessment->setRelation('user', new User(['first_name' => 'Jane', 'last_name' => 'Doe']));
    $assessment->setRelation('media', new Collection($media));
    $assessment->setRelation('treatmentSessions', new Collection($sessions));

    return $assessment;
}

function readAssessmentDownload(Assessment $assessment, ?array $imageTypes = null): array
{
    $response = app(AssessmentImageDownloadService::class)->download($assessment, $imageTypes);
    ob_start();
    $response->sendContent();
    $contents = ob_get_clean();
    $path = tempnam(sys_get_temp_dir(), 'image_test_');
    file_put_contents($path, $contents);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();
    unlink($path);

    return $entries;
}

beforeEach(fn () => Storage::fake('assessment_download_test'));

test('downloads all five before and after modes as real JPEGs in client folders', function () {
    $media = [];
    $id = 1;
    $modes = ['woods_uv', 'white', 'red', 'subsurface_polarized', 'surface_polarized'];
    foreach (['assessment_images', 'post_assessment_images'] as $collection) {
        foreach ($modes as $mode) {
            $media[] = downloadImageMedia($id++, "$mode.png", $collection);
        }
    }
    $entries = readAssessmentDownload(downloadAssessmentFixture($media));
    expect($entries)->toHaveCount(12);
    foreach (['before', 'after'] as $folder) {
        foreach ($modes as $mode) {
            expect(getimagesizefromstring($entries["Jane Doe/$folder/$mode.jpg"])[2])->toBe(IMAGETYPE_JPEG);
        }
    }
});

test('uses mode metadata and never duplicates missing modes', function () {
    $entries = readAssessmentDownload(downloadAssessmentFixture([
        downloadImageMedia(1, 'surface_polarized.png', 'assessment_images', 'subsurface_polarized'),
        downloadImageMedia(2, 'unknown.png', 'assessment_images'),
    ]));
    expect(array_keys($entries))->toBe([
        'Jane Doe/before/', 'Jane Doe/before/subsurface_polarized.jpg', 'Jane Doe/after/',
    ]);
});

test('uses the latest completed session with images and supports the legacy collection', function () {
    $sessions = [];
    foreach ([[1, 'completed', 'red'], [2, 'completed', 'white'], [3, 'pending', 'woods_uv'], [4, 'completed', null]] as [$number, $status, $mode]) {
        $session = new TreatmentSession(['session_number' => $number, 'status' => $status]);
        $session->exists = true;
        $session->setRelation('media', new Collection($mode ? [downloadImageMedia($number + 10, "$mode.png", 'user_post_assessment_images')] : []));
        $sessions[] = $session;
    }
    $entries = readAssessmentDownload(downloadAssessmentFixture([
        downloadImageMedia(1, 'red.png', 'post_assessment_images'),
    ], $sessions));
    expect(array_keys($entries))->toBe(['Jane Doe/before/', 'Jane Doe/after/', 'Jane Doe/after/white.jpg']);
});

test('rejects downloads without recognized images', function () {
    app(AssessmentImageDownloadService::class)->download(downloadAssessmentFixture([]));
})->throws(ValidationException::class, 'No assessment images are available for the selected image types.');

test('includes only selected image types in both folders', function (array $selected) {
    $media = [];
    $id = 1;
    foreach (['assessment_images', 'post_assessment_images'] as $collection) {
        foreach (array_keys(AssessmentImageDownloadService::IMAGE_TYPES) as $mode) {
            $media[] = downloadImageMedia($id++, "$mode.png", $collection);
        }
    }
    $entries = readAssessmentDownload(downloadAssessmentFixture($media), $selected);
    $expected = ['Jane Doe/before/', 'Jane Doe/after/'];
    foreach (['before', 'after'] as $folder) {
        foreach ($selected as $mode) {
            $expected[] = "Jane Doe/$folder/$mode.jpg";
        }
    }
    expect(array_keys($entries))->toEqualCanonicalizing($expected);
})->with([
    'white only' => [['white']],
    'multiple types' => [['woods_uv', 'red', 'surface_polarized']],
]);

test('rejects empty or invalid image type selections', function (array $selected) {
    app(AssessmentImageDownloadService::class)->download(downloadAssessmentFixture([]), $selected);
})->with([
    'empty' => [[]],
    'invalid' => [['unknown']],
    'mixed' => [['white', 'unknown']],
])->throws(ValidationException::class, 'Select at least one valid image type.');

test('does not substitute other image types when a selected type is unavailable', function () {
    $assessment = downloadAssessmentFixture([downloadImageMedia(1, 'red.png', 'assessment_images')]);
    app(AssessmentImageDownloadService::class)->download($assessment, ['white']);
})->throws(ValidationException::class, 'No assessment images are available for the selected image types.');

test('sanitizes client folder names', function () {
    $assessment = downloadAssessmentFixture([downloadImageMedia(1, 'white.png', 'assessment_images')]);
    $assessment->user->first_name = '../Jane\\Doe:';
    $entries = readAssessmentDownload($assessment);
    foreach (array_keys($entries) as $name) {
        expect($name)->not->toContain('../', '\\', ':');
    }
});
