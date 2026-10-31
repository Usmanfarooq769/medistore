# Medicine CSV import guide

## Download the template

Open **Medicines** in MediStore and click **CSV Template**. The downloaded file contains the exact column names accepted by the importer. Open it in Excel or LibreOffice, keep the first row unchanged, add one medicine per row, and save as CSV UTF-8.

## Supported columns

| Column | Required | Description / example |
|---|---|---|
| `name` | Yes | Brand/product name, e.g. Panadol |
| `generic_name` | No | Generic formula, e.g. Paracetamol |
| `strength` | No | Strength, e.g. 500mg |
| `category` | No | Category name; created if it does not exist |
| `manufacturer` | No | Manufacturer/company name; created if it does not exist |
| `barcode` | No | Unique barcode; used as the preferred matching key |
| `unit` | No | Stock unit, e.g. strip, tablet, bottle, box |
| `pack_size` | No | Number of stock units in one pack; must be a positive integer |
| `purchase_price` | No | Default purchase cost, numeric and non-negative |
| `sale_price` | No | Default selling price, numeric and not below purchase price |
| `reorder_level` | No | Low-stock alert threshold, integer zero or greater |
| `rack` | No | Shelf/rack reference, e.g. A-01 |

## Import behaviour

- Existing medicines are matched by barcode. If no barcode is supplied, matching uses medicine name plus strength.
- A matched medicine is updated; a new medicine is created when no match exists.
- Blank optional fields do not clear existing values on an update.
- Category and manufacturer names are matched/created automatically.
- Import creates medicine master records only. It does not create stock, purchase batches, batch numbers, expiry dates, or supplier transactions.
- Add opening stock using **Purchases**, where batch number, expiry date, supplier, quantity, and batch purchase/sale prices can be recorded correctly.

## Before importing a large file

1. Keep a backup of the database.
2. Test with 5–10 rows first.
3. Ensure barcodes are unique and preserve leading zeros (set the barcode column to Text in Excel).
4. Do not rename the header row.
5. Review the created/updated/skipped counts and row-level error messages after import.
