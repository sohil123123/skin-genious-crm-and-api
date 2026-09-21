<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Invoice;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserPackageUsage;
use App\Support\Pdf\StructuredDataHtml;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client report PDFs: packages, assessments, sessions and invoices, each on
 * its own or all four together in one document.
 */
class UserReportPdfService
{
    public const SECTIONS = [
        'packages' => 'Packages',
        'assessments' => 'Assessments',
        'sessions' => 'Treatment Sessions',
        'invoices' => 'Invoices',
    ];

    /** Longest side of an embedded photo, in pixels. */
    private const THUMB_SIZE = 520;

    /** Photos per assessment / session, so a report never balloons. */
    public const MAX_PHOTOS = 8;

    /**
     * Keys in `pigmentation_inputs` that repeat what the assessment already
     * stores in its own columns (diagnosis, recommended_full_plan) or that are
     * the questionnaire definition rather than the answers.
     */
    public const PIGMENTATION_DUPLICATE_KEYS = ['diagnosis', 'lastPlan', 'aiAnalysis', 'dynamicQuestions', 'reassessQuestions'];

    /** @var list<string> */
    private array $tempFiles = [];

    /** The User relation behind each section, for counting its records. */
    public const SECTION_RELATIONS = [
        'packages' => 'packages',
        'assessments' => 'assessments',
        'sessions' => 'treatmentSessions',
        'invoices' => 'invoices',
    ];

    public static function isValidSection(string $section): bool
    {
        return $section === 'all' || array_key_exists($section, self::SECTIONS);
    }

    /**
     * `withCount()` arguments for loading every section's record count with
     * a list of users, so hasData() doesn't query per row.
     *
     * @return list<string>
     */
    public static function countRelations(): array
    {
        return array_values(self::SECTION_RELATIONS);
    }

    /**
     * Whether a client has anything to show in a section ('all': in any).
     */
    public static function hasData(User $user, string $section): bool
    {
        if ($section === 'all') {
            return collect(array_keys(self::SECTIONS))->contains(fn(string $name) => self::hasData($user, $name));
        }

        $relation = self::SECTION_RELATIONS[$section];
        $counted = Str::snake($relation) . '_count';

        $count = array_key_exists($counted, $user->getAttributes())
            ? $user->getAttribute($counted)
            : $user->{$relation}()->count();

        return (int) $count > 0;
    }

