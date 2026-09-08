<?php

use App\Services\ReportAssetHelper;

class DummyMedia {
    public $id;
    public $file_name;
    public $name;
    public $path;

    public function __construct($id, $fileName, $path = null) {
        $this->id = $id;
        $this->file_name = $fileName;
        $this->name = $fileName;
        $this->path = $path ?: '/dummy/path/' . $fileName;
    }

    public function getPath() {
        return $this->path;
    }
}

test('mapMediaToModes distinguishes surface_polarized and subsurface_polarized when subsurface comes first', function () {
    $mediaCollection = collect([
        new DummyMedia(1, 'subsurface_polarized.jpg', '/images/subsurface_polarized.jpg'),
        new DummyMedia(2, 'surface_polarized.jpg', '/images/surface_polarized.jpg'),
        new DummyMedia(3, 'white.jpg', '/images/white.jpg'),
        new DummyMedia(4, 'woods_uv.jpg', '/images/woods_uv.jpg'),
        new DummyMedia(5, 'red.jpg', '/images/red.jpg'),
    ]);

    $mapped = ReportAssetHelper::mapMediaToModes($mediaCollection);

    expect($mapped['subsurface_polarized'])->toBe('/images/subsurface_polarized.jpg');
    expect($mapped['surface_polarized'])->toBe('/images/surface_polarized.jpg');
    expect($mapped['red'])->toBe('/images/red.jpg');
    expect($mapped['white'])->toBe('/images/white.jpg');
    expect($mapped['woods_uv'])->toBe('/images/woods_uv.jpg');
});

test('mapMediaToModes maps legacy surface and subsurface filenames correctly', function () {
    $mediaCollection = collect([
        new DummyMedia(1, 'subsurface.jpg', '/images/subsurface.jpg'),
        new DummyMedia(2, 'surface.jpg', '/images/surface.jpg'),
        new DummyMedia(3, 'white.jpg', '/images/white.jpg'),
        new DummyMedia(4, 'woods.jpg', '/images/woods.jpg'),
        new DummyMedia(5, 'red.jpg', '/images/red.jpg'),
    ]);

    $mapped = ReportAssetHelper::mapMediaToModes($mediaCollection);

    expect($mapped['subsurface_polarized'])->toBe('/images/subsurface.jpg');
    expect($mapped['surface_polarized'])->toBe('/images/surface.jpg');
});

test('mapMediaToModes maps ppl and xpl fallbacks correctly', function () {
    $mediaCollection = collect([
        new DummyMedia(1, 'xpl.png', '/images/xpl.png'),
        new DummyMedia(2, 'ppl.png', '/images/ppl.png'),
        new DummyMedia(3, 'white.png', '/images/white.png'),
        new DummyMedia(4, 'woods.png', '/images/woods.png'),
        new DummyMedia(5, 'red.png', '/images/red.png'),
    ]);

    $mapped = ReportAssetHelper::mapMediaToModes($mediaCollection);

    expect($mapped['subsurface_polarized'])->toBe('/images/xpl.png');
    expect($mapped['surface_polarized'])->toBe('/images/ppl.png');
});
