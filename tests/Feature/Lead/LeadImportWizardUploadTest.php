<?php

declare(strict_types=1);

use App\Filament\Pages\LeadImportWizard;
use App\Models\Clinic;
use App\Models\LeadImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Covers the upload step analysing the file as soon as it is chosen.
 *
 * The analysis used to run only in the step's afterValidation hook, so the
 * detected-format panel stayed invisible until the user pressed Next and came
 * back. It now runs from the file field's afterStateUpdated hook.
 *
 * Livewire's set() on a file property routes through its upload simulation,
 * which only accepts its own synthetic fakes — and the encoding sniffing under
 * test needs the fixture's real UTF-16 bytes. The upload handler is therefore
 * driven directly with the state a finished upload leaves behind, which is the
 * same call the new hook makes.
 */
beforeEach(function (): void {
    $dotenv = \Dotenv\Dotenv::createArrayBacked(base_path())->safeLoad();
    $connection = $dotenv['DB_CONNECTION'] ?? 'mysql';

    config([
        "database.connections.{$connection}.database" => $dotenv['DB_DATABASE'] ?? null,
        "database.connections.{$connection}.host" => $dotenv['DB_HOST'] ?? '127.0.0.1',
        "database.connections.{$connection}.port" => $dotenv['DB_PORT'] ?? '3306',
        "database.connections.{$connection}.username" => $dotenv['DB_USERNAME'] ?? 'root',
        "database.connections.{$connection}.password" => $dotenv['DB_PASSWORD'] ?? '',
        'database.default' => $connection,
    ]);

    \Illuminate\Support\Facades\DB::purge($connection);
    \Illuminate\Support\Facades\DB::setDefaultConnection($connection);

    try {
        $hasTables = \Illuminate\Support\Facades\Schema::connection($connection)->hasTable('lead_imports');
    } catch (Throwable) {
        $hasTables = false;
    }

    if (! $hasTables) {
        $this->markTestSkipped('Lead tables are not migrated on the default connection.');
    }

    $admin = User::query()
        ->withoutGlobalScopes()
        ->whereHas('roles', fn ($query) => $query->where('name', config('project.roles.super_admin')))
        ->first();

    if ($admin === null) {
        $this->markTestSkipped('No super admin available.');
    }

    $this->clinic = Clinic::query()->active()->first();

    if ($this->clinic === null) {
        $this->markTestSkipped('No active clinic available.');
    }

    $this->actingAs($admin);

    $this->fixtures = collect(glob(base_path('tests/Fixtures/leads/*.csv')))->values();

    if ($this->fixtures->isEmpty()) {
        $this->markTestSkipped('No lead export fixture available.');
    }

    // Imports created here are real rows; they are removed again so repeated
    // runs do not accumulate.
    $this->createdImportIds = [];
});

afterEach(function (): void {
    foreach ($this->createdImportIds ?? [] as $id) {
        LeadImport::withoutEvents(fn () => LeadImport::find($id)?->forceDelete());
    }
});

/**
 * The fixture as an upload, copied so a test run never consumes it.
 */
function leadUpload(string $path): UploadedFile
{
    $copy = tempnam(sys_get_temp_dir(), 'lead') . '.csv';
    copy($path, $copy);

    return new UploadedFile($copy, basename($path), null, null, true);
}

/**
 * The fixture as a Livewire temporary upload.
 *
 * The updated() hook guards on this exact type, because that is what the state
 * holds once a browser upload has landed, so the guard is only exercised by the
 * real class.
 */
function leadTemporaryUpload(string $path): TemporaryUploadedFile
{
    $disk = FileUploadConfiguration::disk();

    Storage::fake($disk);

    // Livewire encodes the original filename into the temporary one and reads
    // it back out of there, so the meta segment is not decoration.
    $name = 'lead-' . Str::random(8)
        . str('-meta' . base64_encode(basename($path)) . '-')->replace('/', '_')
        . '.csv';

    Storage::disk($disk)->put('livewire-tmp/' . $name, (string) file_get_contents($path));

    // createFromLivewire prefixes livewire-tmp/ itself, so only the name goes in.
    return TemporaryUploadedFile::createFromLivewire($name);
}

