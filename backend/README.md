# JASSNET backend

PHP/MySQL API scaffold. `public/` is the only web document root; `src/`, `database/`, and `tests/` must not be served directly. Keep secrets in an untracked `.env` or server environment, never in source control or frontend files.

## Local setup

1. Use a supported PHP 8.1+ build with `pdo_mysql`, `json`, `session`, and `openssl` enabled, plus MySQL/MariaDB.
2. Create a database named `jassnet_billing` with `utf8mb4` and apply `database/migrations/001_initial_schema.sql` after reviewing it.
3. Copy `.env.example` to a private `.env` outside the public document root and set database values. Do not commit `.env`.
4. Configure PHP/Apache to load environment variables and use this directory's `public/` folder as the document root. The example assumes XAMPP URL `/project/backend/public`.
5. Visit `/api/v1/health`. It reports database readiness; integrations remain explicitly unverified/disabled.

## Current scope and blockers

This is a separated backend scaffold, not yet a production-ready billing API. Only `GET /api/v1/health` is implemented. There is no authentication/authorization middleware, OTP dispatch/verification, payment checkout/webhook implementation, provisioning worker, RADIUS/RouterOS integration, queue, or customer/ticket API yet. Do not expose it as a live billing service until these are implemented and tested.

Snipper, ClickPesa, SMS, SMTP, FreeRADIUS, routers, WireGuard and OLT/ONU integrations are intentionally disabled/unverified pending inventory and confirmed official documentation, access, test environment, and approval. Never assume the reported infrastructure addresses or credentials.

## Design invariants

- Keep account numbers as strings so leading zeroes are preserved.
- Treat payment, provisioning, and notification state as independent fields.
- Do not trust browser redirects, client amounts/roles/customer IDs, screenshots, or client-supplied MAC addresses.
- Verify payment signatures and/or provider status before marking a payment paid; use idempotency and reconciliation.
- Store OTP hashes, expire challenges, enforce resend cooldown and attempt/rate limits, and use generic responses.
- Scope authorization server-side by role and site/router. The frontend is never an authorization boundary.

See the root README for the route map, integration matrix, and deployment/rollback checklist.
