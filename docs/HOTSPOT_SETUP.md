# MikroTik HotSpot portal setup status

## Planned customer-facing URL

User-selected target: `https://jassnet.co.tz/hotspot/`. This is **not live or configured on a router yet**. The current frontend has no deployed `/hotspot/` captive-login page, and the backend has no HotSpot authentication/device-binding endpoint. Do not paste this URL into production router profiles until the page, DNS, TLS, backend flow, and one-router test are ready.

If the selected page is served over ordinary HTTPS, browsers normally connect to TCP 443 and the URL omits `:443`. That is only the web-host port—not a confirmed MikroTik HotSpot listener, RADIUS port, RouterOS management port, or API setting. Actual router profile/server ports and login method must be read from the target router. Do not expose Winbox/RouterOS API to the public Internet.

## Required evidence before configuration

Provide a sanitized copy of:

- `/system resource print` and `/system package print` (model and RouterOS version).
- `/ip hotspot print detail` and `/ip hotspot profile print detail` (server/profile, interface, `dns-name`, `html-directory`, `login-by`, and any configured HTTP proxy/login options).
- `/ip service print` only to confirm configured management services/ports; redact addresses, users and all secrets.
- Whether the router should host the captive page or redirect to the external `https://jassnet.co.tz/hotspot/` page.
- DNS A/AAAA and valid TLS certificate status for the chosen site, plus the intended router/site test window. Do not send passwords, RADIUS shared secrets, WireGuard private keys, or customer data.

Before a redirect is enabled, deploy the captive page and its documented router hand-off, allow only the required portal assets through the HotSpot walled garden, validate the router login method and HTTPS behavior, and test authentication/accounting on one canary router. Then check binding against trusted RADIUS/router accounting—not MAC values posted by the browser. Stop if the route, certificate, listener, return path, or RADIUS behavior does not match the verified configuration.

Common RADIUS authentication/accounting ports (often UDP 1812/1813) and RouterOS API service ports are separate protocols; they are not substitutes for the captive portal URL and must not be assumed to match this deployment.
