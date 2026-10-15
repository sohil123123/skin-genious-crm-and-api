<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Copies the old Lumen CRM (clinic-management) into this database.
 *
 * Production safety rules this class is built around:
 *
 *  - It only INSERTS. Existing rows are never updated or deleted, with two
 *    deliberate exceptions: users.loyalty_points is incremented by the legacy
 *    opening balance (step "loyalty"), users.referred_by / total_referrals
 *    are filled on users this import created itself (step "referrals"), and
 *    state_invoice_sequences.last_number is moved past the block of INV numbers
 *    reserved for old invoices (step "invoices").
 *  - It never goes through Eloquent. Invoice, InvoiceItem, InvoicePayment,
 *    Appointment, Product and Purchase all have hooks that decrement stock,
 *    award loyalty points, take INV-{STATE} numbers one by one, queue WhatsApp
 *    messages or create expenses. Historic data must trigger none of that.
 *  - Every inserted / matched row is written to legacy_import_map in the same
 *    transaction as the row itself, so a re-run skips what is already done and
 *    `legacy:rollback` knows exactly which rows belong to the import.
 */
class LegacyCrmImporter
{
    public const STEPS = ['clinics', 'staff', 'clients', 'referrals', 'products', 'stock', 'invoices', 'appointments', 'loyalty'];

    // Old entity_options ids -> new values.
    private const GENDER = [4 => 'Male', 5 => 'Female', 6 => 'Other'];
    private const SOURCE = [7 => 'Skin Genius', 8 => 'Friend Referral', 9 => 'Google Search', 10 => 'Practo/Lybrate', 11 => 'By Doctor'];
    private const SKIN_TYPE = [12 => 'Dry', 13 => 'Oily', 14 => 'Combination', 15 => 'Sensitive'];
    // New enum has no "Very Good"; it is folded into "Good" and reported.
    private const SKIN_QUALITY = [16 => 'Poor', 17 => 'Fair', 18 => 'Good', 19 => 'Good', 20 => 'Excellent'];
    private const FACIALS = [21 => 'No', 22 => 'Once In A Year', 23 => 'Once In Six Months', 24 => 'Once In Three Months', 25 => 'Once In A Month'];
    private const SKIN_IMPROVEMENT = [68 => 'Hydration', 69 => 'Smoothness', 70 => 'Elasticity'];
    private const PRODUCT_TYPE = [28 => 'product', 29 => 'service', 30 => 'service'];
    private const PAYMENT_METHOD = [31 => 'cash', 32 => 'card', 33 => 'other', 34 => 'bank_transfer', 35 => 'other', 36 => 'other', 37 => 'loyalty_points'];
    private const PAYMENT_METHOD_LABEL = [31 => 'Cash', 32 => 'Credit Card', 33 => 'Cheque', 34 => 'Online Banking', 35 => 'e Wallet', 36 => 'Settlement', 37 => 'Loyalty Points'];
    private const TAX_TYPE_OUTWARD = 27;
    private const ESTATE_TYPE_CLINIC = 3;

    private const STATE_CODES = [
        'andaman and nicobar islands' => 'AN', 'andhra pradesh' => 'AP', 'arunachal pradesh' => 'AR', 'assam' => 'AS',
        'bihar' => 'BR', 'chandigarh' => 'CH', 'chhattisgarh' => 'CG', 'dadra and nagar haveli' => 'DN',
        'daman and diu' => 'DD', 'delhi' => 'DL', 'goa' => 'GA', 'gujarat' => 'GJ', 'haryana' => 'HR',
        'himachal pradesh' => 'HP', 'jammu and kashmir' => 'JK', 'jharkhand' => 'JH', 'karnataka' => 'KA',
        'kerala' => 'KL', 'ladakh' => 'LA', 'lakshadweep' => 'LD', 'madhya pradesh' => 'MP', 'maharashtra' => 'MH',
        'manipur' => 'MN', 'meghalaya' => 'ML', 'mizoram' => 'MZ', 'nagaland' => 'NL', 'odisha' => 'OD',
        'orissa' => 'OD', 'puducherry' => 'PY', 'pondicherry' => 'PY', 'punjab' => 'PB', 'rajasthan' => 'RJ',
        'sikkim' => 'SK', 'tamil nadu' => 'TN', 'telangana' => 'TS', 'tripura' => 'TR', 'uttar pradesh' => 'UP',
        'uttarakhand' => 'UK', 'uttaranchal' => 'UK', 'west bengal' => 'WB',
    ];

    private Connection $old;
    private Connection $db;

    /** @var array<string, array<int, int|null>> entity => [old_id => new_id] */
    private array $map = [];
    /** @var array<string, array<string, int>> */
    private array $stats = [];
    /** @var list<array{step: string, old_id: int|string, issue: string}> */
    private array $issues = [];

    /** @var array<string, int> last-10-digit mobile key => users.id */
    private array $mobileIndex = [];
    /** @var array<string, int> lowercase email => users.id */
    private array $emailIndex = [];
    /** @var array<string, true> */
    private array $referralIndex = [];
    /** @var array<int, string|null> new clinic id => GST state code */
    private array $clinicState = [];
    /** @var array<string, int> role name => roles.id */
    private array $roleIds = [];

    private int $fallbackUserId;
    private string $now;

    /** @var list<int>|null old estate ids in scope; null = every clinic */
    private ?array $estates;
    /** @var array{client: array<int, true>, staff: array<int, true>, product: array<int, true>}|null */
    private ?array $scope = null;

    /**
     * @param  array{dry_run: bool, clinic_map: array<int,int>, create_missing_clinics: bool, activate_staff: bool,
     *               include_deleted_sales: bool, include_future_appointments: bool, fallback_user_id: ?int,
     *               loyalty_source: string, chunk: int, retry_skipped: bool, only_estates: ?list<int>,
     *               include_deleted_product_stock: bool, renumber_existing_invoices: bool}  $options
     * @param  Closure(string, string): void  $log  (level, message)
     */
    public function __construct(private array $options, private Closure $log)
    {
        $this->old = DB::connection(config('legacy_import.connection'));
        $this->db = DB::connection();
        $this->now = now()->toDateTimeString();
        $this->estates = $options['only_estates'] ? array_values(array_map('intval', $options['only_estates'])) : null;
    }

    // ------------------------------------------------------------------
    //  Preflight
    // ------------------------------------------------------------------

