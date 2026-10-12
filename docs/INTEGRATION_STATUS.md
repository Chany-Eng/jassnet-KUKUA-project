# Integration status matrix

**Status date:** 2026-10-12. No production host, credentials, provider documentation, router inventory, or connectivity evidence was supplied. Reported infrastructure values are unverified and are not used.

| Component | Endpoint/port | Direction | Authentication | Network path | Owner | Evidence/status |
|---|---|---|---|---|---|---|
| Public frontend | HTTPS 443 (target `jassnet.co.tz`) | Customer browser → static host | Public/TLS | Public Internet | JASSNET hosting owner | Target domain only; DNS/TLS not checked |
| Backend API | HTTPS 443 (target `apii.jassnet.co.tz/api/v1`) | Browser → API | PHP session cookie; allowlisted CORS | Public HTTPS; restrict administration | Backend owner | Local scaffold only; VPS/IP/OS/DNS/TLS unverified |
| MySQL/MariaDB | TCP 3306 (default only) | API → database | Dedicated least-privilege DB account | Private network/socket only | DBA | Local XAMPP MariaDB 10.4.32 verified; migrations applied to and removed with an isolated temporary schema. Production DB/version/network remain unverified |
| FreeRADIUS | Commonly UDP 1812/1813; confirm actual setup | Router → RADIUS; backend → verified SQL/jobs | Shared secret/private routing; exact setup TBD | WireGuard/private routes preferred | Network/RADIUS owner | Unverified; no connection or accounting test |
| MikroTik routers | RouterOS API/SSH/Winbox ports are configuration-dependent | Router ↔ RADIUS; restricted admin path | Unique scoped credential | WireGuard/private management only | Network owner | Approx. 10 reported; inventory and first-router canary not supplied |
| WireGuard | UDP port is deployment-specific | VPS ↔ router/site peers | Peer keys, AllowedIPs | Private overlay | Network owner | Peers, routes, firewall, MTU and return path unverified |
| Snipper | Official endpoint/port TBD | API → payment provider; provider → webhook | Product/docs/signature TBD | HTTPS after validation | Finance/provider owner | Disabled; exact product, docs, sandbox and refund flow unverified |
| ClickPesa | Official endpoint/port TBD | API → payment provider; provider → webhook | Product/docs/signature TBD | HTTPS after validation | Finance/provider owner | Disabled; exact product, docs, sandbox and refund flow unverified |
| SMS provider | Official endpoint/port TBD | API/worker → SMS provider | Provider auth TBD | HTTPS after provider confirmation | Operations owner | Disabled; provider and sender ID not confirmed |
| SMTP/email | Configurable endpoint/port TBD | API/worker → mail provider | SMTP/provider secret TBD | TLS/private as supported | Operations owner | Not configured or tested |
| OLT/ONU | Model/protocol/port TBD | Monitoring worker → device | Device-specific least privilege | Management VLAN/private VPN | Network owner | Not implemented; model, firmware and documented access absent |
| Queue/worker | Database-backed job tables in schema | Worker ↔ database/providers | Dedicated DB account and provider secrets | Private server | Backend owner | Tables only; no worker/retries/dead-letter execution yet |
| Backups/monitoring | Provider-specific | Server → protected backup/monitoring | Separate scoped credentials | Private/encrypted | Operations owner | Runbook only; destinations, retention and restore evidence absent |

## Preconditions before any provider or network integration

Obtain an approved inventory and owner for each component; verify DNS/TLS and hosting; confirm PHP/MySQL/FreeRADIUS/RouterOS versions and actual schemas/configuration; confirm WireGuard AllowedIPs/routes/firewall/MTU/time sync; obtain official provider docs and sandbox credentials through a secret manager; confirm SMS provider and templates; and test isolated staging. Never open management ports to the Internet as a shortcut. Do not put credentials in tickets, source control, frontend assets, logs, or this document.
