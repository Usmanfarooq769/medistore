# MediStore Professional Pharmacy Modules

This patch is designed to be applied **on top of the previously installed `medistore_complete_pharmacy_upgrade.zip`** (the package that introduced base-tablet stock, FEFO, cashier shifts, batch profitability, audit logs and CSV import).

## Included modules

1. Camera barcode scanning in POS (browser `BarcodeDetector`) and Code 128 barcode label printing.
2. Daily WhatsApp Cloud API low-stock / expiry digest using an approved message template.
3. Supplier purchase orders with draft-to-approval-to-receiving workflow. Receiving creates ordinary purchase records and FEFO stock batches; a draft or approved order does not increase stock.
4. Prescription uploads, review/approval, and checkout enforcement for medicines marked **Prescription required**.
5. Multiple branches, admin branch selector, branch-specific batch stock, branch transfers, branch-aware POS, purchases, returns, adjustments, alerts and reports.
6. Encrypted off-site database backup to S3-compatible storage, backup history and a guarded restore command.
7. Tax profiles (exclusive/inclusive) selected for the sale, plus tax summary reporting.
8. Operating expenses, income statement, cash-flow summary, balance sheet from posted journals, trial balance and balanced manual journal entry posting.

## Apply files and dependencies

1. Take a full database backup before applying the migration.
2. Copy this ZIP into the existing MediStore project root, preserving folders and replacing only included files.
3. Install the S3 filesystem adapter and JS barcode library:

   ```bash
   composer update league/flysystem-aws-s3-v3
   npm install
   ```

4. Run:

   ```bash
   php artisan migrate
   php artisan optimize:clear
   npm run build
   ```

5. Ensure the previous operations upgrade is already installed. This patch builds on its cashier-session, pack conversion and stock-service tables/fields.

## WhatsApp Cloud API

Set these `.env` values:

```env
WHATSAPP_ACCESS_TOKEN=your_meta_permanent_access_token
WHATSAPP_PHONE_NUMBER_ID=your_whatsapp_business_phone_number_id
WHATSAPP_GRAPH_VERSION=v22.0
WHATSAPP_ALERT_PHONE=923001234567
WHATSAPP_ALERT_TEMPLATE=medicine_stock_alert
WHATSAPP_TEMPLATE_LANGUAGE=en_US
```

Create and get approval for a WhatsApp Business **utility template** named `medicine_stock_alert` with two body text variables: low-stock medicine count and expired/expiring batch count. The scheduled command sends a template message (not a free-form text message), which is appropriate for a proactive scheduled notification. Phone number must use international digits without `+` or spaces. Test manually:

```bash
php artisan store:whatsapp-stock-alerts
```

## Encrypted cloud backups

Configure an S3 or S3-compatible bucket:

```env
BACKUP_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_ENDPOINT=
```

The backup command encrypts the database dump using AES-256-GCM with a key derived from `APP_KEY`. **Keep the same `APP_KEY` safe and backed up**: encrypted backup files cannot be restored if the key is lost or changed.

Test:

```bash
php artisan store:backup-cloud
```

Restore only after stopping users from working and taking a separate current backup:

```bash
php artisan store:restore-cloud "medistore-backups/YYYY/MM/backup-file.sql.enc" --force
php artisan optimize:clear
```

Restore is destructive. The encrypted object path must be copied exactly from the Backups screen. MySQL requires `mysqldump` and `mysql` client utilities on the server; SQLite requires the database file to be accessible to the PHP process.

## Scheduler

Keep the existing Laravel scheduler cron entry:

```cron
* * * * * cd /path/to/medistore && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs added by this patch:

- 09:00 — WhatsApp stock/expiry digest.
- 02:00 — encrypted cloud database backup.
- Existing 23:59 — daily operations email report.

Times use the Laravel application timezone. Set `APP_TIMEZONE` appropriately.

## Branch setup

- Existing stock and users are assigned to the new **Main Branch** during migration.
- Admin users can select an active branch in the top navigation. The selected branch is sent as `X-Branch-ID` on API calls.
- Cashiers are always restricted to the branch assigned to their user record; they cannot override it with the browser header.
- Assign a user to a branch using Users → Branch ID (the ID is visible in the branch records / database). Main Branch is ID `1` on a fresh installation.
- Branch transfers create a pending request. Admin approval atomically deducts the source batch and creates a destination batch with the same batch number, expiry and cost snapshots.
- Medicine master data and supplier/customer master balances are shared across branches; physical stock, purchases, sales, returns, adjustments and reports are branch-scoped. Customer and supplier payments are applied to the active branch's outstanding invoices; switch branches before collecting another branch's balance.

## Prescription workflow

1. Open Medicines and edit the medicine.
2. Enable **Prescription required** for regulated/restricted medicines according to local law and store policy.
3. Upload the prescription in Prescriptions, include the medicine, and submit for review.
4. An admin approves or rejects it.
5. At POS, checkout for a restricted medicine asks for the approved `RX-...` number. The server validates its status and that the restricted medicine is listed before deducting stock.

The software provides workflow controls only; the pharmacy remains responsible for verifying the prescription, prescriber and applicable local dispensing rules.

## Accounting / tax notes

- Tax profiles support inclusive and exclusive tax calculation for a sale. Existing manual tax percentage remains available when no profile is selected.
- Tax report compares recorded sales tax with recorded purchase tax for the selected branch and period. Confirm local tax treatment with your accountant.
- Income statement uses operational sales, returns, batch cost snapshots and approved expenses.
- Cash flow summary uses cash sales, customer cash receipts, supplier cash payments, cash refunds and cash expenses.
- New sales, purchases, customer/supplier payments, sales/purchase returns, operating expenses and stock adjustments automatically create balanced general-ledger entries. Voiding a sale/purchase or deleting an expense creates a reversing journal rather than deleting posted history. Inter-branch transfers create matching inter-branch receivable/payable balance-sheet entries and do not create a profit/loss journal.
- Balance sheet and trial balance use posted journal entries. Manual journals must balance (total debit = total credit). Enter verified opening balances as a manual journal before relying on the balance sheet. Historical transactions created before this upgrade are not automatically backfilled; the operational income statement continues to calculate historical sales, returns, batch COGS, expenses and stock adjustments directly. Have your accountant reconcile opening balances and tax treatment before statutory reporting.

## Verification checklist

- Test camera scanning on HTTPS or localhost with a supported Chromium browser; a USB/Bluetooth HID scanner remains available as fallback.
- Print a small barcode label test and verify the printed scanner reads the same barcode value.
- Test PO submit, approve, receive, and verify branch stock and supplier due.
- Test prescription pending → approved → restricted POS checkout; reject an unapproved prescription and a prescription missing a required medicine.
- Create two branches, receive stock in one, transfer a batch, and confirm source/destination batch quantities.
- Test WhatsApp using an approved template and verify the log status.
- Test backup upload, download the encrypted object through the storage console, and perform a restore rehearsal on a non-production copy.
- Test tax inclusive/exclusive arithmetic, expenses, P&L, cash flow, trial balance and balanced journal posting.

Run your application's PHPUnit suite and Vite build before production deployment. This package has static syntax validation only; live Meta/S3 credentials, database integration and production workflows must be verified in your environment.
