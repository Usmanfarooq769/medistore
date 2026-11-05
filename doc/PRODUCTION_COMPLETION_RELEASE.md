# MediStore production-completion patch (based on original project + Patch 02)

This patch does not assume Product Completion Patch 01 was installed. It is built on the original uploaded Laravel 12 / Vue 3 project with `medistore_order_pos_return_flow_patch_02` overlaid.

## Included in this patch

- Optional SaaS multi-company architecture using a separate MySQL database per pharmacy tenant. This avoids relying on developers remembering `company_id` filters in every Eloquent and raw query. The database boundary isolates users, medicines, batches, stock, customers, orders, sales, purchases, accounting, settings and audit records.
- Single-company installations remain the default (`TENANCY_MODE=single`) and continue using the existing database and branch model.
- Domain-to-tenant resolver, encrypted tenant database passwords, tenant-specific cache prefixes, tenant-specific local upload roots, and tenant identity propagation to queued jobs.
- CLI tenant provisioning, domain verification and per-tenant migration commands.
- Customer self-service invoice return requests; staff approval/rejection; link the request to the existing Sale Return transaction after staff processes stock/accounting in the existing Returns module.
- Checkout idempotency key for public storefront requests to prevent duplicate online orders on client retries.
- Customer-selected cash-on-delivery / pay-at-pharmacy payment choice. Online card/wallet gateway processing is deliberately not represented as complete; it requires a selected provider and merchant credentials.
- Basic production configuration readiness command and a tenant-context unit test.

## A. Existing single-company installation

Keep the existing `.env` and database. Add:

```dotenv
TENANCY_MODE=single
```

