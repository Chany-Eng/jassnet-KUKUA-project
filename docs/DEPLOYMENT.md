# Deployment, backup, recovery, and rollback runbook

## Current release warning

This repository is a development scaffold. It does not implement staff authentication/RBAC enforcement, customer/service management, payment creation or provider webhook verification, provisioning/RADIUS workers, cashier operations, email/SMS delivery workers, or OLT/ONU integration. Do not deploy as a production billing/network-control system and do not enable live charging. See [INTEGRATION_STATUS.md](INTEGRATION_STATUS.md).

## Local/staging installation

1. Record OS, PHP and database versions; install supported PHP 8.1+ extensions `pdo_mysql`, `json`, `session`, `openssl`, and `sodium`; configure Apache rewrite and TLS.
2. Create an empty utf8mb4 MySQL/MariaDB database and least-privilege DB user. The API user requires only the reviewed application schema privileges; use a separate migration principal for DDL.
3. Place the web document root at `backend/public/`. Store the environment file and all secrets outside that root. Set a unique random `APP_KEY` (32+ characters), DB settings, HTTPS cookie settings and exact `CORS_ALLOWED_ORIGINS`.
4. Apply migrations in ascending order, once, after reviewing them: `001_initial_schema.sql`, `002_operations_and_access.sql`, `003_rate_limits_and_history.sql`. Take a database backup before DDL. Some `ALTER TABLE` statements are not rerunnable; do not blindly reapply a partially completed migration. Record checksums/results with the operator.
5. Serve `frontend/` as static content. Configure `window.JASSNET_API_BASE_URL` in the deployment page before loading the API client to point to the published API. Use HTTPS on both origins. Avoid wildcard CORS and do not allow credentials from untrusted origins.
6. Run PHP syntax checks and `backend/tests/health_test.php`, inspect application logs, confirm `/api/v1/health`, and test origin allowlisting. OTP delivery requires an independently reviewed notification worker and confirmed SMS provider; current queue stores OTP payload encrypted with the application key.
7. Seed no privileged users until staff login, recovery, MFA, and role/permission enforcement are implemented and reviewed.

## Release and rollback checklist

- Approve a change record, test plan, rollback triggers, maintenance window, and named operator.
- Back up application source/config (excluding secrets in routine artifacts), database, RADIUS configuration/database, router configuration exports, WireGuard configuration, DNS/firewall/TLS settings, and provider configuration using encrypted storage with access logs.
- Restore backups to an isolated staging environment and record evidence, duration, and data consistency. A backup not restore-tested is not an accepted recovery plan.
- Apply migration on staging; run API/frontend tests; review logs for secrets; test customer isolation, OTP expiry/reuse/rate limits, webhook idempotency/signature, paid-but-provisioning-failed handling, duplicate activation prevention, and role checks before enabling relevant features.
- For network integration, validate the exact RADIUS SQL schema and configuration, test auth/accounting on one lab/canary router, verify CoA/disconnect behavior if needed, monitor errors, and stop before wider rollout if evidence is incomplete. Do not push to all ~10 routers at once.
- Roll back application code when health/errors or data-integrity thresholds fail. For schema changes, use a reviewed forward fix or restore procedure; avoid destructive rollback after new writes. Reconcile payment provider records before retrying payment/provisioning operations.
- After any incident, preserve audit/webhook/job logs, reconcile money and service state, rotate affected credentials, and document recovery evidence.

## Backups and monitoring to configure

Define encrypted backup destination, database and config cadence, retention, offsite copy, key custody, RPO/RTO and restore owner. Alert on backup failure/age, DB/API health, queue backlog/dead jobs, OTP/SMS delivery failures, payment events needing review, reconciliation differences, RADIUS sync failures, router availability, and disk/certificate/time-sync issues. Never expose detailed traces or secret-bearing payloads on public health endpoints.

## Operational gaps

No deployment, backup, restore, rollback, router canary, provider sandbox, DNS/TLS, firewall, WireGuard, or production smoke-test evidence has been provided or run in this workspace. This runbook is an operator checklist, not proof of integration.

## Hotspot captive portal readiness

`frontend/hotspot/index.html` is the package catalog page and `GET /api/v1/hotspot/packages` reads enabled Hotspot plans. Configure real Hotspot plans before expecting cards to appear. Checkout is intentionally disabled until an approved Snipper/ClickPesa product, official checkout/status/signature docs and sandbox tests exist. Deploy the public path `/hotspot/`, verify HTTPS and its walled-garden assets, then use the router-specific steps in [HOTSPOT_SETUP.md](HOTSPOT_SETUP.md); do not configure a router redirect based only on the URL.
