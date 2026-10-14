<?php

/*
|--------------------------------------------------------------------------
| Legacy CRM import (old Lumen "clinic-management" database)
|--------------------------------------------------------------------------
|
| Used by `php artisan legacy:import` and `php artisan legacy:rollback`.
| See docs/legacy-crm-merge.md for the full runbook.
|
*/

return [

    // Database connection that holds the restored old dump (config/database.php).
    'connection' => 'legacy',

    // The old app encrypted mobile / email with openssl_encrypt($v, CIPHER_METHOD, APP_KEY, 0, IV).
    // Copy these three values VERBATIM from the old server's .env (never commit them).
    'decrypt' => [
        'key' => env('LEGACY_CRM_APP_KEY'),
        'cipher' => env('LEGACY_CRM_CIPHER_METHOD'),
        'iv' => env('LEGACY_CRM_IV'),
    ],

    /*
     | Which old clinics (estate ids) to bring over. Only their sales and appointments are
     | imported, plus the clients / staff / products that belong to them or are used by them.
     | Everything else is recorded as "out of scope" and other clinics are never created.
     | 3 = AM Dermatology & Aesthetics. Override with --only-estate=3 --only-estate=5, or
     | --all-estates to import every clinic.
     */
    'only_estates' => [3],

    /*
     | Old estate_id => new clinics.id.
     | Can also be passed as --clinic-map=3:1,9:2 (option wins over config).
     | Old estates without a mapping are matched by clinic name; if that fails the
     | import stops, unless --create-missing-clinics is given.
     | Estates 1 (Website) and 2 (Backend) are not clinics and map to "no clinic".
     */
    'clinic_map' => [
        // 3 => 1,   // AM Dermatology & Aesthetics
    ],

    // Imported invoices get invoice_type = legacy and keep the original bill number in
    // invoices.old_invoice_number (shown as "Old Bill No."). INV numbers: see below.
    'invoice_type' => 'legacy',

    // How old invoices are numbered:
    //  "same_as_old" = new number IS the old bill number (old 020 -> INV-MH-0020); bills not
    //                  imported leave unused numbers. Needs the INV-MH counter at 0 (empty
    //                  series) — otherwise preflight stops before anything is written.
    //  "old_number"  = consecutive after the current counter, in old bill number order.
    //  "date"        = consecutive after the current counter, oldest bill date first.
    'invoice_number_order' => env('LEGACY_INVOICE_NUMBER_ORDER', 'same_as_old'),

    // same_as_old on a CRM that already has INV-{STATE} invoices: move those invoices to after
    // the old block (old bill 001 = INV-MH-0001; existing INV-MH-0001 -> INV-MH-<max old + 1>, ...).
    // This CHANGES invoice numbers that may already be issued to customers — off by default.
    // Previous numbers are recorded and legacy:rollback restores them.
    'renumber_existing_invoices' => env('LEGACY_RENUMBER_EXISTING_INVOICES', false),

    // Step "stock": date of the opening-stock purchase made for each imported goods product.
    // updated_at = when the old balance_quantity last changed (default); created_at = product creation.
    'opening_stock_date' => 'updated_at',

    // Sales deleted in the old CRM come in as CANCELLED invoices (keeping their old bill number,
    // items and recorded payments). Reports skip cancelled invoices, so totals are unaffected.
    // Deleted sales without any items are still skipped.
    'include_deleted_sales' => true,

    // Appointments dated today or later come in as "confirmed" (no WhatsApp is sent).
    'include_future_appointments' => true,

    // Old appointments only have a start time.
    'appointment_duration_minutes' => 30,

    // Old role name => new role name (null = create the user without any role).
    // Admin / Superadmin are deliberately NOT mapped to super_admin.
    'role_map' => [
        'Superadmin' => null,
        'Admin' => null,
        'Doctor' => 'therapist',
        'Clinic Manager' => 'clinic_manager',
        'Clinic Staff' => 'clinic_manager',
        'Clinic Receptionist' => 'clinic_manager',
    ],

    // Staff who do not already exist in the new CRM are created with is_active = false
    // (no new logins appear on production). Use --activate-staff to override.
    'activate_new_staff' => false,

    // Rows per transaction.
    'chunk' => 500,
];
