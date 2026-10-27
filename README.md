# RAR Inbox AI Assist

**v0.1.0 · 27 October 2026 · pre-pilot build — not yet approved for production**

Human-reviewed AI reply assistant for **Nabiad Distribution Limited** dealers and distributors who message the business through Messenger (Meta Business Suite inbox, desktop Chrome). A side panel shows **three fact-checked suggestions** (Professional, Friendly, Short; optional fourth Sales-focused) built only from approved facts on nabiad.com. Staff select, edit, copy (or insert) and **press Send themselves** — the software never sends.

## What is in this repository

| Part | Folder | Status |
|---|---|---|
| WordPress plugin `rar-inbox-ai-assist` | [`plugin/rar-inbox-ai-assist`](plugin/rar-inbox-ai-assist) | Built; 478/478 PHP checks (MariaDB 10.11 + WooCommerce 11.1.2), 464/464 (SQLite) |
| Chrome extension (MV3 side panel) | [`extension`](extension) | Built; 20/20 unit, 21/21 end-to-end incl. axe-core |
| Tests | [`tests`](tests) | PHP unit/integration, extension unit, end-to-end |
| Architecture v0.2 | [`docs`](docs/README.md) | Source of truth (PRD, AppFlow, UI, TRD, Schema, Plan) |

Not yet verified: live Meta inbox capture/insert (off by default; select/paste + Copy works), a real AI provider call, the Hostinger load benchmark and an independent security review. See [PROJECT_STATUS.md](PROJECT_STATUS.md).

## Download

- **[Release v0.1.0 (pre-release)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/tag/v0.1.0)** — `rar-inbox-ai-assist-0.1.0.zip` (WordPress plugin), `rar-inbox-ai-assist-extension-0.1.0.zip` (Chrome extension), `SHA256SUMS`.
- [All releases](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases) · [Source ZIP of `main`](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/archive/refs/heads/main.zip)

Do not use the source ZIP as a plugin: upload the release's plugin ZIP to WordPress and unzip the extension ZIP for Chrome.

## Install

Full guide: **[docs/INSTALL.md](docs/INSTALL.md)**. In short: backup → upload the plugin ZIP → API key constant in `wp-config.php` → token prices + Test connection → LiteSpeed exclude `/wp-json/rar-iaa/` → load KB, dealers and price CSV → each staff member creates a token → Chrome "Load unpacked" → connect.

Build the ZIPs yourself:

```bash
python3 scripts/package.py          # dist/rar-inbox-ai-assist-0.1.0.zip, dist/rar-inbox-ai-assist-extension-0.1.0.zip, dist/SHA256SUMS
```

## How it keeps answers correct

- Facts only: approved KB answers, the linked dealer's active price list, stock buckets. Unlinked contacts never get dealer prices.
- Every suggestion passes a fact guard: a price or percentage not in the approved facts is replaced by a holding reply; other unverified numbers are flagged.
- Credit, discounts, order confirmation, complaints and damage go to a manager (handoff).
- Phone numbers, e-mails, addresses and names are masked before the AI provider sees the text; no conversation text is stored.
- Per-person revocable tokens, rate/daily/monthly limits with cost reservation, one AI call at a time, kill switch.

## Development

```bash
npm ci                                                  # dev-only test tools
node --test tests/ext/*.test.mjs                        # extension unit tests
WP_LOAD=/path/wp-load.php IAA_TEST_URL='http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1' \
  php tests/php/run-all.php                             # plugin tests (server: IAA_PROVIDER=stub php -S 127.0.0.1:8899 -t /path)
IAA_E2E_DOCROOT=/path WP_LOAD=/path/wp-load.php node tests/e2e/run.mjs   # end-to-end
python3 scripts/check_docs.py && python3 scripts/check_tokens.py
```

`scripts/ci/setup-wp.sh` creates a throwaway WordPress test site; CI is in [.github/workflows/ci.yml](.github/workflows/ci.yml). Read [CLAUDE.md](CLAUDE.md) and [docs/06_Plan.md](docs/06_Plan.md) before changing anything.

No project licence has been selected; public visibility does not grant a right to reuse. Bundled third-party assets keep their own licences (Hind Siliguri — OFL, `extension/fonts/OFL.txt`; Lucide — ISC, `extension/icons/LICENSE-lucide.txt`).
