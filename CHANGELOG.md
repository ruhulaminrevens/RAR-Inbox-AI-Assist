# Changelog

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
