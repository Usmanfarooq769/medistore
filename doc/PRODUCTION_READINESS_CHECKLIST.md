# MediStore Production Readiness Checklist

- [ ] Confirm production `APP_ENV`, `APP_DEBUG=false`, strong `APP_KEY`, HTTPS and secure cookie settings.
- [ ] Run `php artisan migrate --force` only after a tested database backup and deployment review.
- [ ] Confirm queue workers, scheduler and failed-job monitoring are active.
- [ ] Run `php artisan system:health-check` and resolve critical/warning findings.
- [ ] Verify encrypted off-site backups and perform a restore in an isolated staging environment.
- [ ] Test staff roles, branch scoping, customer portal invitation expiry and token revocation.
- [ ] Rotate partner credentials after any exposure; give each partner the minimum scopes required.
- [ ] Restrict webhook outbound network destinations at firewall/egress-proxy level.
- [ ] Configure and test payment gateway integration before treating subscription invoices as paid.
- [ ] Configure a real push sender/VAPID provider before promising push notification delivery.
- [ ] Run dependency vulnerability checks, static analysis, penetration testing and load/concurrency tests.
- [ ] Complete branch-level UAT, accounting reconciliation and documented rollback approval.
