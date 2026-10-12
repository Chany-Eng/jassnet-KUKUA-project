# JASSNET frontend

Responsive Swahili-language frontend for the JASSNET Hotspot & PPPoE Billing System. This directory contains only browser-facing assets; credentials and authorization belong on the backend.

## Run locally

Open `index.html` in a browser, or serve this directory with Apache/XAMPP. The default API base URL is `http://localhost/project/backend/public/api/v1`; configure `window.JASSNET_API_BASE_URL` before `assets/js/app.js` when deploying to a different API host.

The PPPoE OTP form calls `POST /portal/otp/request`. It is not a working login until that backend endpoint and a confirmed SMS provider have been configured. Do not place API secrets in this directory.