    public function download(User $user, string $section = 'all'): Response
    {
        $pdf = $this->generate($user, $section);

        $filename = Str::slug($user->name ?: 'client', '_')
            . '_' . ($section === 'all' ? 'complete_report' : $section)
            . '_' . now()->format('Ymd_His') . '.pdf';

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function generate(User $user, string $section = 'all'): string
    {
        if (! self::isValidSection($section)) {
            throw new \InvalidArgumentException("Unknown report section [{$section}].");
        }

        // Assessments carry tens of kilobytes of JSON each; a full report
        // for a regular client takes a while and a lot of regex backtracking.
        @set_time_limit(300);
        @ini_set('pcre.backtrack_limit', '20000000');

        $sections = $section === 'all' ? array_keys(self::SECTIONS) : [$section];
        $user->loadMissing('clinic');

        $data = [
            'report' => $this,
            'user' => $user,
            'clinic' => $user->clinic,
            'logo' => $this->logoPath($user),
            'section' => $section,
            'sections' => $sections,
            'title' => $section === 'all' ? 'Complete Client Report' : self::SECTIONS[$section] . ' Report',
            'generatedAt' => now(),
            'generatedBy' => auth()->user()?->name,
        ];

        foreach ($sections as $name) {
            $data += $this->{'load' . Str::studly($name)}($user);
        }

        // The complete report leaves out sections the client has nothing in;
        // a single-section report still says so on its own page.
        if ($section === 'all') {
            $data['sections'] = $sections = array_values(array_filter(
                $sections,
                fn(string $name) => $data[$name]->isNotEmpty(),
            ));

            foreach (array_diff(array_keys(self::SECTIONS), $sections) as $empty) {
                unset($data[$empty]);
            }
        }

        $data['summary'] = $this->summary($data);

        $mpdf = $this->makeMpdf();
        $mpdf->SetTitle($data['title'] . ' - ' . $user->name);
        $mpdf->SetAuthor($user->clinic?->name ?? config('app.name'));

        try {
            $mpdf->WriteHTML(view('pdf.user-report.styles')->render(), HTMLParserMode::HEADER_CSS);
            $mpdf->WriteHTML(view('pdf.user-report.cover', $data)->render(), HTMLParserMode::HTML_BODY);

            // Written one record at a time: a single WriteHTML call for a
            // whole report can exceed PCRE limits and is much slower.
            foreach ($sections as $name) {
                foreach ($this->{Str::camel($name) . 'Chunks'}($data) as $html) {
                    $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                }
            }

            return $mpdf->Output('', 'S');
        } finally {
            foreach ($this->tempFiles as $file) {
                @unlink($file);
            }

            $this->tempFiles = [];
        }
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    private function loadPackages(User $user): array
    {
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->with([
                'clinic',
                'createdBy',
                'items.service',
                'items.usages' => fn($q) => $q->latest(),
                'items.usages.appointment.therapist',
                'items.usages.invoice',
                'items.usages.recordedBy',
                'invoices' => fn($q) => $q->orderBy('invoice_date'),
            ])
            ->latest()
            ->get();

        return ['packages' => $packages];
    }

    private function loadAssessments(User $user): array
    {
        $assessments = Assessment::query()
            ->where('user_id', $user->id)
            ->with(['clinic', 'createdBy', 'parentAssessment', 'media', 'treatmentSessions'])
            ->latest()
            ->get();

        return ['assessments' => $assessments];
    }

    private function loadSessions(User $user): array
    {
        $sessions = TreatmentSession::query()
            ->where('user_id', $user->id)
            ->with(['assessment', 'media', 'ivSession.bags', 'ivSession.ingredients'])
            ->orderByDesc('assessment_id')
            ->orderBy('session_number')
            ->get();

        $appointments = Appointment::query()
            ->where('user_id', $user->id)
            ->whereIn('treatment_session_id', $sessions->pluck('id'))
            ->with('therapist')
            ->orderBy('start_datetime')
            ->get()
            ->groupBy('treatment_session_id');

        $usages = UserPackageUsage::query()
            ->whereIn('user_package_id', UserPackage::query()->where('user_id', $user->id)->select('id'))
            ->with(['packageItem.package', 'packageItem.service', 'appointment.therapist', 'invoice', 'recordedBy'])
            ->latest()
            ->get();

        return [
            'sessions' => $sessions,
            'sessionAppointments' => $appointments,
            'packageUsages' => $usages,
        ];
    }

    private function loadInvoices(User $user): array
    {
        $invoices = Invoice::query()
            ->where('user_id', $user->id)
            ->with(['clinic', 'package', 'items.product', 'payments' => fn($q) => $q->orderBy('payment_date'), 'payments.creator'])
            ->orderByDesc('invoice_date')
            ->get();

        return ['invoices' => $invoices];
    }

    private function summary(array $data): array
    {
        $summary = [];

        if (isset($data['packages'])) {
            /** @var Collection $packages */
            $packages = $data['packages'];
            $paid = $packages->sum(fn(UserPackage $p) => (float) $p->invoices->sum('amount_paid'));
            $value = (float) $packages->sum('final_amount');

            $summary['packages'] = [
                'count' => $packages->count(),
                'active' => $packages->filter(fn(UserPackage $p) => $this->packageStatus($p)['label'] === 'Active')->count(),
                'value' => $value,
                'paid' => $paid,
                'outstanding' => max(0, $value - $paid),
                'sessions_total' => $packages->sum(fn(UserPackage $p) => $p->items->sum('quantity')),
                'sessions_used' => $packages->sum(fn(UserPackage $p) => $p->items->sum('used_sessions')),
            ];
        }

        if (isset($data['assessments'])) {
            $assessments = $data['assessments'];

            $summary['assessments'] = [
                'count' => $assessments->count(),
                'completed' => $assessments->filter(fn($a) => $this->enumValue($a->status) === 'completed')->count(),
                'by_type' => $assessments->groupBy(fn($a) => $this->assessmentTypeLabel($a->assessment_type))->map->count()->all(),
                'latest' => $assessments->first()?->created_at,
            ];
        }

        if (isset($data['sessions'])) {
            $sessions = $data['sessions'];

            $summary['sessions'] = [
                'count' => $sessions->count(),
                'completed' => $sessions->where('status', 'completed')->count(),
                'pending' => $sessions->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count(),
                'minutes' => (int) $sessions->sum(fn($s) => (int) $s->treatment_time),
                'package_sessions_used' => (int) $data['packageUsages']->sum('sessions_used'),
            ];
        }

        if (isset($data['invoices'])) {
            $invoices = $data['invoices']->where('status', '!=', 'cancelled');

            $summary['invoices'] = [
                'count' => $data['invoices']->count(),
                'total' => (float) $invoices->sum('grand_total'),
                'paid' => (float) $invoices->sum('amount_paid'),
                'due' => (float) $invoices->sum('amount_due'),
                'gst' => (float) $invoices->sum('gst_total'),
            ];
        }

        return $summary;
    }

    // ------------------------------------------------------------------
    // Chunks (one per record, see generate())
    // ------------------------------------------------------------------

    private function packagesChunks(array $data): iterable
    {
        yield view('pdf.user-report.packages.intro', $data)->render();

        foreach ($data['packages'] as $index => $package) {
            yield $this->keepRecord(view('pdf.user-report.packages.item', $data + [
                'package' => $package,
                'index' => $index,
                'status' => $this->packageStatus($package),
            ])->render());
        }
    }

    private function assessmentsChunks(array $data): iterable
    {
        yield view('pdf.user-report.assessments.intro', $data)->render();

        foreach ($data['assessments'] as $index => $assessment) {
            yield $this->keepRecord(view('pdf.user-report.assessments.item', $data + [
                'assessment' => $assessment,
                'index' => $index,
                'preImages' => $this->photos($assessment, $assessment->assessment_type === 'pigmentation'
                    ? 'pigmentation_pre_assessment_images'
                    : 'assessment_images'),
                'postImages' => $this->photos($assessment, $assessment->assessment_type === 'pigmentation'
                    ? 'pigmentation_post_assessment_images'
                    : 'post_assessment_images'),
                'clinical' => $this->assessmentClinicalSections($assessment),
            ])->render());
        }
    }

    private function sessionsChunks(array $data): iterable
    {
        yield view('pdf.user-report.sessions.intro', $data)->render();

        foreach ($data['sessions'] as $index => $session) {
            $images = $this->photos($session, 'post_treatment_images');

            yield $this->keepRecord(view('pdf.user-report.sessions.item', $data + [
                'session' => $session,
                'index' => $index,
                'appointments' => $data['sessionAppointments']->get($session->id, collect()),
                'postImages' => $images !== [] ? $images : $this->photos($session, 'user_post_assessment_images'),
                'ivSession' => $this->ivSessionData($session),
            ])->render());
        }

        yield view('pdf.user-report.sessions.usages', $data)->render();
    }

    private function invoicesChunks(array $data): iterable
    {
        yield view('pdf.user-report.invoices.intro', $data)->render();

        foreach ($data['invoices'] as $index => $invoice) {
            yield $this->keepRecord(view('pdf.user-report.invoices.item', $data + [
                'invoice' => $invoice,
                'index' => $index,
            ])->render());
        }
    }

    /**
     * A record card (one package, invoice…) that fits on a page is kept
     * whole: moved to the next page rather than cut. A taller one flows
     * without its outer border — mPDF draws a rounded border broken across
     * pages — while its sections still keep themselves together.
     */
    private function keepRecord(string $html): string
    {
        $start = strpos($html, '<div class="record"');

        if ($start === false) {
            return $html;
        }

        // Find the card's matching </div>.
        preg_match_all('#<div|</div>#', $html, $tags, PREG_OFFSET_CAPTURE, $start);
        $depth = 0;
        $end = strlen($html);

        foreach ($tags[0] as [$tag, $offset]) {
            $depth += $tag === '</div>' ? -1 : 1;

            if ($depth === 0) {
                $end = $offset + strlen('</div>');
                break;
            }
        }

        $record = substr($html, $start, $end - $start);

        $record = StructuredDataHtml::fitsOnPage($record)
            ? '<div style="page-break-inside: avoid;">' . StructuredDataHtml::withoutKeeps($record) . '</div>'
            : preg_replace('#^<div class="record"#', '<div class="record record-flow"', $record);

        return substr_replace($html, $record, $start, $end - $start);
    }

    // ------------------------------------------------------------------
    // Helpers shared with the views
    // ------------------------------------------------------------------

    /**
     * @return array{label: string, color: string}
     */
    public function packageStatus(UserPackage $package): array
    {
        return match (true) {
            $package->items->isNotEmpty() && $package->isExhausted() => ['label' => 'Completed', 'color' => 'blue'],
            $package->isExpired() => ['label' => 'Expired', 'color' => 'red'],
            ! $package->is_active => ['label' => 'Inactive', 'color' => 'gray'],
            default => ['label' => 'Active', 'color' => 'green'],
        };
    }

    public static function assessmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'normal' => 'Facial',
            'instant-normal' => 'Instant Facial',
            'iv' => 'IV Therapy',
            'instant-iv' => 'Instant IV',
            'pigmentation' => 'Pigmentation',
            null, '' => 'General',
            default => Str::headline($type),
        };
    }