    /**
     * Read-only checks. Throws before anything is written if the setup is unsafe.
     */
    public function preflight(): void
    {
        $oldName = $this->old->getDatabaseName();
        $newName = $this->db->getDatabaseName();
        $sameHost = $this->old->getConfig('host') === $this->db->getConfig('host')
            && (string) $this->old->getConfig('port') === (string) $this->db->getConfig('port');

        if ($sameHost && $oldName === $newName) {
            throw new RuntimeException("The legacy connection points at this CRM's own database ({$newName}). Restore the old dump into a separate database.");
        }
        foreach (['estates', 'clinics', 'clients', 'sales', 'sale_products', 'sale_receipts', 'points_ledgers', 'entity_options'] as $table) {
            if (! $this->old->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("Legacy database `{$oldName}` has no `{$table}` table — is the dump restored there?");
            }
        }
        // The old dump DROPs and recreates users/clinics/products/... — if it was ever
        // restored into this database, `estates` would exist here. Refuse to continue.
        if (Schema::hasTable('estates') || Schema::hasTable('entity_options')) {
            throw new RuntimeException("This CRM database contains old-CRM tables (estates / entity_options). The old dump may have been restored into it — STOP and check the data before going further.");
        }
        if (! Schema::hasTable('legacy_import_map')) {
            throw new RuntimeException('Table legacy_import_map is missing. Run `php artisan migrate` first (it only adds that table).');
        }

        $this->assertColumns('users', ['clinic_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'mobile', 'email', 'state', 'referral_code', 'referred_by', 'opt_for_loyalty', 'how_did_you_hear', 'loyalty_points', 'skin_type', 'skin_quality', 'skin_improvement', 'facials_history', 'password', 'is_active', 'deleted_at']);
        $this->assertColumns('clinics', ['slug', 'name', 'address_line1', 'pincode', 'city', 'state', 'gst_number', 'sale_share', 'is_active']);
        $this->assertColumns('products', ['name', 'type', 'hsn_sac_code', 'sku', 'sell_price', 'purchase_price', 'gst', 'is_active']);
        $this->assertColumns('invoices', ['invoice_number', 'invoice_type', 'clinic_id', 'state_code', 'sequence_number', 'old_invoice_number', 'user_id', 'package_id', 'invoice_date', 'payment_mode', 'source_note', 'subtotal', 'discount_total', 'taxable_value', 'gst_total', 'grand_total', 'amount_paid', 'amount_due', 'status', 'created_by']);
        $this->assertColumns('invoice_items', ['invoice_id', 'product_id', 'product_snapshot', 'hsn_sac_code', 'quantity', 'unit_price', 'discount_type', 'discount_value', 'valid_discount_amount', 'taxable_value', 'gst_percentage', 'gst_amount', 'line_total']);
        $this->assertColumns('invoice_payments', ['invoice_id', 'transaction_id', 'payment_date', 'amount', 'payment_method', 'reference_number', 'notes', 'created_by']);
        $this->assertColumns('appointments', ['type', 'clinic_id', 'user_id', 'therapist_id', 'start_datetime', 'end_datetime', 'duration_minutes', 'status', 'products_used', 'source', 'notes', 'is_billable', 'is_billed', 'created_by']);
        $this->assertColumns('loyalty_point_transactions', ['user_id', 'invoice_payment_id', 'type', 'points', 'balance_after', 'description', 'metadata', 'created_by']);
        $this->assertColumns('purchases', ['clinic_id', 'supplier_name', 'total_amount', 'purchase_date', 'payment_mode', 'status', 'notes', 'subtotal', 'total_gst', 'total_discount', 'created_by', 'updated_by']);
        $this->assertColumns('purchase_items', ['purchase_id', 'product_id', 'quantity', 'purchase_price', 'discount', 'gst', 'gst_amount', 'total']);
        $this->assertColumns('clinic_inventories', ['clinic_id', 'product_id', 'stock_quantity']);

        $this->checkDecryption();

        $this->roleIds = $this->db->table('roles')->where('guard_name', 'web')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        if (! isset($this->roleIds['client'])) {
            throw new RuntimeException("Role 'client' (guard web) does not exist in this CRM.");
        }
        foreach (array_filter(config('legacy_import.role_map')) as $newRole) {
            if (! isset($this->roleIds[$newRole])) {
                throw new RuntimeException("Role '{$newRole}' from config legacy_import.role_map does not exist in this CRM.");
            }
        }

        $this->fallbackUserId = $this->resolveFallbackUser();
        $this->loadState();
        $this->buildScope();
        $clinics = $this->resolveClinicMapping(dryResolve: true);
        $this->checkInvoiceNumbering($clinics);
    }

    /**
     * invoice_number_order = same_as_old needs INV-{STATE}-0001.. to be free (counter at 0).
     * Checked here, before any step writes, so an unsuitable database stops untouched.
     */
    private function checkInvoiceNumbering(array $clinics): void
    {
        if (config('legacy_import.invoice_number_order', 'same_as_old') !== 'same_as_old') {
            return;
        }
        $states = collect($clinics)->pluck('new_id')->filter()
            ->map(fn ($id) => $this->clinicState[(int) $id] ?: config('project.company_state_code', 'MH'))->unique();
        foreach ($states as $state) {
            if ($this->db->table('legacy_import_map')->where('entity', "invoice_block:{$state}")->exists()) {
                continue; // block already taken by an earlier run of this import
            }
            $last = (int) ($this->db->table('state_invoice_sequences')->where('state_code', $state)->value('last_number') ?? 0);
            if ($last !== 0 && ! $this->options['renumber_existing_invoices']) {
                throw new RuntimeException(
                    "Invoice numbers can only equal the old bill numbers while the {$state} invoice counter is at 0, but it is at {$last} "
                    . "(INV-{$state}-0001 onwards are already used). Either set legacy_import.invoice_number_order to old_number to number "
                    . "the old invoices after INV-{$state}-" . sprintf('%04d', $last) . ', or pass --renumber-existing-invoices to move '
                    . "this CRM's INV-{$state} invoices to after the old ones (changes numbers already issued — check GST implications)."
                );
            }
            if ($last !== 0) {
                $existing = $this->existingSeriesInvoices($state);
                ($this->log)('warn', sprintf('  invoice numbers: --renumber-existing-invoices — %d existing INV-%s invoice(s) will be MOVED after the old ones: %s',
                    $existing->count(), $state, $existing->take(10)->pluck('invoice_number')->implode(', ') . ($existing->count() > 10 ? ', …' : '')));
            } else {
                ($this->log)('info', "  invoice numbers: {$state} counter is at 0 — old invoices will keep their old bill numbers (INV-{$state}-<old no.>).");
            }
        }
    }

    /**
     * With --only-estate / config only_estates: a client, staff member or product is in scope
     * when it belongs to one of those clinics OR is used by their sales / appointments
     * (e.g. clients registered at head office who visited the clinic, the admin who billed).
     */
    private function buildScope(): void
    {
        if ($this->estates === null) {
            ($this->log)('info', 'Scope: ALL old clinics.');

            return;
        }
        $e = $this->estates;
        $sales = fn () => $this->old->table('sales')->whereIn('estate_id', $e);
        $appts = fn () => $this->old->table('appointments')->whereIn('estate_id', $e);
        $ids = fn ($q, string $col) => $q->distinct()->pluck($col)->filter()->map(fn ($v) => (int) $v)->all();

        $clients = array_merge(
            $ids($this->old->table('clients')->whereIn('creation_location_id', $e), 'id'),
            $ids($sales(), 'client_id'),
            $ids($appts(), 'client_id'),
        );
        $staff = array_merge(
            $ids($this->old->table('users')->whereIn('estate_id', $e), 'id'),
            $ids($sales(), 'entry_by'),
            $ids($this->old->table('sale_receipts')->join('sales', 'sales.id', '=', 'sale_receipts.sale_id')->whereIn('sales.estate_id', $e), 'sale_receipts.entry_by'),
            $ids($appts(), 'created_by'),
            // the clinic's primary doctor becomes the therapist of imported appointments
            $ids($this->old->table('users')->whereIn('estate_id', $e)->where('is_primary_doctor', 1), 'id'),
        );
        $products = array_merge(
            $ids($this->old->table('products')->whereIn('estate_id', $e), 'id'),
            $ids($this->old->table('sale_products')->join('sales', 'sales.id', '=', 'sale_products.sale_id')->whereIn('sales.estate_id', $e), 'sale_products.product_id'),
            $ids($this->old->table('appointment_products')->join('appointments', 'appointments.id', '=', 'appointment_products.appointment_id')->whereIn('appointments.estate_id', $e), 'appointment_products.product_id'),
        );

        $this->scope = [
            'client' => array_fill_keys($clients, true),
            'staff' => array_fill_keys($staff, true),
            'product' => array_fill_keys($products, true),
        ];
        ($this->log)('info', sprintf(
            'Scope: old estate(s) %s only — %d clients, %d staff, %d products in scope; everything else is skipped as "out of scope".',
            implode(', ', $e), count($this->scope['client']), count($this->scope['staff']), count($this->scope['product'])
        ));
    }

    private function outOfScope(string $entity, int $oldId): bool
    {
        return $this->scope !== null && ! isset($this->scope[$entity][$oldId]);
    }

    /** When exactly one clinic is in scope, in-scope users without a clinic of their own get it. */
    private function scopeClinicId(): ?int
    {
        return $this->estates !== null && count($this->estates) === 1
            ? ($this->map['estate'][$this->estates[0]] ?? null)
            : null;
    }

    private function assertColumns(string $table, array $columns): void
    {
        $missing = array_diff($columns, Schema::getColumnListing($table));
        if ($missing) {
            throw new RuntimeException("Table `{$table}` is missing columns: " . implode(', ', $missing) . '. Is this CRM fully migrated?');
        }
    }

    private function checkDecryption(): void
    {
        $cfg = config('legacy_import.decrypt');
        if (blank($cfg['key']) || blank($cfg['cipher']) || blank($cfg['iv'])) {
            throw new RuntimeException('LEGACY_CRM_APP_KEY / LEGACY_CRM_CIPHER_METHOD / LEGACY_CRM_IV are not set in .env (copy them from the old server .env).');
        }

        $sample = $this->old->table('clients')->orderByDesc('id')->limit(200)->pluck('mobile');
        $ok = $sample->filter(fn ($m) => strlen($this->mobileKey($this->decrypt($m)) ?? '') === 10)->count();
        $rate = $sample->count() ? $ok / $sample->count() : 0;
        ($this->log)('info', sprintf('Decryption check: %d/%d sample client mobiles decrypt to a 10-digit number.', $ok, $sample->count()));
        if ($rate < 0.9) {
            throw new RuntimeException('Fewer than 90% of sample mobiles decrypt to valid numbers — the LEGACY_CRM_* key/cipher/IV values are probably wrong.');
        }
    }

    private function resolveFallbackUser(): int
    {
        $id = $this->options['fallback_user_id'];
        if ($id) {
            if (! $this->db->table('users')->where('id', $id)->exists()) {
                throw new RuntimeException("--fallback-user={$id} does not exist.");
            }

            return (int) $id;
        }

        $id = $this->db->table('users')
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super_admin')
            ->whereNull('users.deleted_at')
            ->orderBy('users.id')
            ->value('users.id');

        if (! $id) {
            throw new RuntimeException('No super_admin user found to use as created_by fallback; pass --fallback-user=<id>.');
        }

        return (int) $id;
    }

    /** Loads the id map and the uniqueness indexes of the live tables. */
    private function loadState(): void
    {
        $this->map = [];
        $this->db->table('legacy_import_map')->select(['entity', 'old_id', 'new_id'])->orderBy('id')
            // --retry-skipped: forget earlier "skipped" decisions so those rows are tried again.
            ->when($this->options['retry_skipped'] ?? false, fn ($q) => $q->where('action', '!=', 'skipped'))
            ->each(function ($row) {
                $this->map[$row->entity][(int) $row->old_id] = $row->new_id !== null ? (int) $row->new_id : null;
            }, 5000);

        $this->mobileIndex = $this->emailIndex = $this->referralIndex = [];
        $this->db->table('users')->select(['id', 'mobile', 'email', 'referral_code'])->orderBy('id')
            ->each(function ($u) {
                if ($key = $this->mobileKey($u->mobile)) {
                    $this->mobileIndex[$key] ??= (int) $u->id;
                }
                if ($u->email) {
                    $this->emailIndex[mb_strtolower(trim($u->email))] ??= (int) $u->id;
                }
                if ($u->referral_code) {
                    $this->referralIndex[mb_strtoupper($u->referral_code)] = true;
                }
            }, 5000);

        $this->clinicState = $this->db->table('clinics')->pluck('state', 'id')->mapWithKeys(fn ($s, $id) => [(int) $id => $s])->all();
    }

    // ------------------------------------------------------------------
    //  Running
    // ------------------------------------------------------------------

    /**
     * @param  list<string>  $steps
     */
    public function run(array $steps): void
    {
        if ($this->options['dry_run']) {
            // Everything in one transaction that is always rolled back.
            $this->db->beginTransaction();
            try {
                foreach ($steps as $step) {
                    $this->runStep($step);
                }
            } finally {
                $this->db->rollBack();
                ($this->log)('warn', 'DRY RUN — every change above has been rolled back. Nothing was written.');
            }

            return;
        }

        foreach ($steps as $step) {
            $this->runStep($step);
        }
    }

    private function runStep(string $step): void
    {
        ($this->log)('step', strtoupper($step));
        $started = microtime(true);
        $this->{'step' . Str::studly($step)}();
        $s = $this->stats[$step] ?? [];
        ($this->log)('info', sprintf(
            '%s done in %.1fs — %s',
            $step,
            microtime(true) - $started,
            $s ? collect($s)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') : 'nothing to do'
        ));
    }

    // ------------------------------------------------------------------
    //  Step: clinics (old estate_id -> new clinic id)
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{old: object, new_id: ?int, how: string}>
     */
    private function resolveClinicMapping(bool $dryResolve = false): array
    {
        $optionMap = $this->options['clinic_map'] + array_map('intval', config('legacy_import.clinic_map', []));
        $newClinics = $this->db->table('clinics')->get(['id', 'name']);
        $byName = $newClinics->mapWithKeys(fn ($c) => [$this->normName($c->name) => (int) $c->id])->all();
        $validIds = $newClinics->pluck('id')->map(fn ($id) => (int) $id)->flip()->all();

        $estatesWithData = collect([
            $this->old->table('clients')->distinct()->pluck('creation_location_id'),
            $this->old->table('sales')->distinct()->pluck('estate_id'),
            $this->old->table('appointments')->distinct()->pluck('estate_id'),
        ])->flatten()->map(fn ($id) => (int) $id)->unique()->flip()->all();

        $oldClinics = $this->old->table('clinics')->orderBy('estate_id')->get();
        $result = [];
        $unresolved = [];

        foreach ($oldClinics as $c) {
            $estate = (int) $c->estate_id;
            if (array_key_exists($estate, $this->map['estate'] ?? [])) {
                $result[$estate] = ['old' => $c, 'new_id' => $this->map['estate'][$estate], 'how' => 'already mapped'];
            } elseif ($this->estates !== null && ! in_array($estate, $this->estates, true)) {
                $result[$estate] = ['old' => $c, 'new_id' => null, 'how' => 'out of scope, skipped'];
            } elseif (isset($optionMap[$estate])) {
                if (! isset($validIds[$optionMap[$estate]])) {
                    throw new RuntimeException("Clinic map {$estate}:{$optionMap[$estate]} points at a clinic id that does not exist.");
                }
                $result[$estate] = ['old' => $c, 'new_id' => $optionMap[$estate], 'how' => 'explicit map'];
            } elseif (isset($byName[$this->normName($c->name)])) {
                $result[$estate] = ['old' => $c, 'new_id' => $byName[$this->normName($c->name)], 'how' => 'name match'];
            } elseif (! isset($estatesWithData[$estate])) {
                $result[$estate] = ['old' => $c, 'new_id' => null, 'how' => 'no data, skipped'];
            } else {
                $result[$estate] = ['old' => $c, 'new_id' => null, 'how' => 'CREATE'];
                $unresolved[] = "estate {$estate} \"{$c->name}\"" . ($c->deleted_at ? ' (deleted in old CRM)' : '');
            }
        }

        if ($dryResolve) {
            foreach ($result as $estate => $r) {
                ($this->log)('info', sprintf('  clinic: old estate %d "%s" -> %s (%s)', $estate, $r['old']->name, $r['new_id'] ? "clinic #{$r['new_id']}" : '—', $r['how']));
            }
            if ($unresolved && ! $this->options['create_missing_clinics']) {
                throw new RuntimeException(
                    "These old clinics have data but no matching clinic in this CRM:\n  - " . implode("\n  - ", $unresolved)
                    . "\nMap them with --clinic-map=<old_estate_id>:<new_clinic_id>,... or allow creating them with --create-missing-clinics."
                );
            }
        }

        return $result;
    }

    private function stepClinics(): void
    {
        // Estates 1 (Website) and 2 (Backend) are head-office, not clinics.
        foreach ($this->old->table('estates')->where('type', '!=', self::ESTATE_TYPE_CLINIC)->pluck('id') as $estateId) {
            if (! array_key_exists((int) $estateId, $this->map['estate'] ?? [])) {
                $this->record('estate', (int) $estateId, null, null, 'skipped', 'not a clinic estate');
            }
        }

        $cities = $this->oldCities();

        foreach ($this->resolveClinicMapping() as $estate => $r) {
            if (array_key_exists($estate, $this->map['estate'] ?? [])) {
                $this->bump('clinics', 'already done');
                continue;
            }
            $c = $r['old'];

            $this->db->transaction(function () use ($estate, $r, $c, $cities) {
                if ($r['new_id']) {
                    $this->record('estate', $estate, $r['new_id'], 'clinics', 'matched', $r['how']);
                    $this->bump('clinics', 'matched');

                    return;
                }
                if ($r['how'] !== 'CREATE') {
                    $this->record('estate', $estate, null, null, 'skipped', $r['how']);
                    $this->bump('clinics', 'skipped');

                    return;
                }

                $city = $cities[(int) $c->city_id] ?? null;
                $id = $this->db->table('clinics')->insertGetId([
                    'slug' => $this->uniqueSlug($c->name),
                    'name' => mb_strtolower(trim($c->name)), // Clinic::name mutator stores lowercase
                    'address_line1' => $c->address_line_1 ?: '-',
                    'address_line2' => $c->address_line_2 ?: null,
                    'pincode' => (string) $c->pincode,
                    'city' => $city['city'] ?? '-',
                    'state' => $city['state_code'] ?? null,
                    'gst_number' => $c->gst_number ?: null,
                    'sale_share' => $c->sg_referral_share ?? 0,
                    'google_map_link' => $c->google_map_link ?: null,
                    'is_active' => false, // review and activate by hand
                    'created_at' => $c->created_at ?? $this->now,
                    'updated_at' => $c->updated_at ?? $c->created_at ?? $this->now, // keep the old CRM's timestamp
                ]);
                $this->clinicState[$id] = $city['state_code'] ?? null;
                $this->record('estate', $estate, $id, 'clinics', 'created', 'created inactive');
                $this->bump('clinics', 'created (inactive)');
            });
        }
    }

    // ------------------------------------------------------------------
    //  Step: staff (old users)
    // ------------------------------------------------------------------

    private function stepStaff(): void
    {
        $roleNames = $this->old->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->pluck('roles.name', 'model_has_roles.model_id')->all();
        $roleMap = config('legacy_import.role_map');
        $activate = $this->options['activate_staff'] || config('legacy_import.activate_new_staff');

        foreach ($this->old->table('users')->orderBy('id')->get() as $u) {
            if (array_key_exists((int) $u->id, $this->map['staff'] ?? [])) {
                $this->bump('staff', 'already done');
                continue;
            }

            if ($this->outOfScope('staff', (int) $u->id)) {
                $this->record('staff', (int) $u->id, null, null, 'skipped', 'out of scope');
                $this->bump('staff', 'out of scope');
                continue;
            }

            $this->db->transaction(function () use ($u, $roleNames, $roleMap, $activate) {
                $mobile = $this->decrypt($u->mobile);
                $key = $this->mobileKey($mobile);
                $email = $this->cleanEmail($this->decrypt($u->email));

                // Mobile only: old CRM staff accounts were reused and share emails (old #3 Sapna Chavan
                // carries Devika's address), so an email match links the wrong person.
                $existing = $key ? ($this->mobileIndex[$key] ?? null) : null;
                if ($existing) {
                    $this->record('staff', (int) $u->id, $existing, 'users', 'matched', 'mobile');
                    $this->bump('staff', 'matched existing user');

                    return;
                }
                if (! $key) {
                    $this->record('staff', (int) $u->id, null, null, 'skipped', 'mobile not decryptable');
                    $this->issue('staff', $u->id, 'mobile could not be decrypted; staff user not created (their records fall back to --fallback-user)');
                    $this->bump('staff', 'skipped');

                    return;
                }

                $oldRole = $roleNames[$u->id] ?? null;
                $newRole = $oldRole !== null ? ($roleMap[$oldRole] ?? null) : null;

                $id = $this->db->table('users')->insertGetId([
                    // head-office staff (estate 1/2) stay without a clinic
                    'clinic_id' => $this->map['estate'][(int) $u->estate_id] ?? null,
                    'first_name' => trim($u->first_name),
                    'last_name' => trim($u->last_name),
                    'mobile' => $key,
                    'email' => $email && ! isset($this->emailIndex[$email]) ? $email : null,
                    'password' => $u->password, // bcrypt hash, valid as-is
                    'is_active' => $activate && ! $u->deleted_at,
                    'deleted_at' => $u->deleted_at,
                    'created_at' => $u->created_at ?? $this->now,
                    'updated_at' => $u->updated_at ?? $u->created_at ?? $this->now, // keep the old CRM's timestamp
                ]);
                if ($newRole) {
                    $this->assignRole($id, $newRole);
                }
                $this->indexUser($id, $key, $email, null);
                $this->record('staff', (int) $u->id, $id, 'users', 'created', trim("{$oldRole} -> " . ($newRole ?? 'no role')));
                $this->bump('staff', $activate ? 'created' : 'created (inactive)');
            });
        }
    }

    // ------------------------------------------------------------------
    //  Step: clients
    // ------------------------------------------------------------------

    private function stepClients(): void
    {
        $cities = $this->oldCities();
        $pointsClients = $this->old->table('points_ledgers')->distinct()->pluck('client_id')->map(fn ($i) => (int) $i)->flip()->all();

        $this->old->table('clients')
            ->leftJoin('client_medical_backgrounds as m', function ($j) {
                $j->on('m.client_id', '=', 'clients.id')
                    ->whereRaw('m.id = (select max(id) from client_medical_backgrounds where client_id = clients.id)');
            })
            ->select(['clients.*',
                'm.has_diabetes', 'm.has_high_bp', 'm.has_high_cholesterol', 'm.has_asthma', 'm.has_heart_disease',
                'm.has_anaemia', 'm.has_pcos', 'm.has_thyroid', 'm.has_other_disease', 'm.current_medication', 'm.allergies',
                'm.skin_type_id', 'm.skin_quality_id', 'm.facials_id', 'm.look_less_tired', 'm.look_less_angry', 'm.look_less_sad',
                'm.get_less_saggy_appearance', 'm.look_more_youthful', 'm.look_more_attractive', 'm.get_softer_features',
                'm.get_slimmer_face', 'm.skin_improvement_id', 'm.remarks'])
            ->orderBy('clients.id')
            ->chunk($this->options['chunk'], function ($chunk) use ($cities, $pointsClients) {
                $password = Hash::make(Str::random(40)); // unknown random password; clients do not log in

                $this->db->transaction(function () use ($chunk, $cities, $pointsClients, $password) {
                    foreach ($chunk as $c) {
                        $this->importClient($c, $cities, $pointsClients, $password);
                    }
                });
                ($this->log)('progress', 'clients up to old id ' . $chunk->last()->id);
            });
    }

    private function importClient(object $c, array $cities, array $pointsClients, string $password): void
    {
        $oldId = (int) $c->id;
        if (array_key_exists($oldId, $this->map['client'] ?? [])) {
            $this->bump('clients', 'already done');

            return;
        }
        if ($this->outOfScope('client', $oldId)) {
            $this->record('client', $oldId, null, null, 'skipped', 'out of scope');
            $this->bump('clients', 'out of scope');

            return;
        }

        $key = $this->mobileKey($this->decrypt($c->mobile));
        if (! $key) {
            $this->record('client', $oldId, null, null, 'skipped', 'mobile not decryptable');
            $this->issue('clients', $oldId, 'mobile could not be decrypted / is not a valid number; client skipped (their sales will be skipped too)');
            $this->bump('clients', 'skipped');

            return;
        }

        if (isset($this->mobileIndex[$key])) {
            // Existing production client (or an old duplicate already imported in this run):
            // link to it, never modify it.
            $this->record('client', $oldId, $this->mobileIndex[$key], 'users', 'matched', 'mobile');
            $this->bump('clients', 'matched existing user');

            return;
        }

        $email = $this->cleanEmail($this->decrypt($c->email));
        if ($email && isset($this->emailIndex[$email])) {
            $this->issue('clients', $oldId, "email {$email} already used by user #{$this->emailIndex[$email]}; imported without email");
            $email = null;
        }

        $code = $c->referral_code ? mb_strtoupper(trim($c->referral_code)) : null;
        if (! $code || isset($this->referralIndex[$code])) {
            do {
                $code = 'REF' . random_int(100000, 999999);
            } while (isset($this->referralIndex[$code]));
        }

        if ((int) $c->skin_quality_id === 19) {
            $this->issue('clients', $oldId, "skin quality 'Very Good' stored as 'Good'");
        }

        $city = $cities[(int) $c->city_id] ?? null;
        $estate = $this->map['estate'][(int) $c->creation_location_id]
            ?? $this->map['estate'][(int) $c->registration_location_id]
            ?? $this->scopeClinicId(); // registered at head office but visited the in-scope clinic
        $remarks = trim((string) $c->remarks);

        $id = $this->db->table('users')->insertGetId([
            'clinic_id' => $estate,
            'first_name' => trim($c->first_name),
            'last_name' => trim($c->last_name),
            'gender' => self::GENDER[(int) $c->gender_id] ?? null,
            'date_of_birth' => $this->validDate($c->dob),
            'mobile' => $key,
            'email' => $email,
            'occupation' => $c->occupation ?: null,
            'address_line_1' => $c->address_line_1 ?: null,
            'address_line_2' => $c->address_line_2 ?: null,
            'pincode' => $c->pincode ? (string) $c->pincode : null,
            'city' => $city['city'] ?? null,
            'state' => $city['state_code'] ?? null,
            'referral_code' => $code,
            'opt_for_loyalty' => (bool) $c->skin_genious_member || isset($pointsClients[$oldId]),
            'how_did_you_hear' => self::SOURCE[(int) $c->source_id] ?? null,
            'loyalty_points' => 0, // opening balance is added by step "loyalty"
            'has_diabetes' => (bool) $c->has_diabetes,
            'has_high_bp' => (bool) $c->has_high_bp,
            'has_cholesterol' => (bool) $c->has_high_cholesterol,
            'has_asthma' => (bool) $c->has_asthma,
            'has_heart_disease' => (bool) $c->has_heart_disease,
            'has_anaemia' => (bool) $c->has_anaemia,
            'has_pcos' => (bool) $c->has_pcos,
            'has_thyroid' => (bool) $c->has_thyroid,
            'other_diseases' => trim(implode("\n", array_filter([$c->has_other_disease, $remarks !== '' ? "Remarks (old CRM): {$remarks}" : null]))) ?: null,
            'current_medications' => $c->current_medication ?: null,
            'allergies' => $c->allergies ?: null,
            'skin_type' => self::SKIN_TYPE[(int) $c->skin_type_id] ?? null,
            'facials_history' => self::FACIALS[(int) $c->facials_id] ?? null,
            'skin_quality' => self::SKIN_QUALITY[(int) $c->skin_quality_id] ?? null,
            'goal_less_tired' => (bool) $c->look_less_tired,
            'goal_less_angry' => (bool) $c->look_less_angry,
            'goal_less_sad' => (bool) $c->look_less_sad,
            'goal_less_saggy' => (bool) $c->get_less_saggy_appearance,
            'goal_youthful' => (bool) $c->look_more_youthful,
            'goal_attractive' => (bool) $c->look_more_attractive,
            'goal_soft_features' => (bool) $c->get_softer_features,
            'goal_slim_face' => (bool) $c->get_slimmer_face,
            'skin_improvement' => self::SKIN_IMPROVEMENT[(int) $c->skin_improvement_id] ?? null,
            'password' => $password,
            'is_active' => ! $c->deleted_at,
            'deleted_at' => $c->deleted_at,
            'created_at' => $c->created_at ?? $this->now,
            'updated_at' => $c->updated_at ?? $c->created_at ?? $this->now, // keep the old CRM's timestamp
        ]);

        $this->assignRole($id, 'client');
        $this->indexUser($id, $key, $email, $code);
        $this->record('client', $oldId, $id, 'users', 'created');
        $this->bump('clients', 'created');
    }

    // ------------------------------------------------------------------
    //  Step: referrals (only on users this import created)
    // ------------------------------------------------------------------

    private function stepReferrals(): void
    {
        $createdClients = $this->db->table('legacy_import_map')
            ->where('entity', 'client')->where('action', 'created')
            ->pluck('new_id', 'old_id')->mapWithKeys(fn ($n, $o) => [(int) $o => (int) $n])->all();

        $pairs = $this->old->table('clients')->whereNotNull('referrer_id')->get(['id', 'referrer_id']);
        $counts = [];

        $this->db->transaction(function () use ($pairs, $createdClients, &$counts) {
            foreach ($pairs as $p) {
                $newUser = $createdClients[(int) $p->id] ?? null;
                $referrer = $this->map['client'][(int) $p->referrer_id] ?? null;
                if (! $newUser || ! $referrer || $newUser === $referrer) {
                    $this->bump('referrals', 'skipped (not created by import / referrer missing)');
                    continue;
                }
                $updated = $this->db->table('users')->where('id', $newUser)->whereNull('referred_by')->update(['referred_by' => $referrer]);
                $this->bump('referrals', $updated ? 'linked' : 'already set');
                if ($updated && in_array($referrer, $createdClients, true)) {
                    $counts[$referrer] = ($counts[$referrer] ?? 0) + 1;
                }
            }

            // total_referrals only on referrers this import created (existing production users untouched).
            foreach ($counts as $userId => $n) {
                $this->db->table('users')->where('id', $userId)->update(['total_referrals' => DB::raw('total_referrals + ' . (int) $n)]);
            }
        });
    }

    // ------------------------------------------------------------------
    //  Step: products
    // ------------------------------------------------------------------

    private function stepProducts(): void
    {
        $gst = $this->old->table('product_tax_relations as r')
            ->join('taxes as t', 't.id', '=', 'r.tax_id')
            ->where('t.type', self::TAX_TYPE_OUTWARD)
            ->groupBy('r.product_id')
            ->selectRaw('r.product_id, SUM(t.percentage) as pct')
            ->pluck('pct', 'product_id')->all();

        $existing = $this->db->table('products')->get(['id', 'name', 'type'])
            ->mapWithKeys(fn ($p) => [$p->type . '|' . $this->normName($p->name) => (int) $p->id])->all();

        $this->db->transaction(function () use ($gst, &$existing) {
            foreach ($this->old->table('products')->orderBy('id')->get() as $p) {
                if (array_key_exists((int) $p->id, $this->map['product'] ?? [])) {
                    $this->bump('products', 'already done');
                    continue;
                }
                if ($this->outOfScope('product', (int) $p->id)) {
                    $this->record('product', (int) $p->id, null, null, 'skipped', 'out of scope');
                    $this->bump('products', 'out of scope');
                    continue;
                }
                $type = self::PRODUCT_TYPE[(int) $p->type] ?? 'product';
                $key = $type . '|' . $this->normName($p->name);

                if (isset($existing[$key])) {
                    $this->record('product', (int) $p->id, $existing[$key], 'products', 'matched', 'name+type');
                    $this->bump('products', 'matched existing product');
                    continue;
                }

                $id = $this->db->table('products')->insertGetId([
                    'name' => preg_replace('/\s+/', ' ', trim($p->name)),
                    'type' => $type,
                    'hsn_sac_code' => $type === 'service' ? '999729' : '330499', // same defaults as Product::saving
                    'sku' => null, // set right below: name initials + type + new id (App\Support\ProductSku)
                    'sell_price' => $p->price,
                    'purchase_price' => $p->purchase_price,
                    'gst' => (float) ($gst[$p->id] ?? 0),
                    'description' => (int) $p->type === 30 ? 'Membership (old CRM)' : null,
                    'is_active' => true,
                    'created_at' => $p->created_at ?? $this->now,
                    'updated_at' => $p->updated_at ?? $p->created_at ?? $this->now, // keep the old CRM's timestamp
                ]);
                $this->db->table('products')->where('id', $id)
                    ->update(['sku' => \App\Support\ProductSku::make(preg_replace('/\s+/', ' ', trim($p->name)), $type, (int) $id)]);
                $existing[$key] = $id;
                $this->record('product', (int) $p->id, $id, 'products', 'created');
                $this->bump('products', 'created');
            }
        });
    }

    // ------------------------------------------------------------------
    //  Step: stock (opening-stock purchase per product the import created)
    // ------------------------------------------------------------------

    /**
     * Old products.balance_quantity is the clinic's current stock. For every GOODS product
     * this import created, one purchase is recorded at the product's clinic with
     * quantity = balance_quantity and rate = purchase_price — calculated exactly like
     * PurchaseForm (rate excludes GST, GST % from the product, grand total rounded) — and
     * clinic_inventories is raised by that quantity, as PurchaseItem::created would do.
     *
     * Written with the query builder, so PurchaseObserver does NOT create an Expense:
     * this is opening stock, not new spending. Products matched to existing production
     * products are left alone (their stock is already managed in this CRM).
     */
    private function stepStock(): void
    {
        $dateField = config('legacy_import.opening_stock_date', 'updated_at') === 'created_at' ? 'created_at' : 'updated_at';
        $createdProducts = $this->db->table('legacy_import_map')->where('entity', 'product')->where('action', 'created')
            ->pluck('new_id', 'old_id')->mapWithKeys(fn ($n, $o) => [(int) $o => (int) $n])->all();
        if (! $createdProducts) {
            return;
        }
        $gstByProduct = $this->db->table('products')->whereIn('id', array_values($createdProducts))->pluck('gst', 'id')->all();

        $rows = $this->old->table('products')
            ->whereIn('id', array_keys($createdProducts))
            ->where('type', 28) // Goods only; services / membership hold no stock
            ->where('balance_quantity', '>', 0)
            ->orderBy('id')->get();

        $this->db->transaction(function () use ($rows, $createdProducts, $gstByProduct, $dateField) {
            foreach ($rows as $p) {
                $oldId = (int) $p->id;
                if (array_key_exists($oldId, $this->map['opening_stock'] ?? [])) {
                    $this->bump('stock', 'already done');
                    continue;
                }
                if ($p->deleted_at && ! $this->options['include_deleted_product_stock']) {
                    $this->record('opening_stock', $oldId, null, null, 'skipped', 'product deleted in old CRM');
                    $this->issue('stock', $oldId, sprintf('"%s" was deleted in the old CRM but still shows %d in stock; no opening stock added (use --include-deleted-product-stock)', preg_replace('/\s+/', ' ', trim($p->name)), $p->balance_quantity));
                    $this->bump('stock', 'skipped (deleted in old CRM)');
                    continue;
                }
                $clinicId = $this->map['estate'][(int) $p->estate_id] ?? $this->scopeClinicId();
                if (! $clinicId) {
                    $this->record('opening_stock', $oldId, null, null, 'skipped', 'no clinic for product');
                    $this->bump('stock', 'skipped (no clinic)');
                    continue;
                }

                $productId = $createdProducts[$oldId];
                $qty = (int) $p->balance_quantity;
                $rate = round((float) ($p->purchase_price ?? 0), 2);
                $gst = (float) ($gstByProduct[$productId] ?? 0);
                $taxable = round($qty * $rate, 2);
                $gstAmount = round($taxable * $gst / 100, 2);
                $at = $p->{$dateField} ?? $p->created_at ?? $this->now;
                $date = Carbon::parse($at)->toDateString();
                if ($rate <= 0) {
                    $this->issue('stock', $oldId, sprintf('"%s" has no purchase price in the old CRM; opening stock recorded at rate 0', trim($p->name)));
                }

                $purchaseId = $this->db->table('purchases')->insertGetId([
                    'clinic_id' => $clinicId,
                    'supplier_name' => 'Old CRM (opening stock)',
                    'total_amount' => round($taxable + $gstAmount), // PurchaseForm rounds the grand total
                    'purchase_date' => $date,
                    'payment_mode' => null,
                    'status' => 'Paid',
                    'notes' => "Opening stock from old CRM product #{$oldId} (stock {$qty} on {$date})",
                    'subtotal' => $taxable,
                    'total_gst' => $gstAmount,
                    'total_discount' => 0,
                    'created_by' => $this->staffOrFallback($p->created_by),
                    'updated_by' => null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
                $this->db->table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'purchase_price' => $rate,
                    'discount' => 0,
                    'gst' => $gst,
                    'gst_amount' => $gstAmount,
                    'total' => $taxable, // PurchaseForm stores the taxable value in "total"
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                $inventory = $this->db->table('clinic_inventories')->where('clinic_id', $clinicId)->where('product_id', $productId)->lockForUpdate()->first();
                if ($inventory) {
                    $this->db->table('clinic_inventories')->where('id', $inventory->id)->update(['stock_quantity' => DB::raw('stock_quantity + ' . $qty), 'updated_at' => $this->now]);
                } else {
                    $this->db->table('clinic_inventories')->insert(['clinic_id' => $clinicId, 'product_id' => $productId, 'stock_quantity' => $qty, 'created_at' => $at, 'updated_at' => $at]);
                }

                $this->record('opening_stock', $oldId, $purchaseId, 'purchases', 'created', "qty {$qty}");
                $this->bump('stock', 'purchases created');
                $this->bump('stock', 'units', $qty);
            }
        });
    }

    // ------------------------------------------------------------------
    //  Step: invoices (sales + sale_products + sale_receipts)
    // ------------------------------------------------------------------

    /** @var array<int, array{0: string, 1: int}> old sale id => [state code, sequence number] */
    private array $invoiceNumbers = [];

    /** Old sales this run reads (scope + deleted filter). */
    private function salesQuery()
    {
        return $this->old->table('sales')
            ->when($this->estates !== null, fn ($q) => $q->whereIn('estate_id', $this->estates))
            ->when(! $this->options['include_deleted_sales'], fn ($q) => $q->whereNull('deleted_at'));
    }

    private function stepInvoices(): void
    {
        $this->planInvoiceNumbers();

        $this->salesQuery()->orderBy('id')->chunk($this->options['chunk'], function ($sales) {
            $ids = $sales->pluck('id')->all();
            $lines = $this->old->table('sale_products')->whereIn('sale_id', $ids)->orderBy('id')->get()->groupBy('sale_id');
            $lineTax = $this->old->table('sale_product_taxes')
                ->whereIn('sale_product_id', $lines->flatten()->pluck('id')->all() ?: [0])
                ->groupBy('sale_product_id')->selectRaw('sale_product_id, SUM(tax_percentage) as pct')
                ->pluck('pct', 'sale_product_id')->all();
            $receipts = $this->old->table('sale_receipts')->whereIn('sale_id', $ids)->orderBy('id')->get()->groupBy('sale_id');

            $this->db->transaction(function () use ($sales, $lines, $lineTax, $receipts) {
                foreach ($sales as $s) {
                    $this->importSale($s, $lines[$s->id] ?? collect(), $lineTax, $receipts[$s->id] ?? collect());
                }
            });
            ($this->log)('progress', 'sales up to old id ' . $sales->last()->id);
        });
    }

    /**
     * Old invoices get numbers in the live INV-{STATE}-{n} series (same format as
     * InvoiceNumberService), consecutively in the order of config invoice_number_order:
     * "old_number" (default) follows the old bill numbers — bill 001 first — while "date"
     * follows the bill date. Old bill numbers are not in date order (paper bills were typed
     * in later), so the two orders differ. Existing invoices already hold the start of
     * the series, so a block AFTER the current state_invoice_sequences.last_number is
     * reserved and the counter is moved past it — new invoices continue after the block.
     *
     * The block is recorded in legacy_import_map (entity "invoice_block:{STATE}",
     * old_id = size, new_id = number before the block), so a resumed run reuses the same
     * numbers and legacy:rollback can hand them back.
     */
    private function planInvoiceNumbers(): void
    {
        $this->invoiceNumbers = [];
        $withItems = $this->old->table('sale_products')->distinct()->pluck('sale_id')->map(fn ($i) => (int) $i)->flip()->all();
        $byState = [];
        $mode = config('legacy_import.invoice_number_order', 'same_as_old');
        if (! in_array($mode, ['same_as_old', 'old_number', 'date'], true)) {
            throw new RuntimeException("config legacy_import.invoice_number_order must be same_as_old, old_number or date (got '{$mode}').");
        }
        $sales = $this->salesQuery()
            ->when($mode === 'date',
                fn ($q) => $q->orderBy('date_time'),
                // old bill numbers are numeric strings ("001", "1580"): sort by value, not text
                fn ($q) => $q->orderByRaw('CAST(invoice_number AS UNSIGNED)')->orderBy('invoice_number'))
            ->orderBy('id')
            ->get(['id', 'estate_id', 'client_id', 'invoice_number']);

        // Only sales that will really become invoices get a number.
        foreach ($sales as $s) {
            $clinicId = $this->map['estate'][(int) $s->estate_id] ?? null;
            if (! $clinicId || empty($this->map['client'][(int) $s->client_id]) || ! isset($withItems[(int) $s->id])) {
                continue;
            }
            $state = $this->clinicState[$clinicId] ?: config('project.company_state_code', 'MH');
            $byState[$state][(int) $s->id] = trim((string) $s->invoice_number);
        }

        foreach ($byState as $state => $oldNumbers) {
            if ($mode === 'same_as_old') {
                $this->planSameAsOld($state, $oldNumbers);
                continue;
            }
            // consecutive numbering after the current counter (no gaps)
            $ids = array_keys($oldNumbers);
            $before = $this->reserveInvoiceBlock($state, count($ids));
            foreach ($ids as $i => $id) {
                $this->invoiceNumbers[$id] = [$state, $before + $i + 1];
            }
            ($this->log)('info', sprintf('  invoice numbers: %s … %s (%d old invoices, consecutive in order of %s)',
                $this->formatInvoiceNumber($state, $before + 1), $this->formatInvoiceNumber($state, $before + count($ids)), count($ids),
                $mode === 'date' ? 'bill date' : 'old bill number'));
        }
    }

    /**
     * "same_as_old": the new number IS the old bill number (old 020 -> INV-MH-0020), so numbers
     * of bills that are not imported (deleted in the old CRM) stay unused as gaps. Only possible
     * while numbers 1..max(old) are free, i.e. the state's counter is still at 0 — otherwise the
     * import stops before writing anything. The counter is then set to the highest old number.
     *
     * @param  array<int, string>  $oldNumbers  old sale id => old bill number
     */
    private function planSameAsOld(string $state, array $oldNumbers): void
    {
        foreach ($oldNumbers as $id => $no) {
            if (! ctype_digit($no) || (int) $no < 1) {
                throw new RuntimeException("Old bill number '{$no}' (sale #{$id}) is not a positive whole number, so it cannot be used as the {$state} invoice number. Set legacy_import.invoice_number_order to old_number instead.");
            }
        }
        $max = max(array_map('intval', $oldNumbers));
        $last = (int) ($this->db->table('state_invoice_sequences')->where('state_code', $state)->value('last_number') ?? 0);
        $blockTaken = $this->db->table('legacy_import_map')->where('entity', "invoice_block:{$state}")->exists();
        $before = (! $blockTaken && $last !== 0 && $this->options['renumber_existing_invoices'])
            ? $this->moveExistingInvoicesAfter($state, $max)
            : $this->reserveInvoiceBlock($state, $max, mustStartAtZero: true);
        foreach ($oldNumbers as $id => $no) {
            $this->invoiceNumbers[$id] = [$state, $before + (int) $no];
        }
        ($this->log)('info', sprintf('  invoice numbers: same as old bill numbers, %s … %s (%d old invoices; numbers of bills not imported stay unused)',
            $this->formatInvoiceNumber($state, (int) min(array_map('intval', $oldNumbers))), $this->formatInvoiceNumber($state, $max), count($oldNumbers)));
    }

    /** This CRM's own invoices in the INV-{STATE} series, in number order. */
    private function existingSeriesInvoices(string $state)
    {
        return $this->db->table('invoices')
            ->where('invoice_number', 'like', "INV-{$state}-%")
            ->where(fn ($q) => $q->whereNull('invoice_type')->orWhere('invoice_type', '!=', config('legacy_import.invoice_type')))
            ->orderBy('sequence_number')->orderBy('id')
            ->get(['id', 'invoice_number', 'sequence_number']);
    }

    /** @var array<string, true> numbers that --renumber-existing-invoices frees (dry run) */
    private array $freedNumbers = [];

    /**
     * --renumber-existing-invoices: frees INV-{STATE}-0001..max(old) for the old bills by moving
     * this CRM's own INV-{STATE} invoices to max(old)+1, +2, ... in their current order, and
     * sets the counter after them. Each move is recorded (entity "renumbered_invoice",
     * note = previous number) together with the previous counter, so legacy:rollback restores
     * both. Runs in one short transaction holding the counter lock, like InvoiceNumberService.
     *
     * @return int 0 — old bill numbers are used as-is
     */
    private function moveExistingInvoicesAfter(string $state, int $maxOld): int
    {
        $existing = $this->existingSeriesInvoices($state);
        $plan = $existing->values()->map(fn ($inv, $i) => [$inv, $maxOld + $i + 1]);
        foreach ($plan->take(20) as [$inv, $n]) {
            ($this->log)('warn', sprintf('  renumber: %s -> %s', $inv->invoice_number, $this->formatInvoiceNumber($state, $n)));
        }

        if ($this->options['dry_run']) {
            // Read only (no counter lock during the long dry run); just treat the numbers as free.
            foreach ($existing as $inv) {
                $this->freedNumbers[$inv->invoice_number] = true;
            }

            return 0;
        }

        return $this->db->transaction(function () use ($state, $maxOld, $plan) {
            $row = $this->db->table('state_invoice_sequences')->where('state_code', $state)->lockForUpdate()->first();
            $before = (int) ($row->last_number ?? 0);
            // the list could have changed while we waited for the lock
            $plan = $this->existingSeriesInvoices($state)->values()->map(fn ($inv, $i) => [$inv, $maxOld + $i + 1]);

            // two passes so a move never collides with another existing number
            foreach ($plan as [$inv]) {
                $this->db->table('invoices')->where('id', $inv->id)->update(['invoice_number' => "TMP-RENUMBER-{$inv->id}"]);
            }
            foreach ($plan as [$inv, $n]) {
                $this->db->table('invoices')->where('id', $inv->id)->update([
                    'invoice_number' => $this->formatInvoiceNumber($state, $n),
                    'sequence_number' => $n,
                    'state_code' => $state,
                ]);
                $this->record('renumbered_invoice', (int) $inv->id, $n, 'invoices', 'created', $inv->invoice_number);
            }

            $after = $maxOld + $plan->count();
            $this->db->table('state_invoice_sequences')->where('id', $row->id)->update(['last_number' => $after, 'updated_at' => $this->now]);
            $this->record("invoice_counter_before:{$state}", 0, $before, 'state_invoice_sequences', 'created', "counter was {$before}");
            $this->record("invoice_block:{$state}", $after, 0, 'state_invoice_sequences', 'created',
                sprintf('%s … %s old bills, %d existing invoice(s) moved after them', $this->formatInvoiceNumber($state, 1), $this->formatInvoiceNumber($state, $maxOld), $plan->count()));
            $this->bump('invoices', 'existing invoices renumbered', $plan->count());

            return 0;
        });
    }

    /** @return int the sequence number just before the reserved block */
    private function reserveInvoiceBlock(string $state, int $count, bool $mustStartAtZero = false): int
    {
        $entity = "invoice_block:{$state}";
        $notFree = fn (int $last) => new RuntimeException(
            "Invoice numbers can only equal the old bill numbers while the {$state} invoice counter is at 0, "
            . "but it is at {$last} (INV-{$state}-0001 onwards are already used). "
            . "Set legacy_import.invoice_number_order to old_number to number the old invoices after INV-{$state}-" . sprintf('%04d', $last) . '.'
        );

        $existing = $this->db->table('legacy_import_map')->where('entity', $entity)->first();
        if ($existing) {
            // a --renumber-existing-invoices block also holds the moved invoices after the old ones
            $sizeOk = (int) $existing->old_id === $count || ($mustStartAtZero && (int) $existing->old_id > $count);
            if (! $sizeOk || ($mustStartAtZero && (int) $existing->new_id !== 0)) {
                throw new RuntimeException("A block of {$existing->old_id} {$state} invoice numbers was reserved by an earlier run with different settings. Use the same options (--only-estate, --include-deleted-sales, invoice_number_order) as the first run, or roll back first.");
            }

            return (int) $existing->new_id;
        }

        if ($this->options['dry_run']) {
            // Read only: locking the counter for the whole dry run would block live invoicing.
            $last = (int) ($this->db->table('state_invoice_sequences')->where('state_code', $state)->value('last_number') ?? 0);
            if ($mustStartAtZero && $last !== 0) {
                throw $notFree($last);
            }

            return $last;
        }

        // Short transaction: take the block and move the live counter past it at once.
        return $this->db->transaction(function () use ($state, $count, $entity, $mustStartAtZero, $notFree) {
            $row = $this->db->table('state_invoice_sequences')->where('state_code', $state)->lockForUpdate()->first();
            if (! $row) {
                $this->db->table('state_invoice_sequences')->insert(['state_code' => $state, 'last_number' => 0, 'created_at' => $this->now, 'updated_at' => $this->now]);
                $row = $this->db->table('state_invoice_sequences')->where('state_code', $state)->lockForUpdate()->first();
            }
            if ($mustStartAtZero && (int) $row->last_number !== 0) {
                throw $notFree((int) $row->last_number); // rolls this transaction back: counter untouched
            }
            $before = (int) $row->last_number;
            $this->db->table('state_invoice_sequences')->where('id', $row->id)->update(['last_number' => $before + $count, 'updated_at' => $this->now]);
            $this->record($entity, $count, $before, 'state_invoice_sequences', 'created',
                sprintf('%s … %s', $this->formatInvoiceNumber($state, $before + 1), $this->formatInvoiceNumber($state, $before + $count)));

            return $before;
        });
    }

    private function formatInvoiceNumber(string $state, int $n): string
    {
        return sprintf('INV-%s-%04d', $state, $n); // same format as InvoiceNumberService
    }

    private function importSale(object $s, $lines, array $lineTax, $receipts): void
    {
        $oldId = (int) $s->id;
        if (array_key_exists($oldId, $this->map['sale'] ?? [])) {
            $this->bump('invoices', 'already done');

            return;
        }

        $clinicId = $this->map['estate'][(int) $s->estate_id] ?? null;
        $userId = $this->map['client'][(int) $s->client_id] ?? null;
        $problem = match (true) {
            ! $clinicId => "estate {$s->estate_id} is not mapped to a clinic",
            ! $userId => "client {$s->client_id} was not imported",
            $lines->isEmpty() => 'sale has no line items',
            default => null,
        };
        if (! $problem) {
            foreach ($lines as $l) {
                if (empty($this->map['product'][(int) $l->product_id])) {
                    $problem = "product {$l->product_id} was not imported";
                    break;
                }
            }
        }
        if ($problem) {
            $this->record('sale', $oldId, null, null, 'skipped', Str::limit($problem, 250));
            $this->issue('invoices', $oldId, "sale {$s->invoice_number} skipped: {$problem}");
            $this->bump('invoices', 'skipped');

            return;
        }

        if (! isset($this->invoiceNumbers[$oldId])) {
            $this->record('sale', $oldId, null, null, 'skipped', 'no invoice number planned');
            $this->issue('invoices', $oldId, "sale {$s->invoice_number} skipped: it was not in the numbering plan");
            $this->bump('invoices', 'skipped');

            return;
        }
        [$state, $sequence] = $this->invoiceNumbers[$oldId];
        $number = $this->formatInvoiceNumber($state, $sequence);
        if (isset($this->freedNumbers[$number])) {
            // dry run of --renumber-existing-invoices: the existing invoice still holds this
            // number (it is only moved by a real run), so use a stand-in; everything is rolled back
            $number = "DRYRUN-{$number}";
        }
        if ($this->db->table('invoices')->where('invoice_number', $number)->exists()) {
            $this->record('sale', $oldId, null, null, 'skipped', "invoice number {$number} already exists");
            $this->issue('invoices', $oldId, "invoice number {$number} already exists in this CRM; skipped");
            $this->bump('invoices', 'skipped');

            return;
        }

        $paid = round((float) $receipts->sum('amount'), 2);
        $total = (float) $s->total;
        $methods = $receipts->pluck('payment_method_id')->unique()->map(fn ($m) => self::PAYMENT_METHOD[(int) $m] ?? 'other')->unique();
        $status = match (true) {
            $s->deleted_at !== null => 'cancelled',
            $paid <= 0 => 'unpaid',
            $paid >= $total => 'paid',
            default => 'partial',
        };
        if (abs($paid - (float) $s->amount_received) > 0.01) {
            $this->issue('invoices', $oldId, "sale {$s->invoice_number}: receipts total {$paid} differs from old amount_received {$s->amount_received}; receipts used");
        }

        $createdBy = $this->staffOrFallback($s->entry_by);
        $invoiceId = $this->db->table('invoices')->insertGetId([
            'invoice_number' => $number,
            'invoice_type' => config('legacy_import.invoice_type'),
            'clinic_id' => $clinicId,
            'state_code' => $state,
            'sequence_number' => $sequence, // from the block reserved in planInvoiceNumbers()
            'old_invoice_number' => $s->invoice_number, // number printed on the original bill
            'user_id' => $userId,
            'package_id' => null,
            'invoice_date' => $s->date_time,
            'payment_mode' => $methods->count() === 1 ? $methods->first() : ($methods->isEmpty() ? null : 'multiple'),
            'source_note' => "Imported from old CRM (sale #{$oldId})" . ($s->deleted_at ? " — bill deleted in old CRM on " . Carbon::parse($s->deleted_at)->format('d M Y') : ''),
            'subtotal' => $s->subtotal,
            'discount_total' => $s->discount,
            'taxable_value' => round($total - (float) $s->tax, 2),
            'gst_total' => $s->tax,
            'grand_total' => $total,
            'amount_paid' => $paid,
            'amount_due' => max(0, round($total - $paid, 2)),
            'status' => $status,
            'created_by' => $createdBy,
            'updated_by' => null,
            'created_at' => $s->created_at ?? $s->date_time,
            'updated_at' => $s->updated_at ?? $s->date_time,
        ]);

        $items = [];
        foreach ($lines as $l) {
            $productId = $this->map['product'][(int) $l->product_id];
            $items[] = [
                'invoice_id' => $invoiceId,
                'product_id' => $productId,
                // same shape as InvoiceItem::snapshotOf(), captured at the old bill's date
                'product_snapshot' => json_encode($this->productSnapshot($productId) + ['captured_at' => Carbon::parse($s->date_time)->toDateTimeString()]),
                'hsn_sac_code' => $this->productHsn($productId),
                'quantity' => (int) $l->quantity,
                'unit_price' => $l->rate,
                'discount_type' => 'flat',
                'discount_value' => $l->discount,
                'valid_discount_amount' => $l->discount,
                'taxable_value' => round((float) $l->amount - (float) $l->tax, 2),
                'gst_percentage' => (float) ($lineTax[$l->id] ?? 0),
                'gst_amount' => $l->tax,
                'line_total' => $l->amount,
                'created_at' => $s->created_at ?? $s->date_time,
                'updated_at' => $s->created_at ?? $s->date_time,
            ];
        }
        $this->db->table('invoice_items')->insert($items);

        $payments = [];
        foreach ($receipts as $r) {
            $payments[] = [
                'invoice_id' => $invoiceId,
                'transaction_id' => 'OLD-PAY-' . $r->id,
                'payment_date' => $r->receipt_date,
                'amount' => $r->amount,
                'payment_method' => self::PAYMENT_METHOD[(int) $r->payment_method_id] ?? 'other',
                'reference_number' => $r->receipt_id ? "OLD-RCPT-{$r->receipt_id}" : null,
                'notes' => trim('Old CRM: ' . (self::PAYMENT_METHOD_LABEL[(int) $r->payment_method_id] ?? 'payment') . ($r->remarks ? " — {$r->remarks}" : '')),
                'created_by' => $this->staffOrFallback($r->entry_by),
                'created_at' => $r->created_at ?? $r->receipt_date,
                'updated_at' => $r->updated_at ?? $r->receipt_date,
            ];
        }
        if ($payments) {
            $this->db->table('invoice_payments')->insert($payments);
        }

        $this->record('sale', $oldId, $invoiceId, 'invoices', 'created');
        $this->bump('invoices', 'created');
        $this->bump('invoices', 'items', count($items));
        $this->bump('invoices', 'payments', count($payments));
    }

    // ------------------------------------------------------------------
    //  Step: appointments
    // ------------------------------------------------------------------

    private function stepAppointments(): void
    {
        $duration = (int) config('legacy_import.appointment_duration_minutes', 30);
        $doctorByEstate = $this->old->table('users')->where('is_primary_doctor', 1)->whereNull('deleted_at')
            ->orderBy('id')->get(['id', 'estate_id'])->unique('estate_id')->pluck('id', 'estate_id')->all();
        $productNames = $this->old->table('products')->pluck('name', 'id')->all();
        $today = Carbon::today();

        $this->old->table('appointments')
            ->when($this->estates !== null, fn ($q) => $q->whereIn('estate_id', $this->estates))
            ->orderBy('id')->chunk($this->options['chunk'], function ($rows) use ($duration, $doctorByEstate, $productNames, $today) {
            $products = $this->old->table('appointment_products')->whereIn('appointment_id', $rows->pluck('id')->all())->get()->groupBy('appointment_id');

            $this->db->transaction(function () use ($rows, $products, $duration, $doctorByEstate, $productNames, $today) {
                foreach ($rows as $a) {
                    $oldId = (int) $a->id;
                    if (array_key_exists($oldId, $this->map['appointment'] ?? [])) {
                        $this->bump('appointments', 'already done');
                        continue;
                    }
                    $start = Carbon::parse($a->date_time);
                    $future = $start->gte($today);
                    $clinicId = $this->map['estate'][(int) $a->estate_id] ?? null;
                    $userId = $this->map['client'][(int) $a->client_id] ?? null;

                    $skip = match (true) {
                        $future && ! $this->options['include_future_appointments'] => 'future appointment (use --include-future-appointments)',
                        ! $clinicId => "estate {$a->estate_id} not mapped",
                        ! $userId => "client {$a->client_id} not imported",
                        default => null,
                    };
                    if ($skip) {
                        $this->record('appointment', $oldId, null, null, 'skipped', $skip);
                        $this->bump('appointments', 'skipped');
                        continue;
                    }

                    $doctor = isset($doctorByEstate[$a->estate_id]) ? ($this->map['staff'][(int) $doctorByEstate[$a->estate_id]] ?? null) : null;
                    $used = ($products[$a->id] ?? collect())->map(fn ($p) => [
                        'product_id' => $this->map['product'][(int) $p->product_id] ?? null,
                        'name' => isset($productNames[$p->product_id]) ? preg_replace('/\s+/', ' ', trim($productNames[$p->product_id])) : null,
                    ])->values()->all();

                    $id = $this->db->table('appointments')->insertGetId([
                        'type' => 'other',
                        'clinic_id' => $clinicId,
                        'user_id' => $userId,
                        'therapist_id' => $doctor ?? $this->staffOrFallback($a->created_by),
                        'start_datetime' => $start->toDateTimeString(),
                        'end_datetime' => $start->copy()->addMinutes($duration)->toDateTimeString(),
                        'duration_minutes' => $duration,
                        'status' => $future ? 'confirmed' : 'completed',
                        'products_used' => $used ? json_encode($used) : null,
                        'source' => 'system',
                        'notes' => "Imported from old CRM (appointment #{$oldId})",
                        'is_emergency' => false,
                        'is_billable' => false,
                        'is_billed' => false,
                        'created_by' => $this->staffOrFallback($a->created_by),
                        'created_at' => $a->created_at ?? $a->date_time,
                        'updated_at' => $a->updated_at ?? $a->date_time,
                    ]);
                    $this->record('appointment', $oldId, $id, 'appointments', 'created');
                    $this->bump('appointments', 'created');
                }
            });
            ($this->log)('progress', 'appointments up to old id ' . $rows->last()->id);
        });
    }

    // ------------------------------------------------------------------
    //  Step: loyalty opening balances
    // ------------------------------------------------------------------

    private function stepLoyalty(): void
    {
        // Default source is "client": clients.points_balance is the balance the old CRM showed and
        // let clients redeem — it never ran an expiry job and PointsLedger::redeem only checks
        // points_balance, so ledger expires_on dates were never enforced. "ledger" carries only
        // points whose expiry date has not passed.
        $today = Carbon::today()->toDateString();
        // Remaining points per client from the old FIFO ledger whose expiry date is still ahead.
        $ledger = $this->old->table('points_ledgers')
            ->where('balance', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', $today))
            ->groupBy('client_id')
            ->selectRaw('client_id, SUM(balance) as points, MIN(expires_on) as first_expiry')
            ->get()->keyBy('client_id');
        $clients = $this->old->table('clients')
            ->get(['id', 'points_earned', 'points_spent', 'points_expired', 'points_balance'])->keyBy('id');

        $source = $this->options['loyalty_source'];
        $ids = $source === 'client'
            ? $clients->filter(fn ($c) => $c->points_balance > 0)->keys()
            : $ledger->keys();

        foreach ($ids->chunk($this->options['chunk']) as $chunk) {
            $this->db->transaction(function () use ($chunk, $ledger, $clients, $source) {
                foreach ($chunk as $oldClientId) {
                    $oldClientId = (int) $oldClientId;
                    if (array_key_exists($oldClientId, $this->map['loyalty_opening'] ?? [])) {
                        $this->bump('loyalty', 'already done');
                        continue;
                    }
                    $c = $clients[$oldClientId] ?? null;
                    $ledgerPoints = (int) ($ledger[$oldClientId]->points ?? 0);
                    $points = $source === 'client' ? (int) ($c->points_balance ?? 0) : $ledgerPoints;
                    $userId = $this->map['client'][$oldClientId] ?? null;

                    if (! $userId || $points <= 0) {
                        $this->record('loyalty_opening', $oldClientId, null, null, 'skipped', $userId ? 'no points' : 'client not imported');
                        $this->bump('loyalty', 'skipped');
                        continue;
                    }

                    $current = (int) $this->db->table('users')->where('id', $userId)->lockForUpdate()->value('loyalty_points');
                    $txId = $this->db->table('loyalty_point_transactions')->insertGetId([
                        'user_id' => $userId,
                        'invoice_payment_id' => null,
                        'type' => 'earn',
                        'points' => $points,
                        'balance_after' => $current + $points,
                        'description' => "Opening balance carried over from old CRM (client #{$oldClientId})",
                        'metadata' => json_encode([
                            'source' => 'legacy_import',
                            'legacy_client_id' => $oldClientId,
                            'old_points_earned' => (int) ($c->points_earned ?? 0),
                            'old_points_spent' => (int) ($c->points_spent ?? 0),
                            'old_points_expired' => (int) ($c->points_expired ?? 0),
                            'old_points_balance' => (int) ($c->points_balance ?? 0),
                            // ledger points whose old expiry date has not passed yet (the old CRM never enforced expiry)
                            'old_unexpired_points' => $ledgerPoints,
                            'old_first_expiry' => $ledger[$oldClientId]->first_expiry ?? null,
                        ]),
                        'created_by' => $this->fallbackUserId,
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ]);
                    $this->db->table('users')->where('id', $userId)->update([
                        'loyalty_points' => $current + $points,
                        'opt_for_loyalty' => true,
                    ]);
                    $this->record('loyalty_opening', $oldClientId, $txId, 'loyalty_point_transactions', 'created', "{$points} points");
                    $this->bump('loyalty', 'balances carried');
                    $this->bump('loyalty', 'points', $points);
                }
            });
        }
    }

    // ------------------------------------------------------------------
    //  Verification (read-only)
    // ------------------------------------------------------------------

    /**
     * @return list<array{string, string, string, string}> rows of [check, old, new, result]
     */
    public function verify(): array
    {
        $this->loadState();
        $rows = [];
        $add = function (string $label, $old, $new) use (&$rows) {
            $rows[] = [$label, (string) $old, (string) $new, (string) $old === (string) $new ? 'OK' : 'DIFF'];
        };

        foreach ($this->map['estate'] ?? [] as $estate => $clinicId) {
            if (! $clinicId) {
                continue;
            }
            $oldSales = $this->old->table('sales')->where('estate_id', $estate)->whereNull('deleted_at');
            $newInv = $this->db->table('invoices')->where('clinic_id', $clinicId)->where('invoice_type', config('legacy_import.invoice_type'))
                ->where('status', '!=', 'cancelled');
            $add("estate {$estate} -> clinic {$clinicId}: sales count", (clone $oldSales)->count(), (clone $newInv)->count());
            $add("estate {$estate} -> clinic {$clinicId}: sales total", number_format((float) (clone $oldSales)->sum('total'), 2, '.', ''), number_format((float) (clone $newInv)->sum('grand_total'), 2, '.', ''));
            $add("estate {$estate} -> clinic {$clinicId}: GST total", number_format((float) (clone $oldSales)->sum('tax'), 2, '.', ''), number_format((float) (clone $newInv)->sum('gst_total'), 2, '.', ''));
            $oldPaid = $this->old->table('sale_receipts')->join('sales', 'sales.id', '=', 'sale_receipts.sale_id')
                ->where('sales.estate_id', $estate)->whereNull('sales.deleted_at')->sum('sale_receipts.amount');
            $newPaid = $this->db->table('invoice_payments')->joinSub($newInv->select('id'), 'li', 'li.id', '=', 'invoice_payments.invoice_id')->sum('amount');
            $add("estate {$estate} -> clinic {$clinicId}: payments total", number_format((float) $oldPaid, 2, '.', ''), number_format((float) $newPaid, 2, '.', ''));
        }

        $mapCounts = $this->db->table('legacy_import_map')->groupBy('entity', 'action')->selectRaw('entity, action, COUNT(*) n')->get();
        foreach ([['client', 'clients'], ['staff', 'users'], ['product', 'products'], ['sale', 'sales'], ['appointment', 'appointments']] as [$entity, $oldTable]) {
            $handled = (int) $mapCounts->where('entity', $entity)->sum('n');
            // sales / appointments are only read for in-scope clinics; the rest are read in full
            $total = $this->old->table($oldTable)
                ->when(in_array($entity, ['sale', 'appointment'], true) && $this->estates !== null, fn ($q) => $q->whereIn('estate_id', $this->estates))
                ->when($entity === 'sale' && ! $this->options['include_deleted_sales'], fn ($q) => $q->whereNull('deleted_at'))
                ->count();
            $add("{$entity}: old rows handled (created+matched+skipped)", $total, $handled);
        }
        foreach ($mapCounts as $m) {
            $rows[] = ["map {$m->entity} / {$m->action}", '', (string) $m->n, ''];
        }

        $carried = (int) $this->db->table('loyalty_point_transactions')->whereIn('id',
            $this->db->table('legacy_import_map')->where('entity', 'loyalty_opening')->where('action', 'created')->select('new_id'))->sum('points');
        $rows[] = ['loyalty points carried over', '', (string) $carried, ''];

        // Opening stock: old balance_quantity of the products that got a purchase vs the units purchased.
        $stockMap = $this->db->table('legacy_import_map')->where('entity', 'opening_stock')->where('action', 'created')->pluck('new_id', 'old_id');
        if ($stockMap->isNotEmpty()) {
            $oldQty = (int) $this->old->table('products')->whereIn('id', $stockMap->keys()->all())->sum('balance_quantity');
            $newQty = (int) $this->db->table('purchase_items')->whereIn('purchase_id', $stockMap->values()->all())->sum('quantity');
            $add('opening stock units (imported goods)', $oldQty, $newQty);
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    public function stats(): array
    {
        return $this->stats;
    }

    public function issues(): array
    {
        return $this->issues;
    }

    private function record(string $entity, int $oldId, ?int $newId, ?string $table, string $action, ?string $note = null): void
    {
        // Upsert so a --retry-skipped run can overwrite an earlier "skipped" row.
        $this->db->table('legacy_import_map')->upsert([[
            'entity' => $entity,
            'old_id' => $oldId,
            'new_id' => $newId,
            'new_table' => $table,
            'action' => $action,
            'note' => $note !== null ? Str::limit($note, 250) : null,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]], ['entity', 'old_id'], ['new_id', 'new_table', 'action', 'note', 'updated_at']);
        $this->map[$entity][$oldId] = $newId;
    }

    private function bump(string $step, string $key, int $by = 1): void
    {
        $this->stats[$step][$key] = ($this->stats[$step][$key] ?? 0) + $by;
    }

    private function issue(string $step, int|string $oldId, string $issue): void
    {
        $this->issues[] = ['step' => $step, 'old_id' => $oldId, 'issue' => $issue];
    }

    private function assignRole(int $userId, string $role): void
    {
        $this->db->table('model_has_roles')->insertOrIgnore([
            'role_id' => $this->roleIds[$role],
            'model_type' => \App\Models\User::class,
            'model_id' => $userId,
        ]);
    }

    private function indexUser(int $id, ?string $mobileKey, ?string $email, ?string $code): void
    {
        if ($mobileKey) {
            $this->mobileIndex[$mobileKey] ??= $id;
        }
        if ($email) {
            $this->emailIndex[$email] ??= $id;
        }
        if ($code) {
            $this->referralIndex[$code] = true;
        }
    }

    private function staffOrFallback($oldUserId): int
    {
        return ($oldUserId ? ($this->map['staff'][(int) $oldUserId] ?? null) : null) ?? $this->fallbackUserId;
    }

    /** @var array<int, string> */
    private array $hsnCache = [];

    private function productHsn(int $productId): ?string
    {
        return $this->hsnCache[$productId] ??= (string) $this->db->table('products')->where('id', $productId)->value('hsn_sac_code');
    }

    /** @var array<int, array<string, mixed>> */
    private array $snapshotCache = [];

    /** Product fields for invoice_items.product_snapshot (keys as InvoiceItem::snapshotOf). */
    private function productSnapshot(int $productId): array
    {
        return $this->snapshotCache[$productId] ??= (array) $this->db->table('products')->where('id', $productId)
            ->first(['id', 'name', 'sku', 'type', 'hsn_sac_code', 'sell_price', 'gst', 'description']);
    }

    /** @return array<int, array{city: string, state_code: ?string}> */
    private function oldCities(): array
    {
        static $cache = null;

        return $cache ??= $this->old->table('cities')->join('states', 'states.id', '=', 'cities.state_id')
            ->get(['cities.id', 'cities.name as city', 'states.name as state'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => [
                'city' => $r->city,
                'state_code' => self::STATE_CODES[mb_strtolower(trim(str_replace('&', 'and', $r->state)))] ?? null,
            ]])->all();
    }

    private function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $cfg = config('legacy_import.decrypt');
        $plain = openssl_decrypt($value, $cfg['cipher'], $cfg['key'], 0, $cfg['iv']);

        return $plain === false ? null : trim($plain);
    }

    /** Last 10 digits of an Indian mobile, or null if it is not one. */
    private function mobileKey(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if (strlen($digits) > 10 && (str_starts_with($digits, '91') || str_starts_with($digits, '0'))) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    private function cleanEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function validDate($date): ?string
    {
        if (! $date || str_starts_with((string) $date, '0000')) {
            return null;
        }

        return $date;
    }

    private function normName(?string $name): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim(str_replace('&', 'and', (string) $name))));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'clinic';
        $slug = $base;
        for ($i = 2; $this->db->table('clinics')->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
