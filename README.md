# MediStore – Medical Store Management System
**Laravel 11/12 (REST API) · Vue 3 SPA (Vue Router + Pinia) · MySQL · Bootstrap 5 · SweetAlert2 · Axios (AJAX)**

---------------------------------------------------------------------
## 1. Install (fresh Laravel project)

```bash
composer create-project laravel/laravel medistore
cd medistore
php artisan install:api            # installs Sanctum + personal_access_tokens migration
```

Copy everything from this folder into the project (same paths, overwrite):

```
app/Models/*                         app/Services/StockService.php        app/helpers.php
app/Http/Controllers/Api/*           app/Http/Resources/*                 app/Http/Requests/*
app/Http/Middleware/RoleMiddleware.php                                    app/Providers/AppServiceProvider.php
bootstrap/app.php                    routes/api.php   routes/web.php
database/migrations/*                database/seeders/DatabaseSeeder.php
resources/views/app.blade.php        resources/js/**  resources/css/app.css
vite.config.js                       package.json
```

`.env`
```
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medistore
DB_USERNAME=root
DB_PASSWORD=
CACHE_STORE=file        # redis = fastest
```

```bash
php artisan migrate:fresh --seed
npm install
npm run build          # or: npm run dev  (hot reload while developing)
php artisan serve
```
Open http://127.0.0.1:8000 → **admin@medistore.test / password** or **cashier@medistore.test / password**

Production: `php artisan config:cache route:cache view:cache` and `composer install --optimize-autoloader --no-dev`.

---------------------------------------------------------------------
## 2. Project structure

```
app/
 ├─ Http/Controllers/Api/   Auth, Lookup, Dashboard, Category, Manufacturer, Supplier, Customer,
 │                          Medicine, Purchase, Pos, Sale, SaleReturn, PurchaseReturn,
 │                          StockAdjustment, Alert, Report, Setting, User
 ├─ Http/Resources/         JSON API Resources (what the API returns)
 ├─ Http/Requests/          Form Request validation (Medicine, Purchase, Checkout, SaleReturn, PurchaseReturn)
 ├─ Http/Middleware/        RoleMiddleware (role:admin / role:admin,cashier)
 ├─ Models/                 19 Eloquent models
 └─ Services/StockService   the ONLY place stock changes (batches + total + ledger)
database/migrations/        MySQL tables with indexes
routes/api.php              REST API (Sanctum token)
routes/web.php              SPA catch-all → resources/views/app.blade.php
resources/js/
 ├─ app.js  App.vue  api.js (axios + interceptors)  router/  stores/ (Pinia)
 ├─ components/  AppLayout, DataTable, Pagination, Modal, Autocomplete, CrudPage, PaymentModal
 └─ pages/       Dashboard, Pos, Medicines, Purchases…, returns/*, reports/*, print/*
```

---------------------------------------------------------------------
## 3. Complete flow

```
Company (GSK…)   Category (Tablet…)   Supplier (distributor)
        \              |                   |
         └──── MEDICINE (master) ──────────┤
                                           ▼
                   PURCHASE = STOCK IN  (batch + expiry + qty + bonus + cost + MRP)
                   → batches created, stock ↑, supplier payable ↑
                                           ▼
                   POS SALE  (FEFO: earliest expiry sold first, expired blocked)
                   → stock ↓, receipt, credit → customer due ↑
                                           ▼
   ┌──────────────────────┬────────────────────────┬──────────────────┬───────────────┐
   CUSTOMER RETURN        RETURN TO SUPPLIER        ADJUSTMENT          ALERTS / REPORTS
   stock ↑ (good only)    stock ↓, payable ↓        expired/damaged     low stock, expiry,
   refund / due ↓                                    lost / correction   daily closing, profit
```

---------------------------------------------------------------------
## 4. RETURNS section (customer returns → stock back in)

Menu → **Returns → New Customer Return**

