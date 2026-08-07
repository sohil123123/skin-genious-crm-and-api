<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\DTOs\Lead\ColumnMappingDto;
use App\DTOs\Lead\CsvAnalysisResult;
use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\CrmLeadField;
use App\Enums\DuplicateStrategy;
use App\Enums\LeadFieldType;
use App\Filament\Resources\LeadImports\LeadImportResource;
use App\Http\Requests\Lead\StoreLeadImportRequest;
use App\Http\Requests\Lead\StoreLeadMappingRequest;
use App\Models\Clinic;
use App\Models\LeadCustomField;
use App\Models\LeadImport;
use App\Services\Lead\LeadImportService;
use App\Services\Lead\LeadRowImporterService;
use App\Services\Lead\MappingTemplateService;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * The four-step import wizard: upload, map, configure, preview.
 *
 * The wizard's job is to make sure nothing is imported until the user has seen
 * exactly what will happen. The preview step in particular shows every value
 * both as it appears in the file and as it will be stored, because the
 * transformations this module performs — stripping "p:" from phone numbers,
 * salvaging malformed ones, humanising snake_cased answers — are significant
 * enough that they need to be visible before they are committed.
 */
class LeadImportWizard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $title = 'Import Leads';

    protected static ?string $navigationLabel = 'Import Leads';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.lead-import-wizard';

    /**
     * Fields imported but not shown in the preview table.
     *
     * These are Meta's opaque identifiers. They are essential for deduplication
     * and for joining back to the Ads API, but a human checking a preview
     * cannot verify a nineteen-digit number, and eight such columns crowd out
     * the ones they can verify.
     */
    private const PREVIEW_HIDDEN_FIELDS = [
        CrmLeadField::FbLeadId,
        CrmLeadField::CampaignId,
        CrmLeadField::AdsetId,
        CrmLeadField::AdId,
        CrmLeadField::FormId,
        CrmLeadField::IsOrganic,
        CrmLeadField::Platform,
        CrmLeadField::FbLeadStatus,
    ];

    /**
     * The order visible preview columns appear in, most checkable first.
     */
    private const PREVIEW_FIELD_ORDER = [
        CrmLeadField::FullName,
        CrmLeadField::Phone,
        CrmLeadField::Email,
        CrmLeadField::City,
        CrmLeadField::State,
        CrmLeadField::FbCreatedTime,
        CrmLeadField::CampaignName,
        CrmLeadField::AdsetName,
        CrmLeadField::AdName,
        CrmLeadField::FormName,
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** The import being configured; null until the file has been uploaded. */
    public ?int $importId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Create:LeadImport') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'clinic_id' => auth()->user()?->clinic_id,
            'settings' => ImportSettingsDto::defaults(),
            'duplicate_strategy' => (string) config('leads.duplicates.default_strategy', 'skip'),
            'duplicate_match_fields' => (array) config('leads.duplicates.default_match_fields', ['fb_lead_id']),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    $this->uploadStep(),
                    $this->mappingStep(),
                    $this->settingsStep(),
                    $this->previewStep(),
                ])
                    ->persistStepInQueryString('import-step')
                    // Wizard::submitAction() renders markup rather than taking
                    // an Action instance, so the final button is declared here.
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit" size="sm" color="success" icon="heroicon-m-play">
                            Start Import
                        </x-filament::button>
                    BLADE))),
            ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Step 1 — Upload
    // ────────────────────────────────────────────────────────────────

    protected function uploadStep(): Step
    {
        return Step::make('Upload')
            ->description('Choose the Facebook lead export')
            ->icon('heroicon-o-arrow-up-tray')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('clinic_id')
                    ->label('Clinic')
                    ->options(fn (): array => Clinic::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default(auth()->user()?->clinic_id)
                    ->required()
                    ->searchable()
                    ->helperText('Imported leads belong to this clinic and duplicates are matched within it.')
                    // A clinic user has exactly one clinic, so the choice is
                    // theirs only when they can see more than one.
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin')))
                    ->dehydrated(),

                FileUpload::make('file')
                    ->label('Lead export file')
                    ->required()
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream', '.csv', '.tsv', '.txt'])
                    ->maxSize((int) config('leads.upload.max_size_kb', 51200))
                    // The file is stored by hand in afterValidation so the
                    // original filename, the content hash and the detected
                    // format can all be captured in the same transaction.
                    ->storeFiles(false)
                    ->helperText(
                        'Facebook exports are UTF-16 tab-separated files with a .csv extension. '
                        . 'The encoding and delimiter are detected automatically and shown before anything is imported.'
                    )
                    ->columnSpan(fn (): int => check_role(config('project.roles.super_admin')) ? 1 : 2),

                View::make('filament.lead.upload-summary')
                    ->viewData(fn (): array => ['import' => $this->currentImport()])
                    ->visible(fn (): bool => $this->currentImport() !== null)
                    ->columnSpanFull(),
            ])
            ->afterValidation(function (Get $get): void {
                $this->handleUpload($get);
            });
    }

    /**
     * Store the upload, detect its format and analyse it.
     */
    protected function handleUpload(Get $get): void
    {
        // Re-entering the step after going back should not re-upload the file.
        if ($this->importId !== null && $this->currentImport()?->analysis !== null) {
            return;
        }

        $userId = (int) auth()->id();
        $limiter = 'lead-import-upload:' . $userId;

        if (RateLimiter::tooManyAttempts($limiter, (int) config('leads.upload.rate_limit.attempts', 20))) {
            $this->haltWith(
                'Too many uploads',
                sprintf('Try again in %d seconds.', RateLimiter::availableIn($limiter))
            );
        }

        $clinicId = (int) ($get('clinic_id') ?: auth()->user()?->clinic_id);

        if ($clinicId === 0) {
            $this->haltWith('No clinic selected', 'Choose the clinic these leads belong to.');
        }

        $upload = collect($get('file'))->first();

        if ($upload === null) {
            $this->haltWith('No file selected', 'Choose a lead export to import.');
        }

        $validator = Validator::make(
            ['clinic_id' => $clinicId, 'file' => $upload],
            StoreLeadImportRequest::sharedRules(),
            StoreLeadImportRequest::sharedMessages(),
        );

        if ($validator->fails()) {
            $this->haltWith('That file cannot be imported', implode(' ', $validator->errors()->all()));
        }

        $disk = (string) config('leads.storage.disk', 'local');
        $directory = trim((string) config('leads.storage.directory', 'lead-imports'), '/') . '/' . $clinicId;
        $originalName = $upload->getClientOriginalName();

        // The stored name is a UUID rather than the original: filenames arrive
        // from an external system and are echoed back on the history screen.
        $storedPath = $upload->storeAs(
            $directory,
            Str::uuid()->toString() . '.' . (pathinfo($originalName, PATHINFO_EXTENSION) ?: 'csv'),
            ['disk' => $disk]
        );

        if ($storedPath === false) {
            $this->haltWith('Upload failed', 'The file could not be saved. Check the storage permissions and try again.');
        }

        RateLimiter::hit($limiter, (int) config('leads.upload.rate_limit.decay_minutes', 60) * 60);

        $service = app(LeadImportService::class);

        try {
            $previous = $service->findPreviousUploadOfSameFile(Storage::disk($disk)->path($storedPath), $clinicId);

            $import = $service->createFromUpload($storedPath, $originalName, $clinicId);
            $analysis = $service->analyze($import);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);

            $this->haltWith('The file could not be read', $exception->getMessage());
        }

        $this->importId = $import->getKey();

        if ($analysis->totalRows === 0) {
            $this->haltWith('The file has no data rows', 'Only a header row was found.');
        }

        if ($analysis->missingRequiredFields !== []) {
            Notification::make()
                ->title('A required column is missing')
                ->body('No column maps to: ' . implode(', ', $analysis->missingRequiredFields) . '. Map one on the next step.')
                ->warning()
                ->persistent()
                ->send();
        }

        if ($previous !== null) {
            Notification::make()
                ->title('This exact file was uploaded before')
                ->body(sprintf(
                    'Imported on %s as "%s". Continuing is safe — duplicate handling decides what happens to repeated rows.',
                    $previous->created_at?->timezone(config('leads.display.timezone'))->format('d M Y, h:i A'),
                    $previous->original_filename,
                ))
                ->warning()
                ->persistent()
                ->send();
        }

        $this->applyProposedMapping($import, $analysis);
    }

    /**
     * Seed the mapping step from the auto-mapper and any matching template.
     */
    protected function applyProposedMapping(LeadImport $import, CsvAnalysisResult $analysis): void
    {
        ['mapping' => $mapping, 'template' => $template] = app(LeadImportService::class)->proposeMapping($import);

        $rows = [];

        // Indexed rather than keyed by header: Livewire treats dots in state
        // keys as path separators, and these headers contain dots, question
        // marks and currency symbols.
        foreach ($analysis->headers as $header) {
            $dto = $mapping[$header] ?? ColumnMappingDto::ignored($header);

            $rows[] = [
                'csv_column' => $header,
                'target' => $dto->target,
                'custom_label' => $dto->customLabel ?? $header,
                'custom_type' => $dto->customType->value,
                'auto_mapped' => $dto->autoMapped,
                'confidence' => $dto->confidence,
                'suggestions' => $dto->suggestions,
            ];
        }

        $state = [
            'mapping' => $rows,
            'settings' => $import->settingsWithDefaults(),
            'duplicate_strategy' => $import->duplicate_strategy?->value ?? 'skip',
            'duplicate_match_fields' => $import->duplicate_match_fields ?: ['fb_lead_id'],
        ];

        if ($template !== null) {
            $state['template_name'] = $template->name;

            Notification::make()
                ->title('Saved mapping applied')
                ->body(sprintf('"%s" matches this file\'s columns and has been applied.', $template->name))
                ->success()
                ->send();
        }

        $this->data = array_merge($this->data ?? [], $state);
    }

    // ────────────────────────────────────────────────────────────────
    // Step 2 — Mapping
    // ────────────────────────────────────────────────────────────────

    protected function mappingStep(): Step
    {
        return Step::make('Mapping')
            ->description('Match columns to CRM fields')
            ->icon('heroicon-o-arrows-right-left')
            ->schema(fn (): array => $this->mappingComponents())
            ->afterValidation(function (Get $get): void {
                $missing = StoreLeadMappingRequest::findUnmappedRequiredFields($get('mapping') ?? []);

                if ($missing !== []) {
                    $this->haltWith(
                        'A required field is not mapped',
                        'Map a column to: ' . implode(', ', $missing) . '. Without it, leads cannot be contacted.'
                    );
                }
            });
    }

    /**
     * Build one mapping row per detected column.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function mappingComponents(): array
    {
        $rows = $this->data['mapping'] ?? [];

        if ($rows === []) {
            return [Text::make('Upload a file to map its columns.')->color('gray')];
        }

        $coreOptions = [];

        foreach (CrmLeadField::groupedOptions() as $group => $options) {
            foreach ($options as $value => $label) {
                $coreOptions[$group][ColumnMappingDto::PREFIX_CORE . $value] = $label;
            }
        }

        $existingCustom = LeadCustomField::query()
            ->where('is_active', true)
            ->when($this->currentImport()?->clinic_id, fn ($query, $clinicId) => $query
                ->where(fn ($inner) => $inner->where('clinic_id', $clinicId)->orWhereNull('clinic_id')))
            ->get();

        $components = [
            Text::make(
                'Every column below will be imported unless you set it to Ignore. '
                . 'Columns marked "auto" were matched automatically — change any that are wrong.'
            )->color('gray'),
        ];

        foreach ($rows as $index => $row) {
            $components[] = $this->mappingRow($index, $row, $coreOptions, $existingCustom);
        }

        return [
            TextInput::make('template_name')
                ->label('Save this mapping as')
                ->placeholder('e.g. AI Facial — Session Recommendation form')
                ->helperText('Optional. Saved mappings are offered automatically the next time this form is exported.')
                ->maxLength(255),

            Section::make('Column mapping')->schema($components),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, string>>  $coreOptions
     * @param  \Illuminate\Support\Collection<int, LeadCustomField>  $existingCustom
     */
    protected function mappingRow(int $index, array $row, array $coreOptions, $existingCustom): Grid
    {
        $header = (string) ($row['csv_column'] ?? '');
        $suggestions = (array) ($row['suggestions'] ?? []);

        $options = $coreOptions;
        $options['Custom questions'] = [ColumnMappingDto::PREFIX_CUSTOM . $this->newFieldToken($header) => '➕ New custom question'];

        // Existing questions are listed so two differently-worded versions of
        // the same question can be folded into one field, which is exactly what
        // "what is your main skin concern?" and "...right now?" require.
        foreach ($existingCustom as $field) {
            $options['Custom questions'][ColumnMappingDto::PREFIX_CUSTOM . $field->key] = Str::limit($field->display_label, 70);
        }

        $options['Skip'] = [ColumnMappingDto::TARGET_IGNORE => '🚫 Ignore this column'];

        $isCustom = fn (Get $get): bool => str_starts_with(
            (string) $get("mapping.{$index}.target"),
            ColumnMappingDto::PREFIX_CUSTOM
        );

        return Grid::make(['default' => 1, 'lg' => 12])
            ->schema([
                // Text renders a single content value, so the column name and
                // its hint are composed into one escaped fragment rather than
                // being set as a separate description.
                Text::make(fn (): HtmlString => $this->mappingRowLabel($header, $row, $suggestions))
                    ->columnSpan(['default' => 1, 'lg' => 5]),

                Select::make("mapping.{$index}.target")
                    ->label('Import as')
                    ->hiddenLabel()
                    ->options($options)
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->live()
                    ->columnSpan(['default' => 1, 'lg' => 7]),

                // The label and type controls sit on their own row rather than
                // being squeezed into the first one. Sharing a single 12-column
                // row left the type select about sixty pixels wide, which broke
                // "Single Choice" across four lines.
                Grid::make(['default' => 1, 'lg' => 12])
                    ->schema([
                        TextInput::make("mapping.{$index}.custom_label")
                            ->label('Question label')
                            ->placeholder($header)
                            ->helperText('Shown throughout the CRM. Leave as-is to keep the wording from the file.')
                            ->maxLength(1000)
                            ->columnSpan(['default' => 1, 'lg' => 8]),

                        Select::make("mapping.{$index}.custom_type")
                            ->label('Answer type')
                            ->options(LeadFieldType::options())
                            ->native(false)
                            ->helperText('Single or multiple choice makes the answers filterable.')
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                    ])
                    ->visible($isCustom)
                    ->columnSpanFull(),

                Hidden::make("mapping.{$index}.csv_column"),
                Hidden::make("mapping.{$index}.auto_mapped"),
                Hidden::make("mapping.{$index}.confidence"),
            ])
            // A hairline between rows keeps eighteen columns readable as a
            // list. Styled inline for the same reason as the Blade views: the
            // panel serves a pre-built theme that has no class for this.
            ->extraAttributes([
                'style' => 'padding-block:.75rem; border-bottom:1px solid color-mix(in srgb, currentColor 12%, transparent);',
            ]);
    }

    /**
     * Compose a column's name and its hint into one rendered fragment.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $suggestions
     */
    protected function mappingRowLabel(string $header, array $row, array $suggestions): HtmlString
    {
        $hint = $this->mappingRowHint($row, $suggestions);

        // Styled inline rather than with utility classes: the panel serves a
        // pre-built vendor theme, so a class this application does not already
        // use elsewhere is simply absent from the compiled stylesheet.
        // The header text originates in an uploaded file, so it is escaped even
        // though it is only ever shown to the person who uploaded it.
        $html = '<span style="font-weight:500; word-break:break-word;">' . e(Str::limit($header, 90)) . '</span>';

        if ($hint !== null) {
            $html .= '<span style="display:block; margin-top:.15rem; font-size:.75rem; opacity:.62; word-break:break-word;">'
                . e($hint) . '</span>';
        }

        return new HtmlString($html);
    }

    /**
     * The helper line under a column name: sample values, and a nudge when the
     * column looks like a question the CRM already tracks.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $suggestions
     */
    protected function mappingRowHint(array $row, array $suggestions): ?string
    {
        $parts = [];

        $analysis = $this->analysis();
        $samples = $analysis?->distinctValues[$row['csv_column']] ?? [];

        if ($samples !== []) {
            $parts[] = 'e.g. ' . Str::limit(implode(' · ', array_slice($samples, 0, 3)), 80);
        }

        if ($suggestions !== []) {
            $best = $suggestions[0];
            $parts[] = sprintf('Similar to existing question "%s" (%s%% match)', Str::limit($best['label'], 45), $best['score']);
        }

        if (! empty($row['auto_mapped']) && ($row['confidence'] ?? 0) >= 100) {
            array_unshift($parts, 'auto-mapped');
        }

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /**
     * A stable token for "create a new custom field from this header".
     */
    protected function newFieldToken(string $header): string
    {
        return LeadCustomField::makeKey($header);
    }

    // ────────────────────────────────────────────────────────────────
    // Step 3 — Settings and duplicates
    // ────────────────────────────────────────────────────────────────

    protected function settingsStep(): Step
    {
        $descriptors = ImportSettingsDto::descriptors();

        $toggles = [];

        foreach ($descriptors as $key => $descriptor) {
            $toggles[] = Toggle::make("settings.{$key}")
                ->label($descriptor['label'])
                ->helperText($descriptor['help'])
                ->inline(false);
        }

        return Step::make('Settings')
            ->description('Cleaning rules and duplicate handling')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Section::make('Cleaning')
                    ->description('Applied to every row as it is read.')
                    ->schema($toggles)
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3]),

                Section::make('Duplicate detection')
                    ->schema([
                        CheckboxList::make('duplicate_match_fields')
                            ->label('Treat rows as duplicates when these match')
                            ->options(fn (): array => collect(CrmLeadField::duplicateMatchFields())
                                ->mapWithKeys(fn (CrmLeadField $field): array => [$field->value => $field->getLabel()])
                                ->all())
                            ->descriptions([
                                CrmLeadField::FbLeadId->value => "Facebook's own lead ID is unique per submission and is the most reliable match.",
                                CrmLeadField::Phone->value => 'Catches the same person submitting more than one form, but a shared family number will match too.',
                                CrmLeadField::Email->value => 'Only useful for files that actually contain an email column.',
                            ])
                            ->required()
                            ->columns(1),

                        Radio::make('duplicate_strategy')
                            ->label('When a duplicate is found')
                            ->options(DuplicateStrategy::options())
                            ->descriptions(DuplicateStrategy::descriptions())
                            ->required(),
                    ])
                    ->columns(['default' => 1, 'lg' => 2]),
            ])
            ->afterValidation(function (): void {
                $this->persistConfiguration();
            });
    }

    // ────────────────────────────────────────────────────────────────
    // Step 4 — Preview
    // ────────────────────────────────────────────────────────────────

    protected function previewStep(): Step
    {
        return Step::make('Preview')
            ->description('Check the result before importing')
            ->icon('heroicon-o-eye')
            ->schema([
                View::make('filament.lead.import-preview')
                    ->viewData(fn (): array => $this->previewData()),

                Checkbox::make('confirmed')
                    ->label('I have checked the preview above and want to import these leads')
                    ->accepted()
                    ->required()
                    ->validationMessages(['accepted' => 'Confirm the preview before importing.']),
            ]);
    }

    /**
     * Build the preview payload: statistics plus a raw-versus-mapped sample.
     *
     * @return array<string, mixed>
     */
    public function previewData(): array
    {
        $import = $this->currentImport();
        $analysis = $this->analysis();

        if ($import === null || $analysis === null) {
            return ['import' => null, 'analysis' => null, 'rows' => [], 'coreColumns' => [], 'customColumns' => []];
        }

        $this->persistConfiguration();

        $importer = app(LeadRowImporterService::class)->prepare($import->refresh());

        $rows = [];

        foreach (array_slice($analysis->sampleRows, 0, (int) config('leads.csv.preview_rows', 20)) as $index => $rawRow) {
            $mapped = $importer->mapRow(
                ImportSettingsDto::fromArray($import->settings ?? [])->stripMetaPrefixes
                    ? app(\App\Services\Lead\MetaPrefixStripper::class)->stripRow($rawRow)
                    : $rawRow
            );

            $rows[] = [
                'number' => $index + 1,
                'raw' => $rawRow,
                'attributes' => $mapped['attributes'],
                'custom' => $mapped['custom'],
                'phone' => $mapped['phone'],
            ];
        }

        $coreColumns = [];
        $customColumns = [];
        $hiddenCoreColumns = [];

        foreach ($this->data['mapping'] ?? [] as $row) {
            $target = (string) ($row['target'] ?? '');

            if (str_starts_with($target, ColumnMappingDto::PREFIX_CORE)) {
                $field = CrmLeadField::tryFrom(substr($target, strlen(ColumnMappingDto::PREFIX_CORE)));

                if ($field === null) {
                    continue;
                }

                // Meta's opaque numeric IDs are imported but never previewed:
                // showing eight of them turned the table into a wall of digits
                // and pushed the columns a human actually checks off-screen.
                if (in_array($field, self::PREVIEW_HIDDEN_FIELDS, true)) {
                    $hiddenCoreColumns[$field->value] = $field->getLabel();

                    continue;
                }

                $coreColumns[$field->value] = $field->getLabel();
            } elseif (str_starts_with($target, ColumnMappingDto::PREFIX_CUSTOM)) {
                $customColumns[] = (string) ($row['custom_label'] ?: $row['csv_column']);
            }
        }

        // Order the visible columns by how useful they are for spotting a bad
        // mapping, rather than by their position in the file.
        $ordered = [];

        foreach (self::PREVIEW_FIELD_ORDER as $field) {
            if (isset($coreColumns[$field->value])) {
                $ordered[$field->value] = $coreColumns[$field->value];
            }
        }

        return [
            'import' => $import,
            'analysis' => $analysis,
            'rows' => $rows,
            'coreColumns' => $ordered + $coreColumns,
            'customColumns' => $customColumns,
            'hiddenCoreColumns' => $hiddenCoreColumns,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Submission
    // ────────────────────────────────────────────────────────────────

    public function startImport(): void
    {
        $this->form->validate();

        $import = $this->currentImport();

        if ($import === null) {
            $this->haltWith('Nothing to import', 'Upload a file first.');
        }

        $this->persistConfiguration();

        $templateName = trim((string) ($this->data['template_name'] ?? ''));

        if ($templateName !== '') {
            app(MappingTemplateService::class)->save(
                name: $templateName,
                headers: $import->detected_headers ?? [],
                mapping: $this->mappingDtos(),
                import: $import->refresh(),
            );
        }

        app(LeadImportService::class)->dispatchImport($import->refresh());

        Notification::make()
            ->title('Import started')
            ->body(sprintf('%s is being imported in the background. Progress is shown on the import history.', $import->original_filename))
            ->success()
            ->send();

        $this->redirect(LeadImportResource::getUrl('view', ['record' => $import->getKey()]));
    }

    /**
     * Write the current mapping, settings and duplicate rules onto the import.
     *
     * Called on every step transition so a user who abandons the wizard can
     * resume from the history screen rather than starting again.
     */
    protected function persistConfiguration(): void
    {
        $import = $this->currentImport();

        if ($import === null) {
            return;
        }

        app(LeadImportService::class)->confirmMapping(
            import: $import,
            mapping: $this->mappingDtos(),
            settings: ImportSettingsDto::fromArray($this->data['settings'] ?? []),
            strategy: DuplicateStrategy::tryFrom((string) ($this->data['duplicate_strategy'] ?? 'skip')) ?? DuplicateStrategy::Skip,
            matchFields: (array) ($this->data['duplicate_match_fields'] ?? ['fb_lead_id']),
        );
    }

    /**
     * @return array<string, ColumnMappingDto>
     */
    protected function mappingDtos(): array
    {
        $mapping = [];

        foreach ($this->data['mapping'] ?? [] as $row) {
            $dto = ColumnMappingDto::fromArray($row);
            $mapping[$dto->csvColumn] = $dto;
        }

        return $mapping;
    }

    public function currentImport(): ?LeadImport
    {
        return $this->importId === null ? null : LeadImport::find($this->importId);
    }

    protected function analysis(): ?CsvAnalysisResult
    {
        $import = $this->currentImport();

        return $import?->analysis === null ? null : CsvAnalysisResult::fromArray($import->analysis);
    }

    /**
     * Show an error and stop the wizard advancing.
     */
    protected function haltWith(string $title, string $body): never
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->persistent()
            ->send();

        throw new \Filament\Support\Exceptions\Halt();
    }
}
