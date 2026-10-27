# Project status — 2026-10-27

**Current stage: v0.2.0 built and tested — pre-pilot, not yet approved for production.** 0.1.0 was installed on nabiad.com by the owner; 0.2.0 adds WooCommerce linking, free/paid AI providers with fallback and a professional UI.

| Item | Status |
|---|---|
| Architecture v0.2 (six documents + audit) | Published |
| WordPress plugin `rar-inbox-ai-assist` 0.2.0 | **Built.** 592/592 PHP checks on MariaDB 10.11 + WooCommerce 11.1.2; 532/532 on SQLite; 594/594 on a fresh ZIP install; upgrade from 0.1.0 with data verified |
| WooCommerce product & price link | **Built** — sync, tier rules, live stock, product fields |
| AI providers | Anthropic, OpenAI, Google Gemini, OpenAI-compatible (Groq/OpenRouter); primary + fallback; free tier with consent. **No live call made yet** (needs owner keys) |
| Chrome extension 0.2.0 (side panel) | **Built.** 23/23 unit tests; 21/21 end-to-end on three sites |
| Accessibility (axe-core, WCAG 2.1 A/AA) | 0 serious/critical on 6 panel views and 12 admin views; manual keyboard pass pending |
| PHP 7.4–8.4 compatibility | PHPCompatibility clean; GitHub CI green on PHP 7.4, 8.3, 8.4 (MariaDB 10.11, WooCommerce 11.1.2) incl. E2E |
| Release ZIPs | `python3 scripts/package.py` → plugin ZIP, extension ZIP, `SHA256SUMS`; release workflow attaches them when a GitHub release is published |
| Live Meta inbox capture/insert | **Not verified** (needs Meta permission evidence T0.09 and spikes T0.02/T0.03). Off by default; select/paste + Copy works |
| Real AI provider call | **Not made** — needs the owner's API key(s) (T0.07) |
| Hostinger campaign-load benchmark | **Not run** (T0.08). Keep 1 simultaneous AI call |
| Independent security review | **Not done** (T4.05) |
| Owner decisions OD-1…OD-14 | Safe defaults implemented; sign-off pending (G1) |
| Pilot and production deployment | **Not done** — needs backup (T5.02) and G2 |

See [docs/06_Plan.md](docs/06_Plan.md) for per-task evidence and [docs/INSTALL.md](docs/INSTALL.md) for installation.
