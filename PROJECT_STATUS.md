# Project status — 2026-10-27

**Current stage: v0.1.0 built and tested locally — pre-pilot, not yet approved for production.**

| Item | Status |
|---|---|
| Architecture v0.2 (six documents + audit) | Published |
| WordPress plugin `rar-inbox-ai-assist` 0.1.0 | **Built.** 480/480 PHP checks on MariaDB 10.11 + WooCommerce 11.1.2; 464/464 on SQLite (WP 7.1.3, PHP 8.3) |
| Chrome extension 0.1.0 (side panel) | **Built.** 20/20 unit tests; 21/21 end-to-end against a mock inbox and local WordPress |
| Accessibility (axe-core, WCAG 2.1 A/AA) | 0 serious/critical on 6 panel views and 7 admin pages; manual keyboard pass pending |
| PHP 7.4–8.4 compatibility | PHPCompatibility clean; GitHub CI green on PHP 7.4, 8.3, 8.4 (MariaDB 10.11, WooCommerce 11.1.2) incl. E2E |
| Release ZIPs | `python3 scripts/package.py` → plugin ZIP, extension ZIP, `SHA256SUMS` |
| Live Meta inbox capture/insert | **Not verified** (needs Meta permission evidence T0.09 and spikes T0.02/T0.03). Off by default; select/paste + Copy works |
| Real AI provider call | **Not made** — needs the owner's API key and token prices (T0.07) |
| Hostinger campaign-load benchmark | **Not run** (T0.08). Keep 1 simultaneous AI call |
| Independent security review | **Not done** (T4.05) |
| Owner decisions OD-1…OD-14 | Safe defaults implemented; sign-off pending (G1) |
| Pilot and production deployment | **Not done** — needs backup (T5.02) and G2 |

See [docs/06_Plan.md](docs/06_Plan.md) for per-task evidence and [docs/INSTALL.md](docs/INSTALL.md) for installation.
