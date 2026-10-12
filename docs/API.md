# API contract (implemented routes only)

Base path: `/api/v1`. All JSON responses use `{ "ok": boolean, "data"?: object, "error"?: { "code": string, "message": string } }`. Error responses do not expose traces. Browser sessions use an HttpOnly cookie. Configure exact trusted origins with `CORS_ALLOWED_ORIGINS`.

| Method | Path | Auth | Purpose/status |
|---|---|---|---|
| GET | `/health` | Public | API and database health; 503 if DB is unavailable/unconfigured. Integrations explicitly marked unverified/disabled. |
| GET | `/hotspot/packages` | Public | Lists enabled Hotspot plans with price, speed, validity and optional data cap. Read-only; only configured plans are shown. |
| POST | `/staff/login` | Public, rate-limited | Staff `username`/password login (`firstname.lastname`); only active users assigned a non-Customer role can create a staff session. |
| GET | `/staff/me` | Staff session | Revalidates the active staff identity; returns username, full name, role names, assigned permission names, and CSRF token. |
| POST | `/staff/logout` | Staff session + `X-CSRF-Token` | Invalidates staff session. |
| POST | `/portal/otp/request` | Public, rate-limited | Request PPPoE-only OTP; `202` generic response whether account exists or not. Enqueues notification for an unimplemented worker. |
| POST | `/portal/otp/verify` | Public, rate-limited | Verify `{ "account_number": "0252230001", "otp": "123456" }`; single-use 5-minute challenge; successful response creates secure session cookie. |
| GET | `/portal/me` | PPPoE customer session | Returns exact signed-in account summary, up to 50 account-scoped payment/receipt/ticket entries, plus CSRF token. Requires active DB data. |
| POST | `/portal/logout` | PPPoE customer session + `X-CSRF-Token` | Invalidates session. |
| OPTIONS | `/api/v1/*` | Allowlisted origin | CORS preflight, allowed methods GET/POST. |

Hotspot package item fields: `id`, `name`, `speed_down_kbps`, `speed_up_kbps`, `price_minor`, `currency`, `validity_days`, and nullable `data_cap_bytes`. This endpoint does not initiate a purchase, authenticate a captive-portal user, bind a device, or prove payment.

OTP request body: `{ "account_number": "0252230001" }`. OTP must be six numeric digits. Request responses are generic to reduce account enumeration; this service does not claim SMS was delivered. OTP worker/provider are not implemented/confirmed, so successful request only means a queued delivery request was accepted. The challenge hash is stored separately; the queued delivery value uses authenticated AES-256-GCM with a key derived from `APP_KEY`. With no working notification worker, the OTP cannot be delivered and OTP login cannot complete.

## Not implemented; do not call or assume

Staff operational RBAC/resource scoping and every staff action endpoint, customer/plans/subscription CRUD, Hotspot purchase/authentication/device binding, PPPoE account creation and credential reset, payment creation/checkout, Snipper/ClickPesa webhooks/status lookup/refunds, cash receipts/reconciliation, usage/session details, tickets/work orders CRUD, provider/system settings, RADIUS sync/CoA, router management, audit UI, queue worker/retries/dead-letter execution, and OLT/ONU. Staff login/profile only establish and report identity/permissions; no dashboard operation is active yet. No payment/webhook endpoint is defined until provider docs are confirmed. The browser UI must only use implemented/documented routes.
