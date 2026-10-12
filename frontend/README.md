# JASSNET frontend

Responsive Swahili-language frontend for the JASSNET Hotspot & PPPoE Billing System. This directory contains only browser-facing assets; credentials and authorization belong on the backend.

## Run locally

Open `index.html` in a browser, or serve this directory with Apache/XAMPP. The default API base URL is `http://localhost/project/backend/public/api/v1`; configure `window.JASSNET_API_BASE_URL` before `assets/js/app.js` when deploying to a different API host.

The portal uses `POST /portal/otp/request`, `POST /portal/otp/verify`, `GET /portal/me`, and `POST /portal/logout` as documented in [../docs/API.md](../docs/API.md). It displays generic OTP request feedback because the SMS provider/worker is not yet implemented; successful request acceptance does not prove that an SMS was delivered. The portal does not store OTPs or credentials in local storage. Do not place API secrets in this directory.

The separate staff entry page is `staff.html`; it authenticates against the staff session routes, shows only permission-labelled module placeholders, and logs out through the backend. The backend has not implemented staff operations, so those cards are intentionally disabled placeholders—not working dashboards. Payments, receipt downloads, HotSpot management, tickets/work orders, and settings likewise have no active operations API.
