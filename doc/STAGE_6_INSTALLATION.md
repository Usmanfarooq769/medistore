# MediStore Stage 6 – Installation & Operations

Stage 6 adds online order requests, delivery tracking, cold-chain temperature records, supplier contracts/performance and event history. It extends the existing Stage 4/5 Laravel + Vue application.

## Files and configuration

- Extract this patch into the existing project root, preserving paths.
- No `.env`, WhatsApp, mail, storage, Composer, npm or lock files are included or changed.
- No new Composer or npm package is required.
- The public storefront endpoints are API endpoints; a separate customer-facing storefront UI/payment gateway is not included in this patch.

## Install

Back up the project and database first, then run:

```bash
php artisan migrate
php artisan db:seed --class=StageSixReferenceSeeder
php artisan optimize:clear
npm run build
```

To rerun all reference seeders from Stage 4, 5 and 6:

```bash
php artisan db:seed --class=StageFourFiveSixSeeder
```

Seeders are idempotent. Stage 6 seeding inserts only default settings; it does not invent temperature devices, supplier contracts, online orders, deliveries, sales or financial records.

## Stage 6 modules

### 6.1 Online order requests and delivery

Public endpoints (rate limited):

- `GET /api/storefront/branches`
- `GET /api/storefront/catalog?branch_id=1&search=paracetamol`
- `POST /api/storefront/orders`

Example request:

```json
{
  "branch_id": 1,
  "customer_name": "Test Customer",
  "customer_phone": "03001234567",
  "delivery_address": "Test delivery address",
  "channel": "web",
  "items": [
    { "medicine_id": 1, "quantity": 2 }
  ]
}
```

The public catalog excludes prescription-only and controlled medicines. Order requests are not sales and do not reserve or deduct inventory. Staff must confirm the request, create the actual sale in the existing POS, link the POS sale ID, then assign a delivery. Delivery cannot be assigned without a linked POS sale.

Staff endpoints are under `/api/stage6` and require Sanctum authentication. Branch scoping uses the existing `BranchContext` and `X-Branch-ID` handling.

### 6.2 Cold-chain monitoring

- Register devices with minimum and maximum temperature thresholds.
- Record manual readings against the device.
- Out-of-range readings create an open excursion.
- Pharmacist/manager must record investigation and corrective action, then acknowledge or resolve it.

This patch records temperature excursions; it does not automatically quarantine medicine batches. Staff must assess affected stock under the applicable cold-chain SOP and quarantine it using the existing stock controls where necessary. Hardware sensor ingestion requires a trusted device gateway and authentication design specific to the selected sensor vendor.

### 6.3 Supplier contracts and performance

- Register supplier contract numbers, dates, lead days, discount terms and status.
- View supplier purchase totals and outstanding balances for the active branch and selected date period.
- A daily command marks active contracts expired after their end date.

Run manually:

```bash
php artisan store:expire-supplier-contracts
```

The existing Laravel scheduler runs it daily at 00:20. Ensure the server's standard scheduler cron is already active.

### 6.4 Event history

The `stage_six_event_logs` table records order review/linking, delivery updates, cold-chain device creation/excursions/actions and supplier contract changes. It is an operational event history, not a replacement for the existing audit log.

## Testing order

1. Use a test branch with non-prescription/non-controlled stock.
2. Query the public catalog and submit a test online order.
3. Confirm the order from the Stage 6 page.
4. Create a test POS sale in the normal POS, then link its sale ID.
5. Assign a driver and test assigned → picked_up → delivered.
6. Register a cold-chain device (e.g. 2–8 °C), record 5 °C, then 10 °C; document and resolve the excursion.
7. Create a draft supplier contract and test the expiry command on a test record.

Do not use fake temperature readings or sample contracts as live operational records.
