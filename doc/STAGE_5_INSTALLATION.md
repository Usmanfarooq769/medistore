# MediStore Stage 5.1–5.4

Stage 5 extends the Stage 4 Laravel 12 / Vue 3 / Sanctum application. It does not replace `.env`, WhatsApp, mail, storage, Composer or npm lock files.

## Included

- 5.1 Supplier quotations and comparison, conversion of an accepted quote to a draft purchase order, branch warehouse locations, batch/bin allocation, and approved physical stock counts.
- 5.2 Healthcare facility directory, Sanctum facility API tokens for external prescription intake, pharmacist-reviewed prescription refill requests and prescription event history.
- 5.3 Insurance eligibility records and claim event history, controlled-medicine register, recall management and batch quarantine.
- 5.4 Customer campaign templates with explicit WhatsApp sends to selected opted-in customer IDs, refill reminders, PWA push-subscription registration endpoint, monthly sales seasonality, transparent demand forecasting and forecast snapshots.

## Install

Back up the database and project, extract this patch over the Stage 4 project, then run:

```bash
php artisan migrate
php artisan optimize:clear
npm run build
```

No new Composer or npm package is required.

## Seed reference data

Stage 5 only:

```bash
php artisan db:seed --class=StageFiveReferenceSeeder
```

Stage 4 and Stage 5 reference data together:

```bash
php artisan db:seed --class=StageFourFiveSeeder
```

Both seeders are idempotent. They do not create users, sales, stock, claims, settlements or journal entries. Demo facilities and campaign templates are placeholders; campaigns remain in `draft` and no messages are sent.

## Access

Open `/stage5`. Admin and branch managers can manage purchasing, warehouse, facilities, insurance records, recalls, campaigns and forecasts. Pharmacists can manage refill reviews and controlled medicine records. Cashiers can access the limited read-only dashboard and submit refill requests through authenticated API workflows.

## Important operational limits

- Supplier quotations are internal quote records. Email/EDI supplier integrations are not included; attach actual supplier quotes and verify terms before approval.
- Insurance eligibility records are staff-confirmed audit records. They do not query payer networks without a separately contracted payer API.
- Forecasting uses a transparent historical daily-average model, not a machine-learning guarantee.
- The mobile workflow is the existing installable PWA and authenticated API, not a native APK. Push subscription registration is provided; a Web Push VAPID sender requires deployment-specific VAPID keys and a configured delivery service. Customers must explicitly opt in using the Stage 5 marketing-consent endpoint before campaign messages are sent. Opted-out customers are skipped. WhatsApp campaigns require an approved Meta template with two body variables and `WHATSAPP_CAMPAIGN_TEMPLATE` in the server environment. Add these optional settings to the live server `.env` only when you are ready to send campaigns and refill reminders:

```env
WHATSAPP_CAMPAIGN_TEMPLATE=your_approved_campaign_template
WHATSAPP_REFILL_TEMPLATE=your_approved_refill_template
```

The campaign template body should contain two text variables: customer name and campaign message. The refill template body should contain two text variables: customer name and medicine name. Do not reuse the low-stock alert template unless its approved template structure exactly matches. The scheduler sends due reminders daily at 10:00 to opted-in customers; ensure your Laravel scheduler cron is already running.
- Controlled-medicine register entries are audit records; users must enter them against the legally required source documents. Do not treat the software as a substitute for local regulatory requirements.
- Recall batch quarantine is enforced in POS stock scopes. Quarantined stock remains blocked until an administrator explicitly releases the affected batch.


## Healthcare facility API integration

1. Register a facility in `/stage5` and select **API token** beside that facility.
2. Store the returned token securely at the hospital/clinic system. It is displayed only once.
3. Submit prescriptions using the facility-specific endpoint below. The prescription is created as `pending` and must be reviewed by a pharmacist before dispensing.

```http
POST /api/integrations/facilities/{facility_id}/prescriptions
X-Facility-Token: {facility_api_token}
Accept: application/json
Content-Type: application/json
```

Example JSON body:

```json
{
  "customer_id": 1,
  "doctor_name": "Dr. Example",
  "doctor_license": "LICENSE-001",
  "prescription_date": "2026-11-03",
  "notes": "Please review",
  "items": [
    { "medicine_id": 1, "quantity": 10, "dosage": "As directed", "duration": "5 days" }
  ]
}
```

## Customer marketing consent

Before campaign sending, record consent only after the customer has explicitly opted in:

```http
PATCH /api/stage5/customers/{customer_id}/marketing-consent
Authorization: Bearer {admin_or_branch_manager_token}
Content-Type: application/json

{ "opted_in": true }
```

To withdraw consent, send `opted_in: false`. The campaign sender skips customers without consent.
