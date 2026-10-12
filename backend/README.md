# JASSNET backend

PHP/MySQL API scaffold. `public/` is the only web document root; `src/`, `database/`, and `tests/` must not be served directly. Keep secrets in an untracked `.env` or server environment, never in source control or frontend files.

## Local setup

1. Use a supported PHP 8.1+ build with `pdo_mysql`, `json`, `session`, and `openssl` enabled, plus MySQL/MariaDB.
2. Create a database named `jassnet_billing` with `utf8mb4`; review and apply migrations `001_initial_schema.sql`, `002_operations_and_access.sql`, `003_rate_limits_and_history.sql` in order. Some DDL in migrations 002/003 is one-time only; snapshot first and do not blindly rerun partial migrations.
3. Copy `.env.example` to a private environment file outside the public document root. Set DB values, a unique random `APP_KEY` (32+ characters), exact `CORS_ALLOWED_ORIGINS`, and `SESSION_SECURE_COOKIE=true` for HTTPS. Do not commit `.env`.
4. Configure PHP/Apache to load environment variables and use this directory's `public/` folder as the document root. Enable rewrite. The example assumes XAMPP URL `/project/backend/public`.
5. Visit `/api/v1/health`; it reports database readiness. See [../docs/API.md](../docs/API.md) for the implemented routes.

## Current scope and blockers

This is a separated development backend, not a production-ready billing API. Implemented routes are health, staff login/profile/logout, PPPoE OTP request/verify, customer account summary, and logout. Staff login is rate-limited and reports database-assigned roles/permissions; it does not make staff CRUD or dashboard operations available. MFA-enabled staff accounts are denied because no MFA verification flow exists. OTP challenges are hashed, expire in five minutes, have rate/attempt controls and create a secure session; queued OTP values are encrypted with AES-256-GCM using a key derived from `APP_KEY`. However, the SMS provider and queue worker are not implemented, so OTP cannot currently be delivered. Customer/service CRUD, account creation, payment checkout/webhook verification, cash/refund operations, provisioning/RADIUS workers, support writes, and monitoring are not implemented. Schema tables alone are not those features. Do not expose as a live billing system.

Snipper, ClickPesa, SMS, SMTP, FreeRADIUS, routers, WireGuard and OLT/ONU integrations are intentionally disabled/unverified pending inventory and confirmed official documentation, access, test environment, and approval. Never assume the reported infrastructure addresses or credentials.

## Design invariants

- Keep account numbers as strings so leading zeroes are preserved.
- Treat payment, provisioning, and notification state as independent fields.
- Do not trust browser redirects, client amounts/roles/customer IDs, screenshots, or client-supplied MAC addresses.
- Verify payment signatures and/or provider status before marking a payment paid; use idempotency and reconciliation.
- Store OTP hashes, expire challenges, enforce resend cooldown and attempt/rate limits, and use generic responses.
- Scope authorization server-side by role and site/router. The frontend is never an authorization boundary.

See [../docs/API.md](../docs/API.md), [../docs/INTEGRATION_STATUS.md](../docs/INTEGRATION_STATUS.md), and [../docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md) for the route map, blockers, and operational checklist.