/**
 * Run the wizard's upload handler with the given file selected.
 */
function chooseFile(LeadImportWizard $wizard, int $clinicId, string $path): void
{
    $wizard->handleUpload([leadUpload($path)], $clinicId);
}

it('analyses the file while still on the upload step', function (): void {
    $wizard = new LeadImportWizard;

    chooseFile($wizard, $this->clinic->getKey(), $this->fixtures[0]);

    $this->createdImportIds[] = $wizard->importId;

    expect($wizard->importId)->not->toBeNull();

    $import = $wizard->currentImport();

    // The panel reads its encoding, delimiter and row counts off the analysis,
    // so this being populated is exactly what makes it render.
    expect($import->analysis)->not->toBeNull()
        ->and($import->original_filename)->toBe(basename($this->fixtures[0]))
        ->and($import->encoding)->not->toBeNull()
        ->and($import->delimiter)->not->toBeNull();
});

it('does not re-upload the same file twice', function (): void {
    $wizard = new LeadImportWizard;

    chooseFile($wizard, $this->clinic->getKey(), $this->fixtures[0]);
    $first = $wizard->importId;
    $this->createdImportIds[] = $first;

    // The afterValidation hook still fires on Next, so the handler has to be
    // idempotent for the same file.
    chooseFile($wizard, $this->clinic->getKey(), $this->fixtures[0]);

    expect($wizard->importId)->toBe($first);
});

it('analyses the upload from the livewire updated hook', function (): void {
    $wizard = new LeadImportWizard;
    $wizard->data = [
        'clinic_id' => $this->clinic->getKey(),
        'file' => [leadTemporaryUpload($this->fixtures[0])],
    ];

    // This is the path the browser actually takes. The file field's own
    // afterStateUpdated never fires under storeFiles(false), so if this hook
    // regresses the panel silently stops appearing on upload.
    $wizard->updated('data.file');

    $this->createdImportIds[] = $wizard->importId;

    expect($wizard->importId)->not->toBeNull('the updated hook did not analyse the upload')
        ->and($wizard->currentImport()?->analysis)->not->toBeNull();
});

it('keeps the temporary upload available for the rest of the wizard', function (): void {
    $upload = leadTemporaryUpload($this->fixtures[0]);
    $disk = Storage::disk(FileUploadConfiguration::disk());
    $tempPath = 'livewire-tmp/' . $upload->getFilename();

    $wizard = new LeadImportWizard;
    $wizard->data = ['clinic_id' => $this->clinic->getKey(), 'file' => [$upload]];
    $wizard->updated('data.file');

    $this->createdImportIds[] = $wizard->importId;

    // The file stays in form state for the whole wizard, and Filament
    // re-validates that state on every step — including a max: rule that reads
    // the file's size. Deleting it at upload time made pressing Next fail with
    // UnableToRetrieveMetadata.
    expect($wizard->importId)->not->toBeNull()
        ->and($disk->exists($tempPath))->toBeTrue('the temporary upload was deleted too early');

    // Re-running the handler is what pressing Next does, and it must survive
    // finding the same file still in place.
    $wizard->handleUpload($wizard->data['file'], $this->clinic->getKey());

    expect($wizard->importId)->not->toBeNull();
});

it('discards the temporary upload once the import is started', function (): void {
    $upload = leadTemporaryUpload($this->fixtures[0]);
    $disk = Storage::disk(FileUploadConfiguration::disk());
    $tempPath = 'livewire-tmp/' . $upload->getFilename();

    $wizard = new LeadImportWizard;
    $wizard->data = ['clinic_id' => $this->clinic->getKey(), 'file' => [$upload]];
    $wizard->updated('data.file');

    $import = $wizard->currentImport();
    $this->createdImportIds[] = $wizard->importId;

    expect($import)->not->toBeNull();

    // startImport() validates the whole form, which needs the later steps'
    // state, so the discard it performs is exercised directly.
    $wizard->discardTemporaryUpload($upload);

    // storeAs() copies rather than moves, and storeFiles(false) means Filament
    // never disposes of it, so without this every import leaves a second copy
    // of the export in livewire-tmp.
    expect($disk->exists($tempPath))->toBeFalse('the temporary upload was left behind');

    // The permanent copy is what the queued job reads, so it must survive.
    expect(Storage::disk($import->disk)->exists($import->stored_path))
        ->toBeTrue('the stored export was deleted along with the temporary file');
});