    public static function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value);
    }

    public static function enumLabel(mixed $value): string
    {
        if ($value instanceof \Filament\Support\Contracts\HasLabel) {
            return (string) $value->getLabel();
        }

        return $value === null || $value === '' ? '—' : Str::headline((string) self::enumValue($value));
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'completed', 'paid', 'active' => 'green',
            'in_progress', 'partial', 'scheduled', 'confirmed' => 'amber',
            'cancelled', 'unpaid', 'overdue', 'incomplete', 'no_show' => 'red',
            default => 'gray',
        };
    }

    /**
     * Treatment times are stored both as "60" and as "60 mins".
     */
    public static function minutes(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return is_numeric($value) ? $value . ' min' : (string) $value;
    }

    /**
     * A small rounded progress bar as an inline SVG image. mPDF ignores the
     * width of a div inside a table cell, and a table nested there breaks
     * keep-together blocks.
     */
    public static function progressBar(int $percent, string $color = '#10b981'): string
    {
        $fill = max(0, min(100, $percent));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="6">'
            . '<rect width="100" height="6" rx="3" fill="#e5e7eb"/>'
            . ($fill > 0 ? '<rect width="' . max($fill, 6) . '" height="6" rx="3" fill="' . $color . '"/>' : '')
            . '</svg>';

        return '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" style="width: 22mm; height: 1.4mm;">';
    }

    public static function money(mixed $amount): string
    {
        return '₹' . number_format((float) $amount, 2);
    }

    /**
     * The clinical JSON of an assessment, titled, in reading order.
     *
     * @return array<string, mixed>
     */
    private function assessmentClinicalSections(Assessment $assessment): array
    {
        $pigmentation = $assessment->pigmentation_inputs;

        if (is_array($pigmentation)) {
            $pigmentation = array_diff_key($pigmentation, array_flip(self::PIGMENTATION_DUPLICATE_KEYS));
        }

        return array_filter([
            'Diagnosis' => $assessment->diagnosis,
            'Parameters With Abnormal Scores' => $assessment->parameters_with_abnormal_scores,
            'Recommended Treatment Plan' => $assessment->recommended_full_plan,
            'Post-Treatment Diagnosis' => $assessment->post_diagnosis,
            'IV Inputs' => $assessment->iv_inputs,
            'IV Treatment Plan' => $assessment->iv_treatment_plan,
            'IV Selected Option' => $assessment->iv_selected_option,
            'Nurse Run Sheet' => $assessment->getAttribute('nurse_run_sheet'),
            'Pigmentation Inputs' => $pigmentation,
            'Skin Scan Metrics' => $assessment->feature_packet,
        ], fn($value) => ! \App\Support\Pdf\StructuredDataHtml::isEmpty($value));
    }

    private function ivSessionData(TreatmentSession $session): ?array
    {
        $iv = $session->ivSession;

        if (! $iv) {
            return null;
        }

        $hidden = ['id', 'iv_session_id', 'iv_session_bag_id', 'treatment_session_id', 'assessment_id', 'user_id', 'created_at', 'updated_at'];
        $strip = fn(array $row) => array_diff_key($row, array_flip($hidden));

        return array_filter([
            'Selected option' => $iv->selected_option_type,
            'Protocol' => $iv->selected_protocol_id,
            'Plan week' => $iv->plan_week_index,
            'Status' => $iv->status,
            'Bags' => $iv->bags->map(fn($bag) => $strip($bag->toArray()))->all(),
            'Ingredients' => $iv->ingredients->map(fn($ingredient) => $strip($ingredient->toArray()))->all(),
        ], fn($value) => ! \App\Support\Pdf\StructuredDataHtml::isEmpty($value));
    }

    /**
     * Downscaled local copies of a record's photos: the originals run to
     * several megabytes each.
     *
     * @return list<array{path: string, name: string}>
     */
    private function photos(\Spatie\MediaLibrary\HasMedia $model, string $collection): array
    {
        return $model->getMedia($collection)
            ->take(self::MAX_PHOTOS)
            ->map(function (Media $media) {
                $path = $this->thumbnail($media);

                return $path ? [
                    'path' => $path,
                    'name' => Str::headline(pathinfo($media->file_name, PATHINFO_FILENAME)),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function thumbnail(Media $media): ?string
    {
        try {
            $source = $media->getPath();
        } catch (\Throwable) {
            return null;
        }

        if (! is_file($source) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($source));

        if (! $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::THUMB_SIZE / max($width, $height));
        $thumb = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagedestroy($image);

        if (! $thumb) {
            return null;
        }

        $target = $this->tempDir() . '/user_report_' . Str::random(16) . '.jpg';
        imagejpeg($thumb, $target, 78);
        imagedestroy($thumb);

        $this->tempFiles[] = $target;

        return $target;
    }

    private function logoPath(User $user): ?string
    {
        $logo = $user->clinic?->logo;

        if ($logo) {
            foreach ([Storage::disk('public')->path($logo), public_path($logo)] as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        $fallback = public_path('images/AI-aesthetics-logo.png');

        return is_file($fallback) ? $fallback : null;
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/public/tmp');

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function makeMpdf(): Mpdf
    {
        $base = config('project.mpdf_config', []);

        $mpdf = new Mpdf(array_merge($base, [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 24,
            'margin_bottom' => 18,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir' => $this->tempDir(),
        ]));

        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->useSubstitutions = true;

        return $mpdf;
    }
}
