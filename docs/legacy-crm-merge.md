# Merging the old CRM into this CRM

`php artisan legacy:import` copies the old Lumen CRM (`clinic-management`, MariaDB dump
`production_backup.sql`) into this database. Because this database is **production**, the import:

- **only inserts rows.** The only changes to existing rows are adding the legacy loyalty opening
  balance to `users.loyalty_points`, and filling `referred_by` / `total_referrals` on users the
  import itself created.
- **never uses Eloquent models**, so no stock is decremented, no loyalty points are awarded, no
  WhatsApp messages are sent and no expenses are created. Old invoices get one block of `INV-{STATE}-{n}` numbers, reserved after the current counter.
- **dry-runs by default.** Nothing is written without `--commit`.
- **is re-runnable.** Every row is recorded in `legacy_import_map` (old id → new id) in the same
  transaction, so a second run skips what was already done and continues after a failure.
- **can be undone** with `php artisan legacy:rollback`.

---

## 1. Restore the old dump into a SEPARATE database

> ⚠️ **Never restore `production_backup.sql` into the CRM database.** The dump starts each table
> with `DROP TABLE IF EXISTS`, and it contains `users`, `clinics`, `products`, `purchases`,
> `appointments`, `roles` and `permissions`. Restoring it into the CRM database **wipes those
> production tables**.

```bash
mysql -u root -p -e "CREATE DATABASE skingenious_legacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p skingenious_legacy < production_backup.sql
```

The first line of the dump (`/*M!999999\- enable the sandbox mode */`) is understood by MariaDB
11.x. A MySQL client rejects it, so delete that line first if you restore into MySQL.

`api_logs` is about 2 GB of the dump and the import does not need it.

## 2. Configure `.env` (never commit these values)

```dotenv
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=skingenious_legacy
LEGACY_DB_USERNAME=...
LEGACY_DB_PASSWORD=...

# From the OLD server's .env, copied exactly as they are (old app/helpers.php encrypt_ssl()).
LEGACY_CRM_APP_KEY=...
LEGACY_CRM_CIPHER_METHOD=...
LEGACY_CRM_IV=...
```

Client and staff mobiles and emails are AES-encrypted in the old database. Without these three
values the import cannot match clients, so preflight stops.

Then run `php artisan config:clear`.

## 3. Rehearse on a copy of production first

1. Restore a recent backup of production into a local or staging database and point `DB_*` at it.
2. Run `php artisan migrate --path=database/migrations/2026_10_12_000001_create_legacy_import_map_table.php`. This only adds the `legacy_import_map` table.
3. Run a dry run:

   ```bash
   php artisan legacy:import -v
   ```

   **Only AM Dermatology & Aesthetics (old estate 3) is imported by default**
   (`legacy_import.only_estates = [3]`). That means:

   - its sales and appointments;
   - the clients registered at it, or who have sales or appointments there (including clients
     registered at head office who visited it);
   - the staff and products it used.

   Other clinics are never created. Their data is recorded in `legacy_import_map` as
   `skipped / out of scope`.

   Preflight prints how each old clinic maps to a new one. AM Dermatology is matched to the new
   clinic of the same name. If the name differs in production, map it explicitly:

   ```bash
   php artisan legacy:import --clinic-map=3:<new_clinic_id>
   ```

   To include other clinics later, add them with `--only-estate=3 --only-estate=5`, or use
   `--all-estates`. A new clinic is only created if you also pass `--create-missing-clinics`,
   and it is created inactive.

4. Read the summary table and the issues CSV in `storage/logs/legacy-import/`.
5. Run with `--commit` on the copy, then compare totals:

   ```bash
   php artisan legacy:import --verify
   ```

6. Check the copy in the panel: client profiles, an invoice, a payment, a loyalty balance and
   the reports.

## 4. Production run

```bash
php artisan backup:run --only-db              # fresh backup first
php artisan down                              # optional: stop writes during the import
php artisan migrate --force --path=database/migrations/2026_10_12_000001_create_legacy_import_map_table.php
php artisan migrate --force --path=database/migrations/2026_10_13_000001_add_product_snapshot_to_invoice_items_table.php
php artisan invoices:backfill-product-snapshots --commit   # snapshot for invoice lines created before this release
php artisan products:regenerate-skus --commit             # name-based SKUs (e.g. DDHGS-P-0006) for existing products
php artisan legacy:import -v                  # dry run (or skip if rehearsed on a copy)
php artisan legacy:import --commit -v         # use the same --clinic-map / flags as the rehearsal
php artisan legacy:import --verify
php artisan up
```

If the run stops part-way, the chunks already committed stay in place. Fix the cause and run
the same command again, and it continues from where it stopped. Use `--step=` to run single
steps, which always run in dependency order.

## 5. Undo

```bash
php artisan legacy:rollback            # dry run: shows what would be removed / kept
php artisan legacy:rollback --commit
```

Rollback deletes only rows the import **created**. It keeps a created row if something added
after the import refers to it, for example:

- a new invoice for an imported client
- a payment added to an imported invoice
- a package usage

The kept rows are listed, and the loyalty opening balances are subtracted again.

---

## What goes where

