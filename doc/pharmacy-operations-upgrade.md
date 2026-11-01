# MediStore Pharmacy Operations Upgrade

## Included

- Medicine pack size and a base-stock-unit flag. New inventory is stored in individual tablets/units.
- Purchase entry uses packs and pack prices; the backend converts received quantities and batch prices to base units.
- POS supports selling either complete packs or individual units. USB/Bluetooth barcode scanners that behave like keyboards can scan directly into the POS search field.
- Sale lines preserve the entered selling unit and pack-size snapshot. FEFO batch allocation, sale returns, supplier returns, stock adjustments, stock valuation and profitability use base units.
- Legacy-stock conversion with an explicit choice: existing quantities were entered as packs, or were already individual units. This also converts the linked historical sale/return and stock-ledger values when pack quantities are selected.
- Cashier shift opening, cash in/out, expected drawer balance, counted cash, and shortage/excess reconciliation.
- Batch-wise gross-profit report, append-only audit trail for important record changes, CSV medicine template/import, and scheduled daily super-admin email report.

## Apply the update

1. Back up the production database before applying any migration or legacy-stock conversion.
2. Copy the ZIP contents into the Laravel project root, preserving folders and replacing the listed existing files.
3. Run:

   ```bash
   php artisan migrate
   php artisan optimize:clear
   npm install
   npm run build
   ```

4. Configure SMTP in `.env` and enter the daily report recipient(s) in Settings.
5. Add the Laravel scheduler to the server cron (Linux hosting):

   ```cron
   * * * * * cd /path/to/medistore && php artisan schedule:run >> /dev/null 2>&1
   ```

6. Test the report manually:

   ```bash
   php artisan store:daily-report
   ```

## Unit-conversion rules

- `medicines.pack_size` is the number of individual units in one pack.
- `medicine_batches.quantity`, `medicines.total_stock`, stock movements, sale item quantities and return quantities are base individual units.
- Medicine master purchase/sale prices and purchase-entry prices are per pack. Batch cost and selling prices are stored per individual unit with four decimal places.
- Set pack size before the first purchase whenever possible.
- For existing medicines that have stock or history, use the **Convert legacy stock** action on the Medicines page. Choose **Packs / strips** only if old quantities and old sale/return records were entered as packs. Choose **Individual tablets / units** if they were already individual units. The conversion is transactional and audited, but is intentionally not reversible; take a database backup first.
- A cashier must open a shift before completing POS sales. Admin users may use POS without opening a shift, or open a shift to reconcile their own drawer.

## Operational notes

- Barcode scanning supports common USB/Bluetooth HID scanners (they type the barcode and press Enter). Camera-based scanning and barcode-label printing are not included in this upgrade.
- Daily email time uses the Laravel application's configured timezone. Confirm `APP_TIMEZONE` / server timezone before relying on the 23:59 report schedule.
- The batch profitability report is gross product profit after proportional invoice discount allocation, before rent, salaries, utilities and other operating expenses.
- The audit trail records selected master/transaction changes and cashier shift open/close events. It is not a substitute for secure off-site database backups.
