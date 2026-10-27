# Changelog

## [0.2.1] — 2026-10-27

Fixes from the owner's first live use on nabiad.com (product photo + "Price koto?").

- **Reply language**: "Price koto?" is Banglish (one Banglish word in a two-word message is enough), and a short English-looking last message ("Price?", "Hello", "ok") follows the customer's earlier messages — these got English replies before.
- **No manager badge for an unknown product**: when a price is asked but no product is recognised, the suggestions ask which product; the "ম্যানেজারকে জানান — ডিলার যাচাই ছাড়া দাম" handoff now appears only when a recognised product's price cannot be given.
- **Panel "পণ্য / নোট" field** (owner-approved): type the product the customer sent as a photo (e.g. "Jaguar Classic Red 100ml"); it is masked, used to find the product facts, never stored, and cleared by "নতুন মেসেজ".
- **No invented example sizes** ("1kg, 500ml") in suggestions (prompt p4).
- **Free primary + fallback**: a slow or failing primary (e.g. Gemini free tier) is cut off at 12 s so the fallback (e.g. Groq) still answers within the 20 s budget; test results explain provider errors (overloaded, limit reached, model not found, key rejected).
- **Admin**: the "AI: primary → fallback" pill shows "(not set)" for a provider without a key/model.

## [0.2.0] — 2026-10-27

Owner requests after installing 0.1.0 on nabiad.com.

- **WooCommerce link**: per-tier price rules (WooCommerce current/regular price ± %, rounding, or CSV), automatic public *Website price* tier, sync snapshots with rollback (Sync now, on product price/name changes, daily, draft-time fallback), product fields "Inbox AI: other names / unit / pack size / exclude", Linked products view with every tier's price and live stock. Schema 3 (in-place upgrade).
- **More AI providers**: Google Gemini and OpenAI-compatible APIs (Groq, OpenRouter …) besides Anthropic and OpenAI; primary + automatic fallback (free first, paid when the free limit is reached); per-provider keys, models, prices and tests; free-tier flag only with explicit data-use consent; panel shows which AI answered.
- **Professional admin UI**: brand header and section navigation on every page, setup checklist, cards, empty states, tabbed Settings with provider cards, Products & prices page; **Dashboard | Settings** links on the Plugins screen.
- **Panel**: numbered steps on connect/ready screens; connect finds `www.` automatically; requests never follow redirects (token protection).
- **Fixes**: fact guard no longer treats sizes in product names ("100ml") or measurements as prices (would have replaced correct perfume prices with holding replies); error responses use an allowlist (no provider/model internals).

## [0.1.0] — 2026-10-27

First working build of the WordPress plugin and Chrome extension (pre-pilot).

- Plugin: 13 tables (schema 2), 8 capabilities, revocable per-person tokens limited to `rar-iaa/v1`, rate/daily/monthly limits with atomic cost reservation, one AI call at a time, audit log, kill switch, retention cleanup.
- Draft pipeline: masking → language → approved facts (KB, linked-dealer price list, stock buckets) → one provider call for 3 (or 4) suggestions → per-suggestion fact guard, holding reply, handoff → no raw text stored.
- Providers: Anthropic Messages API and OpenAI Chat Completions; local-only stub for tests.
- wp-admin: Overview with health checks, Knowledge base (maker-checker), Dealers & tiers, Price lists (CSV import, activate, rollback), Access tokens, Logs (CSV export), Settings.
- Extension: side panel (Bangla UI, light/dark), select/paste + Copy by default, optional live capture/insert (admin switch, never sends), dealer link dialog, rewrite + undo, quick answers, diagnostics, Alt+Shift+D.
- Tests: PHP unit/integration (incl. red-team, privacy, WooCommerce, lifecycle), extension unit tests, end-to-end with axe-core; CI workflow; packaging script.
- Fixed during verification: icon markup parse error, literal "null" in panel views, unlabeled admin filters, listbox roles, guard cue crossing sentence boundaries.
- Fact guard: internal fact IDs (`kb:20`, `price:…`) no longer count as facts, so they cannot suppress an unverified-number warning (found by an intermittent CI failure on MariaDB, where IDs keep growing).
- Test runner: each test file runs in its own scope (per-file counts were corrupted), output is unbuffered, and a crash before the summary now fails the run. Admin tests clear WooCommerce's one-time setup redirect.

## Architecture v0.2 — 2026-10-27

- Publish PRD, app flow, design spec, TRD, schema and plan based on supplied v0.1 ZIP.
- Replace single draft with 3 selectable independently guarded suggestions (optional fourth).
- Prevent price assertions sourced solely from customer messages and confidential dealer price leakage to unlinked users.
- Remove unmasked reply transients; restrict token storage; require cross-namespace auth tests.
- Require atomic cost reservation, Meta policy gate and campaign-like hosting benchmark (concurrency one initially).
- Fix cyclic release plan by defining G2 pre-pilot and G3 general release.
- Add project README, download links, open decisions and security/status guidance.

## Architecture v0.1

Original owner-supplied architecture; not a working application.