| Old CRM | New CRM | Notes |
|---|---|---|
| `estates` + `clinics` | `clinics` | Mapped by `--clinic-map`, config `legacy_import.clinic_map`, or clinic name. Estates 1/2 (Website/Backend) mean "no clinic". Only estates in `only_estates` (default: 3, AM Dermatology) are imported. |
| `users` (staff) | `users` | `created_at` / `updated_at` keep the old values. Matched by mobile, then email. New staff are created **inactive** (use `--activate-staff` to change). Roles follow `legacy_import.role_map`; Admin/Superadmin get **no** role. The bcrypt password is kept. |
| `clients` + `client_medical_backgrounds` | `users` + role `client` | Matched on the last 10 digits of the mobile. A matched production user is **linked, never modified**. Old clients that share a mobile become one user. Lookup ids are turned into enum values. `remarks` is added to `other_diseases`. Clients get a random unusable password. |
| `clients.referrer_id` | `users.referred_by` | Only set on users the import created. |
| `products` | `products` | Matched by name and type, otherwise created **active** with `sku = LEGACY-{id}`. GST % = sum of the outward taxes. Membership becomes `service`. |
| `products.balance_quantity` (goods) | `purchases` + `purchase_items` + `clinic_inventories` | Step `stock`: one **opening-stock purchase** per goods product the import created (supplier "Old CRM (opening stock)"): quantity = `balance_quantity`, rate = `purchase_price` (excl. GST, like the purchase form), GST % = product GST, dated by the old `updated_at` (config `opening_stock_date`). Stock is added to the clinic's inventory. **No Expense** is created. Products deleted in the old CRM get no opening stock unless `--include-deleted-product-stock`. Matched (already existing) products are not touched. |
| `sales` | `invoices` | Numbered `INV-MH-{n}` like new invoices. Default `invoice_number_order = same_as_old`: the new number **equals the old bill number** (old 020 → `INV-MH-0020`, numbers of deleted old bills stay unused) — only possible while the `INV-MH` counter is still 0, otherwise preflight stops and you choose `old_number` (consecutive in old-bill order) or `date` (consecutive by bill date), which number from a block reserved right after the current `state_invoice_sequences` counter (e.g. counter at 4 → old invoices `INV-MH-0005`…`INV-MH-33279`, the next new invoice is `INV-MH-33280`). `old_invoice_number` = number on the original bill (shown as **Old Bill No.** on the invoice page and as a searchable column in the invoice list), `invoice_type = legacy`. Totals are copied as they are. Soft-deleted sales are skipped unless `--include-deleted-sales` (then they become `cancelled`). |
| `sale_products` (+ `sale_product_taxes`) | `invoice_items` | `unit_price` = old GST-inclusive rate. `gst_amount` / `line_total` are copied, not recalculated (the old GST rounding is kept). |
| `sale_receipts` | `invoice_payments` | `transaction_id = OLD-PAY-{id}`. Cheque, e-Wallet and Settlement become `other`, with the original method in `notes`. |
| `appointments` (+ `appointment_products`) | `appointments` | Past appointments only (future ones need `--include-future-appointments`). Status `completed`, type `other`, 30-minute duration, therapist = the clinic's old primary doctor. Not billable. Products go into `products_used`. |
| `clients.points_balance` (+ `points_ledgers` for reference) | `loyalty_point_transactions` + `users.loyalty_points` | One `earn` row per client carrying the balance the old CRM showed (`clients.points_balance`). The old CRM never enforced the ledger expiry dates (no expiry job; redeem checks only `points_balance`), so that balance was really redeemable. `--loyalty-source=ledger` carries only points whose expiry date has not passed. The new CRM has no point expiry. |
| `membership_subscriptions`, old `purchases` bills (stock comes from `balance_quantity` instead), `vendors`, `inventory_adjustments`, `salewise_shares`, `outstandings`, `follow_ups`, logs, oauth | — | Not imported. |

## Finding imported data

- Invoices: `invoice_type = 'legacy'` (original number in `old_invoice_number`); the reserved number block is the `invoice_block:{STATE}` row in `legacy_import_map`
- Any row: `legacy_import_map` (`entity`, `old_id`, `new_id`, `action` = created / matched / skipped, `note`)

## Invoice line snapshots

`invoice_items.product_snapshot` stores the product (name, sku, type, HSN/SAC, price, GST, description, `captured_at`) as it was when the line was invoiced — same idea as `user_package_items.service_snapshot`. `InvoiceItem` fills it automatically on create and when a line's product is changed. The invoice page, invoice PDF and client report show the name from the snapshot (`InvoiceItem::display_name`), so renaming a product later does not change old invoices.

- Old-CRM invoices: filled by the import, `captured_at` = old bill date.
- Invoices created before this release: `php artisan invoices:backfill-product-snapshots` (dry run) / `--commit`. Their true at-sale data no longer exists, so the product's current data is frozen and marked `"backfilled": true`. Only empty snapshots are written; safe to re-run.

## Product SKUs

SKUs are built from the product name by `App\Support\ProductSku`: initials of the name's words (numbers kept whole), the type letter (P = product, S = service, I = IV product) and the product id — `derma decode hair growth serum` #6 → `DDHGS-P-0006`, `Dermalight Injections 10 Sessions` → `DI10S-S-…`. New products get it automatically (ProductObserver), imported products get it during `legacy:import`, existing ones with `php artisan products:regenerate-skus` (dry run) / `--commit` (`--all` rebuilds name-based ones after a format change). The SKU is set once and does not change when a product is renamed; invoice/package snapshots keep the SKU they captured.
