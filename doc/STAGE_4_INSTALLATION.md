# MediStore Stage 4 – Installation & Operations

This is an incremental patch for the supplied MediStore Laravel 12 / Vue 3 project.
It does not include `vendor/`, `node_modules/`, `.env`, or production credentials.

## Included

1. Head-office branch consolidation and `branch_manager` role.
2. Doctor, clinic, clinic-doctor relationships, referrals and prescription linking.
3. Manufacturer Buy-X-Get-Y schemes applied automatically during direct purchases and purchase-order receiving; distributor/manual bonus quantities remain supported.
4. Supplier expiry claims, approval, stock return movements and supplier credit notes.
5. Insurance providers, plans, medicine-level coverage rules, customer panel fields, claims and partial settlement history.
6. Branch-level sales analytics, monthly seasonality, 30-day revenue/gross-profit estimates and suggested purchase plans.
7. Installable mobile web app (PWA) with branch-aware mobile dashboard and existing POS/sales workflows.
8. Optional read-only AI business assistant using an OpenAI API key.
9. API and role integration.

## Apply patch

1. Back up the project and database.
2. Extract this patch into the existing project root, preserving folders.
3. Do not replace your live `.env`.
4. Add the optional AI settings below to your existing server `.env` only if you want AI:
   ```
   OPENAI_API_KEY=your-server-side-api-key
   OPENAI_MODEL=gpt-4o-mini
   ```
   The key is never sent to the browser. WhatsApp, mail and storage settings are untouched.
5. Run:
   ```
   composer install
   npm ci
   php artisan migrate
   php artisan optimize:clear
   npm run build
   ```

No new Composer or npm package is required by Stage 4. The existing `composer.json`, `composer.lock`, `package.json` and `package-lock.json` are unchanged.

## Roles

- `admin`: head office access, all-branch consolidated reporting and all branch controls.
- `branch_manager`: assigned-branch purchasing, supplier, stock adjustment, reporting and Stage 4 operational modules. Assign a branch when creating the user.
- `pharmacist`: branch POS/mobile workflows and prescription review; branch assignment is required.
- `cashier`: mobile dashboard, POS, and existing cashier permissions. AI questions are restricted to the user's assigned branch.

To create a branch manager, open Users and choose **Branch Manager**. Assign the correct `branch_id`.

## Main screens

- `/stage4` – Stage 4 operations (Head Office tab is admin-only).
- `/mobile` – installable mobile dashboard.
- `/purchases/create` – select an active Buy-X-Get-Y manufacturer scheme on a purchase line. The free quantity is added to batch stock without increasing the supplier bill value.
- `/customers` – admins and branch managers can assign an insurance provider, plan and member number.

## API prefix

All new endpoints are under `/api/stage4`:

- `GET chain/overview`
- `GET mobile/overview`
- `GET,POST,PUT,DELETE doctors`
- `GET,POST,PUT clinics`
- `GET,POST referrals`
- `PATCH prescriptions/{prescription}/doctor`
- `GET,POST,PUT schemes`
- `POST schemes/preview`, `POST schemes/redeem`, `GET schemes/profitability`
- `GET,POST expiry-claims`
- `POST expiry-claims/{claim}/submit|review|credit`
- `GET,POST,PUT insurance-providers`
- `POST insurance-plans`, `POST insurance-coverages`
- `GET,POST insurance-claims`
- `POST insurance-claims/{claim}/submit|review|settle`
- `GET analytics`
- `POST ai/ask`

## Operational notes

- Expiry return claims reserve their batches while submitted or approved. Crediting a claim removes the approved batch quantities through `StockService`, records stock movements, creates a supplier credit note, reduces the supplier payable balance and posts a balanced accounting journal. This release approves full claim quantities only; partial quantity approvals are intentionally blocked to avoid stock/credit mismatches.
- Buy-X-Get-Y scheme free units are added to stock automatically on purchase receipt. Existing manual `bonus_qty` remains supported. Scheme discounts are stored and previewable; they are not automatically applied to purchase prices in this release.
- Insurance claims are a separate claims subledger calculated from the sale items and plan coverage rules. Co-pay and insurer-covered values are recorded; they do not rewrite the original POS sale or customer receivable. Reconcile the claim and customer copay according to your insurer contract. Partial insurer settlements are individually recorded.
- The forecast is a transparent sales run-rate / historical-margin estimate, not a machine-learning guarantee. Suggested purchase quantities subtract non-expired stock and approved incoming purchase orders.
- The mobile app is a responsive, installable PWA that uses the same authenticated APIs and branch restrictions. The service worker caches only the public application shell/assets; it never caches API/customer/financial data. Checkout requires connectivity so stock and payment state cannot be duplicated by offline synchronization.
- The AI assistant is read-only, sends only a limited authorized-branch business summary to the configured provider, and must not be used for clinical decisions. It does not execute SQL, mutate records, approve claims or dispense medicines.

## Validation performed

PHP syntax checks and Vue `<script>` syntax checks were run on the project. A full Laravel database migration, authenticated API integration test, and Vite production build were not run in this isolated environment because the supplied archive intentionally excludes `vendor/` and `node_modules/`. Run the install commands above on your local copy after backup.