### A) With invoice (recommended)
1. Scan / type the invoice number from the receipt → **Find**.
2. Every sold item is shown with *Can return* qty (sold − already returned).
3. Enter return qty (or **Return all**) and choose condition per item:
   | Condition | Stock | Money |
   |-----------|-------|-------|
   | **Good**  | ✅ added back to the **same batch** (sellable again) | refunded |
   | **Damaged** | ❌ not added (loss) | refunded |
   | **Expired** | ❌ not added (loss) | refunded |
   > If the batch is already expired, even "Good" items are not restocked.
4. Optional **deduction / restocking fee**, **reason** (quick chips), **refund method** (cash / card / online / no refund).
5. Refund price = net price paid (invoice discount & tax shared proportionally).
6. If the invoice was on **credit**, the customer's **due is reduced first**, remaining amount is refunded.
7. **Save Return** → SweetAlert confirm → stock updated → **print credit note**.

### B) Without invoice (customer lost the receipt)
1. Optional customer (search or type name).
2. Search the medicine → pick the **batch** (all batches shown, expired marked) → price auto-filled from batch → qty → condition.
3. Save → good items go back to that batch.

### Rules & safety
* Return period from Settings (`return_days`, 0 = no limit).
* Cannot return more than sold; each sale item tracks `returned_qty`.
* Every restock writes a `stock_movements` row (`sale_return`) → visible on the medicine stock card.
* Daily closing subtracts cash refunds from cash in hand; Sales & Profit report shows returns and non-restocked loss.

### Return to supplier
Menu → **Returns → Return to Supplier → New**: choose supplier → medicine → batch (qty available, expiry, supplier shown) → qty →
settlement **reduce payable** or **cash back** → stock removed from that batch, supplier ledger updated.

---------------------------------------------------------------------
## 5. API endpoints (all under /api, Bearer token)

| Method | URL | Role |
|---|---|---|
| POST | /login · /logout · GET /me | all |
| GET | /lookups | all |
| GET | /pos/products · POST /pos/checkout | all |
| GET | /medicines/search · /medicines/{id}/batches | all |
| GET/POST/PUT | /customers · /customers/{id}/sales · POST /customers/{id}/payments | all |
| GET | /sales · /sales/{id} · /sales/find?invoice_no= | all |
| GET/POST | /sale-returns · GET /sale-returns/{id} | all |
| GET | /alerts/counts · /alerts/low-stock · /alerts/expiry · /reports/daily | all |
| GET | /dashboard | admin |
| CRUD | /categories · /manufacturers · /suppliers · /medicines · /users | admin |
| GET | /suppliers/{id}/ledger · POST /suppliers/{id}/payments | admin |
| GET | /medicines/{id}/movements | admin |
| GET/POST/DELETE | /purchases | admin |
| DELETE | /sales/{id} (void) | admin |
| GET/POST | /purchase-returns · GET /purchase-returns/{id} | admin |
| GET/POST | /adjustments · POST /adjustments/write-off-expired | admin |
| GET | /reports/summary · /reports/stock | admin |
| GET/PUT | /settings | admin |

---------------------------------------------------------------------
## 6. Speed / low server load
* SPA: Blade shell loads once; every page is a **lazy-loaded Vue chunk**; only JSON travels after that.
* **Server-side pagination** everywhere; POS uses `simplePaginate` (no COUNT query).
* **Debounced search (250–300 ms) + AbortController**: old requests are cancelled.
* **Cache**: settings forever, alert counts 5 min, dashboard 2 min – auto-cleared on any stock change.
* `medicines.total_stock` cached column; MySQL indexes on all filter/sort columns; `sale_date` / `return_date` DATE columns for reports.
* Transactions + `lockForUpdate()` → two cashiers can never sell / return the same unit twice.
* Alert polling every 2 min, paused when the browser tab is hidden.

## 7. Keyboard (POS)
F1 open POS · F2 search · ↑↓←→ select · Enter add · F4 hold · F8 clear · F9 pay
