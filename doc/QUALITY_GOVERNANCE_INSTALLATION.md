# MediStore – Quality, Safety & Product Governance

This patch adds the quality/safety and product roadmap capabilities to the existing MediStore installation. It intentionally uses domain-based names. No database table or controller is named `StageSeven*`.

## Installation

1. Back up the application and database.
2. Extract this patch into the Laravel project root, preserving folders.
3. Run:

```bash
php artisan migrate
php artisan db:seed --class=PharmacyGovernanceSeeder
php artisan optimize:clear
npm run build
```

No `.env`, WhatsApp, mail, storage credentials, Composer files or lock files are included or modified. No new package is required.

## Database tables

- `medication_safety_incidents`
- `quality_improvement_actions`
- `controlled_documents`
- `controlled_document_revisions`
- `compliance_audits`
- `compliance_audit_items`
- `product_roadmap_milestones`
- `product_roadmap_tasks`

## Professional controller names

- `MedicationSafetyController`
- `DocumentGovernanceController`
- `ComplianceAuditController`
- `ProductRoadmapController`

## API routes

### Medication safety

- `GET /api/quality/overview`
- `GET /api/quality/incidents`
- `POST /api/quality/incidents`
- `GET /api/quality/incidents/{incident}`
- `PATCH /api/quality/incidents/{incident}/review`
- `POST /api/quality/incidents/{incident}/actions`
- `PATCH /api/quality/actions/{action}`
- `POST /api/quality/incidents/{incident}/close`

### Controlled documents

- `GET /api/quality/documents`
- `POST /api/quality/documents`
- `POST /api/quality/documents/{document}/revisions`
- `POST /api/quality/documents/{document}/approve`
- `POST /api/quality/documents/{document}/retire`
- `GET /api/quality/documents/{document}/revisions/{revision}/download`

Documents are stored on Laravel's private `local` disk. A revision must be approved before non-admin users can download it.

### Compliance audits

- `GET /api/quality/audits`
- `POST /api/quality/audits`
- `PATCH /api/quality/audits/{audit}/items/{item}`
- `POST /api/quality/audits/{audit}/close`

A non-compliant finding requires a corrective action. An audit cannot be closed while checklist items remain pending.

### Product roadmap

- `GET /api/product-roadmap`
- `PATCH /api/product-roadmap/milestones/{milestone}`
- `PATCH /api/product-roadmap/tasks/{task}`

## Frontend pages

- `/quality-governance` – medication safety, document control and audits.
- `/product-roadmap` – overall product progress and remaining releases (admin only).

## Seeder and progress baseline

Run:

```bash
php artisan db:seed --class=PharmacyGovernanceSeeder
```

The seeder is idempotent. It marks releases 1–6 completed because the project owner has confirmed those releases are working, marks release 7 in progress, and leaves releases 8–9 planned. Initial overall roadmap progress is 66.7% (6 of 9 releases). Release 7 progress increases as its roadmap tasks are completed. This is a project tracking estimate, not a certification or claim that production readiness testing has passed.

## Roles

- Admin: all quality, document, audit and roadmap functions.
- Branch manager: branch-scoped incidents, documents and audits.
- Pharmacist: report/review incidents and view/submit controlled documents.
- Cashier: report a safety incident; no access to document approval or audit administration.

## Operational notes

- Record only the minimum customer-identifying information required for a safety investigation.
- Store controlled documents on private storage and configure private backups in the deployment environment.
- Do not use the incident register as a substitute for emergency clinical escalation or local regulatory reporting.
- Review the applicable pharmacy and data-protection requirements in each operating jurisdiction before enabling the workflows in production.