Do not configure SaaS domains or a central database for single-company mode. Back up the database first, deploy the patch, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
php artisan test
php artisan app:readiness-check
```

## B. SaaS with multiple independent pharmacy companies

Each pharmacy company gets its own MySQL database. Branches remain inside that company's database. This means one company cannot query another company's business tables through normal app requests.

1. Create a separate central registry database, for example `medistore_central`.
2. Configure the central connection in the server `.env` (never commit this file):

```dotenv
TENANCY_MODE=saas
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medistore_central
DB_USERNAME=medistore_registry
DB_PASSWORD=REPLACE_WITH_SECRET
CENTRAL_DB_CONNECTION=mysql
CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_PORT=3306
CENTRAL_DB_DATABASE=medistore_central
CENTRAL_DB_USERNAME=medistore_registry
CENTRAL_DB_PASSWORD=REPLACE_WITH_SECRET
TENANCY_CENTRAL_DOMAINS=admin.example.com,localhost,127.0.0.1
```

3. Run the central registry migration:

```bash
php artisan migrate --database=central --path=database/migrations/central --force
```

4. Register an existing pharmacy database as the first tenant. Use the existing pharmacy database name and its dedicated DB user. The command prompts for the DB password if `--db-password` is omitted:

```bash
php artisan tenant:provision "Main Pharmacy" main shop.example.com existing_medistore_db --skip-create-db --skip-migrate
# Use the Tenant ID printed by the command in the next commands
php artisan tenants:migrate --tenant=1
php artisan tenant:import-local-files 1
```

5. For each new company, create a dedicated MySQL database and user, grant that user access only to that database, then run (the command will not create the database because it already exists):

```bash
php artisan tenant:provision "North Pharmacy" north north.example.com medistore_north --db-host=127.0.0.1 --db-user=medistore_north_user --skip-create-db
```

The command prompts for the database password and runs the application migrations in the new tenant database. The domain is disabled by default. Create the first administrator without using the demo seeder:

```bash
php artisan tenant:create-admin --tenant=2
```

Replace `2` with the tenant ID printed by the provisioning command. The command securely prompts for a 12+ character password. Do not run the demo `DatabaseSeeder` in production; it creates predictable sample accounts and sample pharmacy data.

6. Point each tenant domain's DNS to the application, configure TLS/HTTPS, then activate it (replace the examples with the registered domains):

```bash
php artisan tenant:verify-domain shop.example.com
php artisan tenant:verify-domain north.example.com
```

7. Scheduled pharmacy commands (daily reports, backups, refill reminders, webhook delivery, health checks and reservation expiry) are wrapped by `tenants:run` in SaaS mode so each tenant executes against its own database. The backup object path is tenant-prefixed. For recovery, specify `--tenant=ID` and a backup path under `tenants/ID/medistore-backups/`:

```bash
php artisan store:restore-cloud 'tenants/2/medistore-backups/2026/11/backup.enc' --tenant=2 --force
```

For later releases, migrate all active tenants:

```bash
php artisan tenants:migrate
```

Never run `migrate:fresh` against production databases. Take and verify backups before `tenants:migrate`.

## C. Tenant operations and isolation notes

- Tenant resolution is based on the exact request host and an active, verified domain in the central registry. Unknown domains are rejected.
- Each tenant has its own DB connection, Sanctum tokens, users, branches, stock, customers, sales and other application tables.
- The `central` connection is used only for tenant registry lookup/provisioning. Business queries use the tenant connection after middleware resolves the domain.
- Queue jobs dispatched during a tenant request carry the tenant ID and switch to that tenant DB before execution.
- The private `local` disk is rooted under `storage/app/tenants/{tenant_id}/private`. When converting an existing single-company installation to SaaS, copy its existing private uploads using `tenant:import-local-files ID` before routing customer traffic; the command retains the old shared source folder. Do not put private customer or prescription files on the shared public disk. If you use S3 or another object store, configure a tenant-specific bucket/prefix and test signed download URLs before enabling it; this patch does not automatically prefix arbitrary custom disks.
- Keep `APP_KEY` stable. Tenant DB passwords are encrypted using it. If the key is changed, decrypt/re-encrypt all central registry passwords before deployment.
- Configure allowed hostnames at the reverse proxy/web server. Do not trust arbitrary forwarded Host headers.
- Each tenant database user should have permissions only on its own database. The central registry DB user should not be reused as the tenant's application DB user.

## D. Customer return-request workflow

1. Customer signs into `/customer-portal`, opens an invoice and requests return quantities/reason.
2. Staff opens **Online Return Requests**, reviews and approves/rejects the request.
3. For an approved request, staff opens **Returns → New Customer Return**, finds the original invoice and processes the actual returned items/condition/refund using the existing Sale Return workflow.
4. Staff enters the resulting Sale Return ID on the request screen to link the request to the actual stock/accounting transaction.

A request alone never restores stock or issues a refund. The existing Sale Return transaction remains the only stock/accounting mutation path.

## E. Payments

The storefront now records `cash_on_delivery` or `pay_at_store` and keeps the payment status pending until the POS sale records the actual payment. There is no claim of card/wallet gateway support. Before offering online prepaid orders, select a provider (for example, a locally supported Pakistan payment gateway), obtain merchant credentials, implement its signed callback/verification API, idempotent transaction ledger, refund API and reconciliation, then pass provider sandbox and production tests. Never mark an order paid based only on browser input or a redirect query string.

## F. Required production verification (not optional)

Run in staging first, not against live customer data:

- `php artisan test` and `npm ci && npm run build`.
- Apply migrations to a production-like MySQL clone and verify rollback/restore procedure.
- Test two different tenant domains with same numeric IDs in their separate databases; confirm login tokens, medicine search, POS, customer portal, uploads, reports and queued notifications never cross tenants.
- Test tenant suspension, unknown domain, unverified domain and unavailable tenant database.
- Test queue worker restart after tenant-aware jobs; test failed-job retry.
- Test duplicate storefront checkout with the same `Idempotency-Key`; same payload must return same order number, different payload must return HTTP 409.
- Test stock race: two customers request the last batch units concurrently; only available quantity may be reserved.
- Test online order → confirmation → POS sale → invoice → delivery → partial return; reconcile stock ledger, batch quantity, customer due, refunds and journal entries.
- Test customer return request over-quantity, wrong invoice, expired return window, duplicate pending request and completion linked to a return from another invoice/branch.
- Test backup restore into a clean isolated server, not merely backup file creation.
- Run load tests on product search, POS checkout, reports and customer checkout using production-like data volumes.
- Review all permissions, secrets, HTTPS, CORS, file upload validation, rate limits, server firewall, DB grants, logging retention and incident response.

`php artisan app:readiness-check` checks deployment configuration, central registry availability and active tenant database connectivity/migration-table presence only. It does not certify security, business correctness, gateway settlement, performance or disaster recovery. Do not call the product production-ready until the full checklist passes.