it('ignores an updated hook for an unrelated property', function (): void {
    $wizard = new LeadImportWizard;
    $wizard->data = ['clinic_id' => $this->clinic->getKey(), 'file' => []];

    $wizard->updated('data.clinic_id');

    expect($wizard->importId)->toBeNull();
});

it('can resolve the summary panel the upload field re-renders', function (): void {
    $component = Livewire::test(LeadImportWizard::class);

    // The file field names this key in
    // partiallyRenderComponentsAfterStateUpdated(); Filament throws if it
    // cannot find it, so a renamed or removed key breaks choosing a file.
    $keys = [];
    $walk = function ($container) use (&$walk, &$keys): void {
        foreach ($container->getComponents(withHidden: true) as $child) {
            if (filled($key = $child->getKey())) {
                $keys[] = $key;
            }

            foreach ($child->getChildSchemas(withHidden: true) as $schema) {
                $walk($schema);
            }
        }
    };

    $walk($component->instance()->getSchema('form'));

    $match = collect($keys)->first(fn (string $key): bool => str_ends_with($key, 'upload-summary'));

    expect($match)->not->toBeNull(
        'no component keyed upload-summary; found: ' . implode(', ', $keys)
    );
});

it('renders the summary panel unconditionally so it can be re-rendered', function (): void {
    $component = Livewire::test(LeadImportWizard::class);

    $panel = null;
    $walk = function ($container) use (&$walk, &$panel): void {
        foreach ($container->getComponents(withHidden: true) as $child) {
            if (str_ends_with((string) $child->getKey(), 'upload-summary')) {
                $panel = $child;
            }

            foreach ($child->getChildSchemas(withHidden: true) as $schema) {
                $walk($schema);
            }
        }
    };

    $walk($component->instance()->getSchema('form'));

    // A component hidden at page load has no node in the DOM, and partial
    // rendering replaces a node rather than creating one — so a ->visible()
    // guard here would leave the panel invisible until a full page render,
    // which is the bug this whole arrangement exists to avoid.
    expect($panel)->not->toBeNull()
        ->and($panel->isHidden())->toBeFalse('the panel is hidden, so partial rendering cannot reveal it');
});

it('refuses an unreadable file by halting rather than crashing', function (): void {
    $wizard = new LeadImportWizard;

    $junk = tempnam(sys_get_temp_dir(), 'lead') . '.csv';
    file_put_contents($junk, '');

    // haltWith() throws Halt, which the wizard's own hooks catch. The file
    // field's hook has to catch it too — uncaught, it surfaced as a 500 on the
    // Livewire update instead of the notification.
    expect(fn () => $wizard->handleUpload(
        [new UploadedFile($junk, 'empty.csv', null, null, true)],
        $this->clinic->getKey(),
    ))->toThrow(\Filament\Support\Exceptions\Halt::class);
});

it('re-analyses when a different file is chosen', function (): void {
    if ($this->fixtures->count() < 2) {
        $this->markTestSkipped('Need two fixtures to swap between.');
    }

    $wizard = new LeadImportWizard;

    chooseFile($wizard, $this->clinic->getKey(), $this->fixtures[0]);
    $first = $wizard->importId;
    $this->createdImportIds[] = $first;

    chooseFile($wizard, $this->clinic->getKey(), $this->fixtures[1]);
    $second = $wizard->importId;
    $this->createdImportIds[] = $second;

    // Swapping the file must not leave the summary describing the first one.
    expect($second)->not->toBe($first)
        ->and($wizard->currentImport()->original_filename)->toBe(basename($this->fixtures[1]));
});
