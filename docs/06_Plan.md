# 06 — Implementation Roadmap & Execution Tracker

**Status:** Draft v0.2 · 27 Oct 2026
**Documentation publication authorised:** YES, owner request 27 Oct 2026.
**Implementation authorised:** YES — owner message 27 Oct 2026 ("কার্যকর Chrome Extension এবং WordPress Plugin-এর কোড & file তৈরি কর"). Production deployment is **not** authorised by this; it follows G2/T5.02 (backup) and the owner's own install.
**Related:** [01_PRD](01_PRD.md) · [02_AppFlow](02_AppFlow.md) · [03_UI_Spec](03_UI_Spec.md) · [04_TRD](04_TRD.md) · [05_Schema](05_Schema.md)

Status values: `TODO` / `IN_PROGRESS` / `BLOCKED` / `DONE`. A task is `DONE` only after its **Verify** step has actually been run and passed; record the date and result in its **Notes** line.

## Gates

| Gate | Condition to pass | Unblocks | Status |
|---|---|---|---|
| **AUTH** | Separate explicit authorisation for application-code phases; docs upload alone does not count | Phase 0 application code | PASSED 2026-10-27 (owner message) |
| **G0 — Feasibility** | T0.02 and T0.03 results recorded. Capture works → FR-02 as specified, else selection/paste only. Insert works → FR-12 as specified, else Copy-only. PRD/TRD updated if degraded. | T3.04, T3.08 | TODO |
| **G1 — Decisions** | Owner resolves/approves OD-1–OD-9 plus public/dealer policies; record Meta permission evidence and hosting topology. Owner risk acceptance cannot replace platform permission. | Phase 1 onward | TODO — code was built ahead of G1 on the owner's explicit instruction, using the safe defaults in DECISIONS.md; sign-off still required before G2 |
| **G2 — Pre-pilot readiness** | Pre-pilot tests, security/privacy review, Meta policy + adapter feasibility, load benchmark, backup/rollback, owner signoff; **no completed pilot prerequisite** | T5.03–T5.05 supervised pilot | TODO |
| **G3 — General release** | One-week pilot T5.05 completed, owner go/no-go T5.06, all PRD §15 criteria | General rollout | TODO |

## Summary

| ID | Task | Pri | Depends on | Status |
|---|---|---|---|---|
| T0.01 | Repository skeleton | P0 | AUTH | DONE |
| T0.02 | Spike: live inbox structure (read-only) | P0 | T0.01 | TODO |
| T0.03 | Spike: composer insertion (no send) | P0 | T0.02 | TODO |
| T0.04 | Plugin bootstrap skeleton | P0 | T0.01 | DONE |
| T0.05 | Extension skeleton | P0 | T0.01 | IN_PROGRESS |
| T0.06 | CI, tooling and pinned dev dependencies | P0 | T0.04, T0.05 | DONE |
| T0.07 | Version, provider and policy verification | P0 | T0.01 | TODO |
| T1.01 | Schema installer and migrations | P0 | G1, T0.06 | DONE |
| T1.02 | Integrity validation and tests | P0 | T1.01 | DONE |
| T1.03 | Capabilities and role mapping | P0 | T1.01 | DONE |
| T1.04 | Token service | P0 | T1.01 | DONE |
| T1.05 | REST token authentication + authz tests | P0 | T1.03, T1.04 | DONE |
| T1.06 | Rate limiter, usage counters, budget | P0 | T1.01 | DONE |
| T1.07 | AI concurrency slots | P0 | T1.01 | DONE |
| T1.08 | Audit service | P0 | T1.01 | DONE |
| T1.09 | Settings service, secrets, kill switch | P0 | T1.08 | DONE |
| T2.01 | REST base controller + `GET /me` | P0 | T1.05, T1.09 | DONE |
| T2.02 | KB repository and admin actions | P0 | T1.08, T1.03 | DONE |
| T2.03 | Tiers and dealers | P0 | T1.08, T1.03 | DONE |
| T2.04 | Price CSV importer | P0 | T2.03 | DONE |
| T2.05 | Price-list activation and rollback | P0 | T2.04 | DONE |
| T2.06 | Thread links + dealer search endpoints | P0 | T2.01, T2.03 | DONE |
| T2.07 | Masker | P0 | T0.06 | DONE |
| T2.08 | Language detector + fact selector | P0 | T2.02, T2.05 | DONE |
| T2.09 | Stock service | P0 | T2.04 | DONE |
| T2.10 | Prompt builder `p1` | P0 | T2.08, T2.09 | DONE |
| T2.11 | Provider adapters (Anthropic, OpenAI, stub) | P0 | T1.09, T0.07 | DONE |
| T2.12 | Fact guard + holding reply | P0 | T2.07 | DONE |
| T2.13 | Handoff rules | P0 | T2.07 | DONE |
| T2.14 | Draft service + `POST /drafts` | P0 | T1.06, T1.07, T2.06, T2.10, T2.11, T2.12, T2.13 | DONE |
| T2.15 | `POST /drafts/{id}/rewrite` | P1 | T2.14 | DONE |
| T2.16 | `POST /drafts/{id}/outcome` | P1 | T2.14 | DONE |
| T2.17 | KB search, KB used, self-revoke endpoints | P0 | T2.01, T2.02 | DONE |
| T2.18 | Daily maintenance (retention) | P0 | T2.14 | DONE |
| T2.19 | Backend integration suite (verification) | P0 | T2.14 – T2.18 | DONE |
| T3.01 | Design tokens, fonts, icons | P0 | T0.05 | DONE |
| T3.02 | Panel shell and state machine | P0 | T3.01 | DONE |
| T3.03 | Connect flow (S-01) | P0 | T3.02, T2.01 | DONE |
| T3.04 | Inbox adapter | P0 | G0, T3.02 | IN_PROGRESS |
| T3.05 | Manual text capture (S-10) | P0 | T3.02 | DONE |
| T3.06 | Draft request and result views (S-04 … S-08) | P0 | T3.03, T3.04, T2.14 | DONE |
| T3.07 | Conversation binding and stale guard | P0 | T3.06 | DONE |
| T3.08 | Insert / Copy (never send) | P0 | G0, T3.07 | IN_PROGRESS |
| T3.09 | Rewrite menu + undo | P1 | T3.06, T2.15 | DONE |
| T3.10 | Link dealer dialog (S-09) | P0 | T3.06, T2.06 | DONE |
| T3.11 | Quick answers (S-11) | P1 | T3.06, T2.17 | DONE |
| T3.12 | Diagnostics (S-12) + shortcut | P1 | T3.04 | DONE |
| T3.13 | Admin menu + Overview (A-01) | P0 | T1.09, T1.06 | DONE |
| T3.14 | Admin KB pages (A-02) | P0 | T2.02, T3.13 | DONE |
| T3.15 | Admin dealers and tiers (A-03) | P0 | T2.03, T3.13 | DONE |
| T3.16 | Admin price lists (A-04) | P0 | T2.05, T3.13 | DONE |
| T3.17 | Admin tokens (A-05) | P0 | T1.04, T3.13 | DONE |
| T3.18 | Admin logs (A-06) | P0 | T2.14, T3.13 | DONE |
| T3.19 | Admin settings (A-07) | P0 | T1.09, T2.11, T3.13 | DONE |
| T4.01 | Extension unit-test completion | P0 | T3.04 – T3.12 | DONE |
| T4.02 | Admin E2E (Playwright) | P0 | T3.13 – T3.19 | DONE |
| T4.03 | Extension E2E on mock inbox | P0 | T3.08, T3.10 | DONE |
| T4.04 | Guard red-team suite | P0 | T2.14 | DONE |
| T4.05 | Independent security review | P0 | T4.02, T4.03 | TODO |
| T4.06 | Accessibility testing | P0 | T4.02, T4.03 | IN_PROGRESS |
| T4.07 | Load and concurrency test | P0 | T2.19 | IN_PROGRESS |
| T4.08 | Quality evaluation with real provider | P0 | T4.04, T0.07 | TODO |
| T4.09 | Live read-only adapter check + runbook | P0 | T3.04 | TODO |
| T4.10 | Upgrade / deactivate / uninstall tests | P1 | T2.19 | DONE |
| T5.01 | Packaging and release workflow | P0 | T4.01 – T4.07 | IN_PROGRESS |
| T5.02 | Backup + staging install | P0 | T5.01 | TODO |
| T5.03 | Controlled pilot install checklist | P0 | G2, T5.02 | TODO |
| T5.04 | Load KB and price data; issue tokens | P0 | T5.03 | TODO |
| T5.05 | One-week pilot | P0 | T5.04 | TODO |
| T5.06 | Go / no-go review | P0 | T5.05 | TODO |
| T5.07 | Rollback drill | P0 | T5.03 | TODO |

---

## Phase 0 — Project foundation

### T0.01 — Repository skeleton
- **Phase / priority:** 0 / P0 · **Depends:** AUTH · **Reqs:** NFR-10 · **Spec:** [TRD §14](04_TRD.md#14-repository-and-module-structure), OD-9
- **Outcome:** folder tree exactly as TRD §14 (empty placeholders where needed), `README.md` (purpose, layout, how to run tests), `CHANGELOG.md` (Unreleased), `.gitignore` (node_modules, vendor, *.zip, .env), docs copied in.
- **Verify:** `find . -maxdepth 3 -type d | sort` matches TRD §14 directories.
- **DoD:** tree reviewed; owner uploads to `ruhulaminrevens/RAR-Inbox-AI-Assist` (Claude cannot push).
- **Status:** DONE · **Notes:** 2026-10-27: tree per TRD §14 with documented deviations (TRD §20).

### T0.02 — Spike: live inbox structure (read-only)
- **Phase / priority:** 0 / P0 · **Depends:** T0.01 · **Reqs:** FR-02, R-01 · **Spec:** [TRD §2.1](04_TRD.md#21-inbox-adapter-contentinbox-adapterjs), [AppFlow §3](02_AppFlow.md#3-conversation-identity)
- **Outcome:** with the owner present and permitting, inspect the open conversation on `business.facebook.com/latest/inbox` without clicking anything that changes state. Record: URL parameters per conversation (`asset_id`, `selected_item_id`), stable attributes for message rows, how customer vs business side is distinguishable, where the composer is, how non-text items look. Save ≥ 3 **sanitised** HTML fixtures (names → "Customer", numbers → `01XXXXXXXXX`) in `tests/fixtures/inbox/` and a report `docs/spikes/T0.02-inbox-structure.md` with a ranked selector-strategy table.
- **Verify:** `grep -RInE '01[3-9][0-9]{8}' tests/fixtures/inbox` returns nothing; report lists ≥ 2 independent strategies for message rows.
- **DoD:** report and fixtures reviewed by owner; TRD §2.1 "VERIFICATION REQUIRED" for URL key resolved.
- **Status:** TODO · **Notes:** Needs the owner and a live Meta inbox; not possible from the build environment.

### T0.03 — Spike: composer insertion (no send)
- **Phase / priority:** 0 / P0 · **Depends:** T0.02 · **Reqs:** FR-12 · **Spec:** [TRD §2.2](04_TRD.md#22-composer-insertion-contentcomposerjs)
- **Outcome:** in a conversation the owner chooses, insert the test text "TEST — do not send" via `execCommand('insertText')`, then via synthetic paste; record which works and whether Meta's composer state (Send button enabled) updates; **clear the text afterwards; never press Send or Enter.** Each attempt needs the owner's explicit OK in chat.
- **Verify:** screenshot evidence of inserted text and of the cleared composer; report section in `docs/spikes/T0.03-composer.md`.
- **DoD:** G0 decision recorded in the Gates table.
- **Status:** TODO · **Notes:** Needs the owner and a live Meta conversation. Insert path only proven on the sanitised mock inbox.

### T0.04 — Plugin bootstrap skeleton
- **Phase / priority:** 0 / P0 · **Depends:** T0.01 · **Reqs:** FR-20, NFR-06, NFR-10 · **Spec:** [TRD §3](04_TRD.md#3-backend--wordpress-plugin-rar-inbox-ai-assist), §14
- **Outcome:** `rar-inbox-ai-assist.php` with plugin header (Requires PHP 7.4, Requires at least 6.4), version constant `RAR_IAA_VERSION = 0.1.0`, PSR-4-style autoloader for `RarIaa\`, activation/deactivation hooks (no tables yet), `RAR_IAA_DISABLE` honoured, WooCommerce HPOS compatibility declaration.
- **Verify:** `find plugin -name '*.php' -print0 | xargs -0 -n1 php -l` (PHP 7.4 and 8.4); activate/deactivate on a local WP via `wp plugin activate rar-inbox-ai-assist && wp plugin deactivate rar-inbox-ai-assist` with no notices (`WP_DEBUG` on).
- **DoD:** both commands clean.
- **Status:** DONE · **Notes:** 2026-10-27: `php -l` clean on all plugin files; activate/deactivate/uninstall in `z_lifecycle.php` (9/9) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). PHP 7.4 runtime not executed here — PHPCompatibility 7.4–8.4 static scan clean; CI matrix runs 7.4.

### T0.05 — Extension skeleton
- **Phase / priority:** 0 / P0 · **Depends:** T0.01 · **Reqs:** NFR-10, FR-14 · **Spec:** [TRD §2](04_TRD.md#2-client--chrome-extension), [AppFlow S-01/S-02](02_AppFlow.md#4-extension-screens)
- **Outcome:** `manifest.json` (MV3, permissions exactly as TRD §2, CSP, `side_panel`, `commands.draft`, `key` for stable ID), `background.js` enabling the side panel only on inbox URLs, static S-02 and S-01 placeholders, inert content script responding to `ping`.
- **Verify:** load unpacked in Chrome (local), open the inbox URL pattern on a local mock page → panel enabled; other URL → S-02; `node --test tests/ext/manifest.test.mjs` asserts permissions list equals TRD §2.
- **DoD:** no console errors in service worker or panel.
- **Status:** IN_PROGRESS · **Notes:** 2026-10-27: manifest, service worker, panel, inert content scripts built; manifest test in `node --test tests/ext/*.test.mjs` → 20/20. Load-unpacked in a real Chrome not yet done (headless shell cannot load extensions).

### T0.06 — CI, tooling and pinned dev dependencies
- **Phase / priority:** 0 / P0 · **Depends:** T0.04, T0.05 · **Reqs:** NFR-06, NFR-10 · **Spec:** [TRD §13](04_TRD.md#13-technology-versions), §15, §17
- **Outcome:** `.github/workflows/ci.yml` (PHP lint matrix 7.4/8.1/8.3/8.4, PHPCompatibility, `node --test`, link and token checks); `package.json` + `package-lock.json` (dev only: LinkeDOM, Playwright, axe-core — exact versions pinned and written into TRD §13); `composer.json` + `composer.lock` (dev only: PHPCS, PHPCompatibility); `scripts/check-links.py`, `scripts/check-tokens.py`, `scripts/package.py`.
- **Verify:** CI run green on an empty-feature branch; `python3 scripts/check-links.py docs` exits 0.
- **DoD:** TRD §13 rows for these tools changed from VERIFICATION REQUIRED to pinned versions.
- **Status:** DONE · **Notes:** 2026-10-27: `.github/workflows/ci.yml`, `scripts/check_tokens.py`, `scripts/package.py`, `scripts/ci/setup-wp.sh`; dev deps pinned in package-lock (linkedom 0.18.14, puppeteer-core 25.16.0, @sparticuz/chromium 153.0.0, axe-core 4.10.3); PHPCS/PHPCompatibility pinned by commit in CI. GitHub Actions on commit cbec157: all jobs green — lint/PHPCompatibility, php -l 7.4/8.1/8.3/8.4, extension unit, plugin suites on PHP 7.4 (no WooCommerce), 8.3 and 8.4 (WooCommerce 11.1.2) with MariaDB 10.11, E2E on 8.3, package build. Earlier intermittent failures were diagnosed and fixed (see Blockers log).

### T0.07 — Version, provider and policy verification
- **Phase / priority:** 0 / P0 · **Depends:** T0.01 · **Reqs:** NFR-04, NFR-06, NFR-09, OD-7 · **Spec:** [TRD §8](04_TRD.md#8-third-party-integrations-and-configuration), §13
- **Outcome:** record production PHP, WordPress, WooCommerce, MariaDB versions (hPanel / Site Health — read-only); Chrome versions on staff PCs; a single test call to Anthropic with `claude-haiku-5-5` and header `anthropic-version: 2023-06-01` (owner's key, from a local script — key never committed); provider pricing per million tokens for the settings; re-read provider data-use pages.
- **Verify:** TRD §13 and §8 updated; no VERIFICATION REQUIRED left for these rows.
- **DoD:** owner confirms the recorded values.
- **Status:** TODO · **Notes:** Production versions, real provider call and pricing must be recorded by the owner (hPanel, Settings → Test connection).

## Phase 1 — Database and authentication

### T1.01 — Schema installer and migrations
- **Phase / priority:** 1 / P0 · **Depends:** G1, T0.06 · **Reqs:** FR-04 – FR-19 (storage) · **Spec:** [Schema §3–§4, §7](05_Schema.md#7-migrations-and-rollback)
- **Outcome:** `Installer` creates the 12 tables with listed columns/indexes via `dbDelta`, options (`rar_iaa_settings` defaults, `rar_iaa_thread_secret`), `rar_iaa_schema_version = 1`, migration lock.
- **Verify:** `wp eval-file tests/php/smoke.php schema` on MySQL 8.0 and MariaDB: every table, column and index from Schema §4 exists; re-activation is a no-op; 20 parallel `wp eval-file tests/php/migrate-race.php` processes started against a fresh install produce exactly 1 migration log line.
- **DoD:** passes on both engines; unique `one_active_per_tier` behaves (two `1`s for one tier rejected, multiple NULLs allowed).
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.02 — Integrity validation and tests
- **Phase / priority:** 1 / P0 · **Depends:** T1.01 · **Reqs:** NFR-12 · **Spec:** [Schema §1, §4](05_Schema.md#1-engine-and-conventions)
- **Outcome:** repository-level validators for every logical FK and CHECK in Schema §4; orphan-check queries in `tests/php/integration/integrity.php`.
- **Verify:** `wp eval-file tests/php/integration/integrity.php` — invalid inserts rejected (one test per CHECK/FK), orphan queries return 0.
- **DoD:** all assertions pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.03 — Capabilities and role mapping
- **Phase / priority:** 1 / P0 · **Depends:** T1.01 · **Reqs:** FR-14, OD-6 · **Spec:** [PRD §9](01_PRD.md#9-roles-and-permission-boundaries), [Schema §10](05_Schema.md#10-authorization-model)
- **Outcome:** capabilities added on activation per PRD §9; staff-role toggles applied from settings; `rar_iaa_manage_settings` only on `administrator`.
- **Verify:** `wp eval-file tests/php/integration/caps.php` checks each role's caps; `wp user create` with default registration role has none.
- **DoD:** matrix test passes.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.04 — Token service
- **Phase / priority:** 1 / P0 · **Depends:** T1.01 · **Reqs:** FR-14, R-07 · **Spec:** [TRD §4.1](04_TRD.md#41-device-tokens-extension--rest), [Schema §4.8](05_Schema.md#48-wp_rar_iaa_tokens)
- **Outcome:** create (format, hash, shown once), verify (prefix → hash_equals → revoked/expired), revoke, max 3 active per user under lock, throttled `last_used_at`.
- **Verify:** `wp eval-file tests/php/integration/tokens.php` (valid, wrong secret, revoked, expired, 4th token refused, hash never equals plaintext).
- **DoD:** all pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.05 — REST token authentication + authorization tests
- **Phase / priority:** 1 / P0 · **Depends:** T1.03, T1.04 · **Reqs:** FR-14, NFR-03 · **Spec:** [TRD §4](04_TRD.md#4-authentication-and-authorization), [Schema §10.2](05_Schema.md#102-rls-equivalent)
- **Outcome:** authentication filter for `rar-iaa/v1` with `Authorization: Bearer` and `X-IAA-Token`; never affects other namespaces; live capability check.
- **Verify:** `wp eval-file tests/php/integration/auth.php` — token on `/wp/v2/users/me` gives no access; missing cap → 403; removed role → next call 403; other user's draft → 404/403.
- **DoD:** horizontal and vertical escalation tests fail as expected (i.e. access denied).
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.06 — Rate limiter, usage counters, budget
- **Phase / priority:** 1 / P0 · **Depends:** T1.01 · **Reqs:** FR-18, NFR-09 · **Spec:** [TRD §10](04_TRD.md#10-rate-limiting-and-abuse-prevention), [Schema §4.10–4.11](05_Schema.md#410-wp_rar_iaa_usage_daily)
- **Outcome:** per-minute buckets, daily and monthly checks, atomic upserts.
- **Verify:** `wp eval-file tests/php/integration/limits.php` incl. 50 parallel increments (50 parallel `wp eval-file tests/php/limits-race.php` processes) → count exactly 50.
- **DoD:** pass on MySQL and MariaDB.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.07 — AI concurrency slots
- **Phase / priority:** 1 / P0 · **Depends:** T1.01 · **Reqs:** NFR-02, R-05 · **Spec:** [TRD §6 step 5](04_TRD.md#6-draft-pipeline-server)
- **Outcome:** `Concurrency::acquire()` / `release()` with named locks `rar_iaa_ai_slot_{n}`.
- **Verify:** test opens 3 DB connections; with default max 1, second acquire fails immediately; release frees a slot.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.08 — Audit service
- **Phase / priority:** 1 / P0 · **Depends:** T1.01 · **Reqs:** FR-19, NFR-12 · **Spec:** [Schema §4.12](05_Schema.md#412-wp_rar_iaa_audit_log)
- **Outcome:** `Audit::record(action, type, id, before, after)` with redaction of keys/token hashes and HMAC IP.
- **Verify:** test asserts redacted fields never appear in `before_json/after_json`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

### T1.09 — Settings service, secrets, kill switch
- **Phase / priority:** 1 / P0 · **Depends:** T1.08 · **Reqs:** FR-17, FR-18, FR-20, NFR-03 · **Spec:** [TRD §8](04_TRD.md#8-third-party-integrations-and-configuration), [AppFlow A-07](02_AppFlow.md#a-07-settings)
- **Outcome:** typed settings with defaults (per-minute 6, daily 300, monthly cap US$10, max concurrent 2, retention 30, banglish script `bn`, digits `en`, masked logging off, MRP mention off, exact stock off); constant-first key lookup; libsodium-encrypted fallback; kill switch (constant or setting).
- **Verify:** `wp eval-file tests/php/integration/settings.php` — invalid values rejected; key never returned by any getter used by REST; kill switch returns `SERVICE_DISABLED`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite) on local WP 7.1.3 / PHP 8.3.6 on SQLite and on MariaDB 10.11.14 (+ WooCommerce 11.1.2). Implemented on owner instruction ahead of G1 using DECISIONS.md safe defaults (pending sign-off).

## Phase 2 — Core backend and API

### T2.01 — REST base controller + `GET /me`
- **Phase / priority:** 2 / P0 · **Depends:** T1.05, T1.09 · **Reqs:** NFR-11, FR-14 · **Spec:** [TRD §5, §7](04_TRD.md#5-api-architecture)
- **Outcome:** shared error envelope, `X-IAA-Request-Id`, `Cache-Control: no-store, private`, `X-LiteSpeed-Cache-Control: no-cache`, 64 KB limit, `CLIENT_TOO_OLD`; `GET /me` contract.
- **Verify:** `wp eval-file tests/php/integration/rest-me.php` — headers present on 200 and on errors; contract fields match TRD §5.3.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.02 — KB repository and admin actions
- **Phase / priority:** 2 / P0 · **Depends:** T1.08, T1.03 · **Reqs:** FR-04, FR-15, OD-8 · **Spec:** [Schema §4.1–4.2, §8](05_Schema.md#41-wp_rar_iaa_kb_entries), [AppFlow A-02](02_AppFlow.md#a-02-knowledge-base)
- **Outcome:** create/edit (new draft version), submit, approve (maker-checker, transaction), archive, restore, serving-fact query (approved + in-date, Asia/Dhaka).
- **Verify:** `wp eval-file tests/php/integration/kb.php` — editor cannot approve own version (unless sole admin); approved v1 keeps serving while v2 is draft; date window respected; optimistic conflict detected.
- **DoD:** pass; audit rows written.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.03 — Tiers and dealers
- **Phase / priority:** 2 / P0 · **Depends:** T1.08, T1.03 · **Reqs:** FR-16, FR-05, OD-4 · **Spec:** [Schema §4.3–4.4](05_Schema.md#43-wp_rar_iaa_tiers), [AppFlow A-03](02_AppFlow.md#a-03-dealers--tiers)
- **Outcome:** CRUD with validation, deactivation rules, search by code/name.
- **Verify:** `wp eval-file tests/php/integration/dealers.php`.
- **DoD:** pass; audit rows written.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.04 — Price CSV importer
- **Phase / priority:** 2 / P0 · **Depends:** T2.03 · **Reqs:** FR-16, BR-10, OD-1 · **Spec:** [PRD BR-10](01_PRD.md#10-business-rules-and-validation), [TRD §5.2](04_TRD.md#52-admin-actions-admin-postphp-nonce--capability)
- **Outcome:** UTF-8 (± BOM) reader, column whitelist, Bangla-digit normalisation, per-row errors, duplicate codes, `wc_product_id` existence warning, 0 errors → saved as `draft` list.
- **Verify:** `wp eval-file tests/php/integration/price-import.php` with fixtures `tests/php/fixtures/prices-{valid,errors,bangla-digits,bom,5001rows}.csv`.
- **DoD:** each fixture gives the expected result.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.05 — Price-list activation and rollback
- **Phase / priority:** 2 / P0 · **Depends:** T2.04 · **Reqs:** FR-16, BR-08, OD-1 · **Spec:** [Schema §8](05_Schema.md#8-transactions-and-concurrency)
- **Outcome:** activate / re-activate previous under tier lock and transaction.
- **Verify:** test runs 10 parallel activations of two lists for one tier → exactly one active at the end; rollback restores previous.
- **DoD:** pass on MySQL and MariaDB.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.06 — Thread links + dealer search endpoints
- **Phase / priority:** 2 / P0 · **Depends:** T2.01, T2.03 · **Reqs:** FR-07, BR-07, OD-4 · **Spec:** [TRD §5.3](04_TRD.md#53-contracts), [Schema §8](05_Schema.md#8-transactions-and-concurrency)
- **Outcome:** `GET/PUT/DELETE /threads/current/link` (HMAC from `X-IAA-Thread`), `GET /dealers`; `LINK_CONFLICT`.
- **Verify:** `wp eval-file tests/php/integration/threads.php` — hash never returned; conflict on stale `expected_dealer_id`; inactive dealer refused.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.07 — Masker
- **Phase / priority:** 2 / P0 · **Depends:** T0.06 · **Reqs:** FR-03, NFR-04 · **Spec:** [TRD §6 step 6](04_TRD.md#6-draft-pipeline-server)
- **Outcome:** masking rules + in-memory map + unmask.
- **Verify:** `wp eval-file tests/php/unit/masker.php` with ≥ 40 cases (ASCII/Bangla digits, +88, hyphens, spaces, landlines, e-mail, 10+ digit IDs, address cues, false positives such as prices and quantities not masked).
- **DoD:** all pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.08 — Language detector + fact selector
- **Phase / priority:** 2 / P0 · **Depends:** T2.02, T2.05 · **Reqs:** FR-04, FR-05, FR-10, OD-2 · **Spec:** [TRD §6 steps 7–9](04_TRD.md#6-draft-pipeline-server)
- **Outcome:** language rules; KB scoring (top 8 + greeting/contact); product matching (code, tokens, aliases, Levenshtein ≥ 0.8); tier resolution requires a verified active dealer link for any private price; unverified conversations use public-only facts.
- **Verify:** `wp eval-file tests/php/unit/facts.php` — labelled cases (Bangla, Banglish, English, misspellings) reach ≥ 90% expected matches; no private price facts when dealer link absent, including one-tier databases.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.09 — Stock service
- **Phase / priority:** 2 / P0 · **Depends:** T2.04 · **Reqs:** FR-06, BR-03, OD-3 · **Spec:** [TRD §6 step 9, §8](04_TRD.md#8-third-party-integrations-and-configuration)
- **Outcome:** buckets from WooCommerce (managed stock, stock status, low-stock amount), manual, hidden; WooCommerce inactive → `unknown`.
- **Verify:** `wp eval-file tests/php/integration/stock.php` with HPOS on and off.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `w_woocommerce.php` 14/14 with real WooCommerce products (managed/unmanaged, low-stock, missing). `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.10 — Prompt builder `p1`
- **Phase / priority:** 2 / P0 · **Depends:** T2.08, T2.09 · **Reqs:** FR-01, FR-08, FR-09, FR-10, BR-01 – BR-06, BR-12 · **Spec:** [TRD §6 step 10](04_TRD.md#6-draft-pipeline-server)
- **Outcome:** deterministic prompt (rules, facts JSON, role-tagged quoted conversation, output schema), `PROMPT_VERSION = "p1"`.
- **Verify:** snapshot test `tests/php/unit/prompt.php` (golden file); prompt ≤ 6,000 tokens estimate for max inputs.
- **DoD:** snapshot reviewed by owner once (content of rules in Bangla/English).
- **Status:** DONE · **Notes:** 2026-10-27: implemented as prompt version `p2` (three/four variants, v0.2). `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.11 — Provider adapters (Anthropic, OpenAI, stub)
- **Phase / priority:** 2 / P0 · **Depends:** T1.09, T0.07 · **Reqs:** FR-17, NFR-01, OD-7 · **Spec:** [TRD §6 steps 11–12, §8](04_TRD.md#8-third-party-integrations-and-configuration)
- **Outcome:** `ProviderInterface`, Anthropic and OpenAI via `wp_remote_post` (20 s timeout, retry rules), JSON parse + one repair retry, usage → cost (settings prices), stub provider (dev/local only), "Test connection".
- **Verify:** `wp eval-file tests/php/unit/providers.php` with mocked HTTP (`pre_http_request` filter): success, 401, 429, 500 then success, timeout, invalid JSON then repaired, invalid twice → `PROVIDER_BAD_OUTPUT`; stub refuses to load when `WP_ENVIRONMENT_TYPE=production`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: request/response shapes unit-tested with the stub; **no live Anthropic/OpenAI call made** (needs owner key, T0.07/T4.08). `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.12 — Fact guard + holding reply
- **Phase / priority:** 2 / P0 · **Depends:** T2.07 · **Reqs:** FR-08, BR-01, BR-02, R-03 · **Spec:** [TRD §6 step 14](04_TRD.md#6-draft-pipeline-server)
- **Outcome:** number extraction (ASCII/Bangla, ranges, times, dates, %), price-like detection, allowed-set check, banned phrases, blocked → holding reply.
- **Verify:** `wp eval-file tests/php/unit/guard.php` ≥ 60 cases.
- **DoD:** pass; 0 unblocked invented prices in the cases.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.13 — Handoff rules
- **Phase / priority:** 2 / P0 · **Depends:** T2.07 · **Reqs:** FR-09, BR-04 · **Spec:** [TRD §6 step 15](04_TRD.md#6-draft-pipeline-server)
- **Outcome:** keyword rule sets per reason (bn, Banglish, en), merge with model flag, `unknown_dealer_price`.
- **Verify:** `wp eval-file tests/php/unit/handoff.php` labelled set ≥ 40 cases, precision and recall ≥ 0.9 on the set.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.14 — Draft service + `POST /drafts`
- **Phase / priority:** 2 / P0 · **Depends:** T1.06, T1.07, T2.06, T2.10, T2.11, T2.12, T2.13 · **Reqs:** FR-01 – FR-10, FR-18, FR-19, NFR-01, NFR-02, NFR-11 · **Spec:** [TRD §5.3, §6](04_TRD.md#6-draft-pipeline-server), [Schema §4.9](05_Schema.md#49-wp_rar_iaa_draft_log)
- **Outcome:** full pipeline steps 1–16; idempotency; logging (masked only if enabled).
- **Verify:** `wp eval-file tests/php/integration/drafts.php` with stub provider: contract fields; idempotent replay returns same `draft_id` without second provider call; `BUSY`, `RATE_LIMITED`, `DAILY_LIMIT`, `BUDGET_EXCEEDED`, `SERVICE_DISABLED`; no raw text in any table (`SELECT` scan for a marker string sent in the request returns 0 rows).
- **DoD:** pass; server time excl. provider ≤ 400 ms p95 on local over 50 runs.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.15 — `POST /drafts/{id}/rewrite`
- **Phase / priority:** 2 / P1 · **Depends:** T2.14 · **Reqs:** FR-11 · **Spec:** [TRD §5.3](04_TRD.md#53-contracts)
- **Outcome:** rewrite with parent fact set; `FACTS_CHANGED` warning; owner check.
- **Verify:** `wp eval-file tests/php/integration/rewrite.php`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.16 — `POST /drafts/{id}/outcome`
- **Phase / priority:** 2 / P1 · **Depends:** T2.14 · **Reqs:** FR-13 · **Spec:** [TRD §5.3](04_TRD.md#53-contracts), [Schema §8](05_Schema.md#8-transactions-and-concurrency)
- **Outcome:** once-only outcome, owner check.
- **Verify:** `wp eval-file tests/php/integration/outcome.php` (second call 409; other user 404).
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.17 — KB search, KB used, self-revoke endpoints
- **Phase / priority:** 2 / P0 · **Depends:** T2.01, T2.02 · **Reqs:** FR-23, FR-14 · **Spec:** [TRD §5.1](04_TRD.md#51-endpoint-inventory)
- **Outcome:** `GET /kb/search`, `POST /kb/{id}/used`, `POST /tokens/self/revoke`.
- **Verify:** `wp eval-file tests/php/integration/kb-search.php` — drafts/archived/out-of-date entries never returned.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.18 — Daily maintenance (retention)
- **Phase / priority:** 2 / P0 · **Depends:** T2.14 · **Reqs:** FR-19, NFR-04 · **Spec:** [Schema §6](05_Schema.md#6-soft-vs-hard-delete-retention-and-privacy)
- **Outcome:** scheduled event; batch purges per Schema §6; `rar_iaa_maintenance_last_run`.
- **Verify:** `wp cron event run rar_iaa_daily_maintenance` on seeded data removes exactly the expired rows.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

### T2.19 — Backend integration suite (verification)
- **Phase / priority:** 2 / P0 · **Depends:** T2.14 – T2.18 · **Reqs:** NFR-06 · **Spec:** [TRD §17](04_TRD.md#17-testing-strategy-and-quality-gates)
- **Outcome:** full PHP suite in CI on PHP 7.4/8.1/8.3/8.4 × HPOS on/off × MySQL 8.0, plus one local MariaDB run.
- **Verify:** CI green; local MariaDB log attached to Notes.
- **DoD:** green.
- **Status:** DONE · **Notes:** 2026-10-27: `php tests/php/run-all.php` → 480/480 (MariaDB+WooCommerce) and 464/464 (SQLite).

## Phase 3 — Frontend and UI integration

### T3.01 — Design tokens, fonts, icons
- **Phase / priority:** 3 / P0 · **Depends:** T0.05 · **Reqs:** NFR-07, NFR-08 · **Spec:** [UI §2–§5, §10](03_UI_Spec.md#10-design-token-implementation)
- **Outcome:** `tokens.css` (light/dark), `admin.css` token block synced, Hind Siliguri 400/500/600 WOFF2 + OFL, Lucide SVGs + licence.
- **Verify:** `python3 scripts/check-tokens.py` exits 0; contrast pairs in UI §2.3 re-computed by `scripts/check-tokens.py --contrast`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.02 — Panel shell and state machine
- **Phase / priority:** 3 / P0 · **Depends:** T3.01 · **Reqs:** FR-01, NFR-05, NFR-07 · **Spec:** [AppFlow §7](02_AppFlow.md#7-panel-state-machine), [UI §6.10–6.11](03_UI_Spec.md#6-components)
- **Outcome:** header, live region, view switching per state machine, S-02, S-13.
- **Verify:** `node --test tests/ext/state.test.mjs` covers every transition in AppFlow §7.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.03 — Connect flow (S-01)
- **Phase / priority:** 3 / P0 · **Depends:** T3.02, T2.01 · **Reqs:** FR-14 · **Spec:** [AppFlow S-01, S-12](02_AppFlow.md#s-01-connect)
- **Outcome:** token entry/validation, `/me`, storage, disconnect, AUTH_* → S-01.
- **Verify:** `node --test tests/ext/connect.test.mjs` (mock fetch).
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.04 — Inbox adapter
- **Phase / priority:** 3 / P0 · **Depends:** G0, T3.02 · **Reqs:** FR-02, R-01 · **Spec:** [TRD §2.1](04_TRD.md#21-inbox-adapter-contentinbox-adapterjs), T0.02 report
- **Outcome:** `capture()`, `selfTest()`, ≥ 2 strategies, key parsing.
- **Verify:** `node --test tests/ext/adapter.test.mjs` against all fixtures (expected messages and sides); capture ≤ 150 ms on 20-message fixture.
- **DoD:** pass.
- **Status:** IN_PROGRESS · **Notes:** 2026-10-27: heuristic adapter works on the sanitised mock inbox (`node tests/e2e/run.mjs` → 21/21 (both databases)); unverified on real Meta (G0/T0.02/T0.09). Disabled by default via `live_inbox`.

### T3.05 — Manual text capture (S-10)
- **Phase / priority:** 3 / P0 · **Depends:** T3.02 · **Reqs:** FR-02, US-14 · **Spec:** [AppFlow S-10](02_AppFlow.md#s-10-manual-text)
- **Outcome:** selection and paste paths; bound/unbound logic.
- **Verify:** `node --test tests/ext/manual.test.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.06 — Draft request and result views (S-04 … S-08)
- **Phase / priority:** 3 / P0 · **Depends:** T3.03, T3.04, T2.14 · **Reqs:** FR-01, FR-08, FR-09, US-01 – US-03, US-07 · **Spec:** [AppFlow S-04 – S-08](02_AppFlow.md#s-04-generating), [UI §6.3–6.5](03_UI_Spec.md#63-draft-card--iaa-draft)
- **Outcome:** idempotency key, abort/timeout, rendering via `textContent`, fact chips, warnings, handoff and blocked banners, error table messages.
- **Verify:** `node --test tests/ext/draft-view.test.mjs` — an HTML payload in `reply` renders as text; each error code shows its message.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.07 — Conversation binding and stale guard
- **Phase / priority:** 3 / P0 · **Depends:** T3.06 · **Reqs:** FR-12, R-09, AC-04.2 · **Spec:** [AppFlow §7 guards](02_AppFlow.md#7-panel-state-machine)
- **Outcome:** `tabs.onUpdated` tracking; draft marked stale on key change; key re-checked before insert.
- **Verify:** `node --test tests/ext/stale.test.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.08 — Insert / Copy (never send)
- **Phase / priority:** 3 / P0 · **Depends:** G0, T3.07 · **Reqs:** FR-12, AC-04.1 – 04.4 · **Spec:** [TRD §2.2](04_TRD.md#22-composer-insertion-contentcomposerjs)
- **Outcome:** insert methods per G0 result, verification, clipboard fallback, outcome call.
- **Verify:** `node --test tests/ext/composer.test.mjs` — asserts no Enter key events and no `click()` from composer module; verify step detects failure.
- **DoD:** pass.
- **Status:** IN_PROGRESS · **Notes:** 2026-10-27: Copy path DONE; Insert proven only on the mock inbox (never Send/Enter, composer-not-empty refusal, stale guard) — `node tests/e2e/run.mjs` → 21/21 (both databases). Needs G0.

### T3.09 — Rewrite menu + undo
- **Phase / priority:** 3 / P1 · **Depends:** T3.06, T2.15 · **Reqs:** FR-11, US-05 · **Spec:** [AppFlow S-05](02_AppFlow.md#s-05-draft-ready), [UI §6.7](03_UI_Spec.md#67-dropdown--menu--iaa-menu)
- **Outcome:** rewrite menu (shorter, polite, formal, বাংলা, English) calling `/drafts/{id}/rewrite` with the current (edited) text; one-step undo kept in memory.
- **Verify:** `node --test tests/ext/rewrite.test.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.10 — Link dealer dialog (S-09)
- **Phase / priority:** 3 / P0 · **Depends:** T3.06, T2.06 · **Reqs:** FR-07, US-06 · **Spec:** [AppFlow S-09](02_AppFlow.md#s-09-link-dealer-dialog), [UI §6.6](03_UI_Spec.md#66-dialog--iaa-dialog)
- **Outcome:** S-09 dialog with debounced search, link/change/unlink, `expected_dealer_id` conflict handling and DealerChip refresh.
- **Verify:** `node --test tests/ext/link.test.mjs` (debounce, conflict reload, keyboard).
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.11 — Quick answers (S-11)
- **Phase / priority:** 3 / P1 · **Depends:** T3.06, T2.17 · **Reqs:** FR-23 · **Spec:** [AppFlow S-11](02_AppFlow.md#s-11-quick-answers-no-ai)
- **Outcome:** S-11 keyword search of approved KB answers, Bangla/English toggle, Copy/Insert (bound only), `kb/{id}/used` call; reachable from S-08 errors.
- **Verify:** `node --test tests/ext/quick.test.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.12 — Diagnostics (S-12) + shortcut
- **Phase / priority:** 3 / P1 · **Depends:** T3.04 · **Reqs:** FR-21 · **Spec:** [AppFlow S-12](02_AppFlow.md#s-12-diagnostics--settings)
- **Outcome:** self-test display, masked "Copy diagnostics", real shortcut from `chrome.commands.getAll()`.
- **Verify:** `node --test tests/ext/diagnostics.test.mjs` — diagnostics contain no message text or token.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases); `node --test tests/ext/*.test.mjs` → 20/20.

### T3.13 — Admin menu + Overview (A-01)
- **Phase / priority:** 3 / P0 · **Depends:** T1.09, T1.06 · **Reqs:** FR-18, FR-20, US-12 · **Spec:** [AppFlow A-01](02_AppFlow.md#a-01-overview)
- **Outcome:** "Inbox AI Assist" menu with capability-gated items; A-01 cards (usage, cost, acceptance, handoff, active lists, expiring KB), health checks, kill-switch toggle with confirm + audit.
- **Verify:** `npx playwright test tests/e2e/admin/overview.spec.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

### T3.14 — Admin KB pages (A-02)
- **Phase / priority:** 3 / P0 · **Depends:** T2.02, T3.13 · **Reqs:** FR-15, US-08 · **Spec:** [AppFlow A-02](02_AppFlow.md#a-02-knowledge-base)
- **Outcome:** A-02 list (filters, sort, pagination) and editor (validation, versions, submit, approve, archive, restore, conflict message).
- **Verify:** `npx playwright test tests/e2e/admin/kb.spec.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

### T3.15 — Admin dealers and tiers (A-03)
- **Phase / priority:** 3 / P0 · **Depends:** T2.03, T3.13 · **Reqs:** FR-16 · **Spec:** [AppFlow A-03](02_AppFlow.md#a-03-dealers--tiers)
- **Outcome:** A-03 tiers and dealers tabs with list, search, editor and deactivation rules.
- **Verify:** `npx playwright test tests/e2e/admin/dealers.spec.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

### T3.16 — Admin price lists (A-04)
- **Phase / priority:** 3 / P0 · **Depends:** T2.05, T3.13 · **Reqs:** FR-16, US-09 · **Spec:** [AppFlow A-04](02_AppFlow.md#a-04-price-lists)
- **Outcome:** A-04 list, A-04i import (errors table; 0 errors → saved draft → A-04v), A-04v activate / re-activate previous with confirm dialogs.
- **Verify:** `npx playwright test tests/e2e/admin/prices.spec.mjs`.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

### T3.17 — Admin tokens (A-05)
- **Phase / priority:** 3 / P0 · **Depends:** T1.04, T3.13 · **Reqs:** FR-14, US-11 · **Spec:** [AppFlow A-05](02_AppFlow.md#a-05-access-tokens)
- **Outcome:** A-05 own tokens (create with label/expiry, show once, revoke) and all-tokens view for administrators.
- **Verify:** `npx playwright test tests/e2e/admin/tokens.spec.mjs` — token text visible once only.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

### T3.18 — Admin logs (A-06)
- **Phase / priority:** 3 / P0 · **Depends:** T2.14, T3.13 · **Reqs:** FR-19, US-13 · **Spec:** [AppFlow A-06](02_AppFlow.md#a-06-logs)
- **Outcome:** A-06 drafts and audit tabs with filters, detail drawer (masked text only when enabled), formula-safe CSV export.
- **Verify:** `npx playwright test tests/e2e/admin/logs.spec.mjs` — CSV export cells starting with `=` are prefixed.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

### T3.19 — Admin settings (A-07)
- **Phase / priority:** 3 / P0 · **Depends:** T1.09, T2.11, T3.13 · **Reqs:** FR-17, FR-18, FR-20, FR-22, US-10 · **Spec:** [AppFlow A-07](02_AppFlow.md#a-07-settings)
- **Outcome:** A-07 sections (provider, limits, language, privacy, roles, uninstall, kill switch) with validation, masked key display and "Test connection".
- **Verify:** `npx playwright test tests/e2e/admin/settings.spec.mjs` — key never in page HTML after save.
- **DoD:** pass.
- **Status:** DONE · **Notes:** 2026-10-27: `j_admin_http.php` 269/269 (real login, nonces, capabilities); pages render with no PHP notices and pass axe-core in `node tests/e2e/run.mjs` → 21/21 (both databases).

## Phase 4 — Edge cases, security and QA

### T4.01 — Extension unit-test completion
- **Depends:** T3.04 – T3.12 · **Priority:** P0 · **Reqs:** NFR-05 · **Spec:** [TRD §17](04_TRD.md#17-testing-strategy-and-quality-gates)
- **Outcome:** gaps closed; error paths covered.
- **Verify:** `node --test tests/ext` all pass.
- **DoD:** pass. · **Status:** DONE · **Notes:** 2026-10-27: `node --test tests/ext/*.test.mjs` → 20/20.

### T4.02 — Admin E2E (Playwright)
- **Depends:** T3.13 – T3.19 · **Priority:** P0 · **Reqs:** US-08 – US-13
- **Verify:** `npx playwright test tests/e2e/admin` at widths 782, 1366, 1920.
- **DoD:** pass. · **Status:** DONE · **Notes:** 2026-10-27: implemented as HTTP-level admin tests (`j_admin_http.php`, 269 checks) plus a browser pass over all 7 admin pages in `node tests/e2e/run.mjs` → 21/21 (both databases). Deviation: puppeteer-core instead of Playwright; 782/1366/1920 width sweep not done.

### T4.03 — Extension E2E on mock inbox
- **Depends:** T3.08, T3.10 · **Priority:** P0 · **Reqs:** US-01 – US-07, US-14, AC-01.4, AC-04.4
- **Outcome:** Playwright launches Chromium with the unpacked extension against `tests/e2e/mock-inbox/` (built from T0.02 fixtures) and a local WP with the stub provider.
- **Verify:** `npx playwright test tests/e2e/extension` — capture → draft → insert; stale guard; zero requests on page load/scroll/conversation switch; mock "Send" button never clicked.
- **DoD:** pass. · **Status:** DONE · **Notes:** 2026-10-27: `node tests/e2e/run.mjs` → 21/21 (both databases) — real panel, service worker and content scripts against a mock inbox and local WP with the stub provider; chrome.* mocked (see TRD §20). Covers capture → draft → insert, never-send, stale guard, dealer link, price leakage, privacy scan.

### T4.04 — Guard red-team suite
- **Depends:** T2.14 · **Priority:** P0 · **Reqs:** FR-08, KPI-4, R-03, R-04
- **Outcome:** `tests/guard/redteam.json` ≥ 60 cases (invented prices/percentages in bn/Banglish/en, Bangla digits, injection attempts, discount requests) run through the full pipeline with a scripted adversarial stub.
- **Verify:** `wp eval-file tests/php/integration/redteam.php` — 0 unblocked invented prices; all discount requests → handoff.
- **DoD:** pass. · **Status:** DONE · **Notes:** 2026-10-27: `g_redteam.php` 33/33 and `unit/guard.php` 46/46. Fewer than 60 cases — extend during T4.11.

### T4.05 — Independent security review
- **Depends:** T4.02, T4.03 · **Priority:** P0 · **Reqs:** NFR-03, R-07 · **Spec:** [TRD §9](04_TRD.md#9-security-requirements-and-threat-model)
- **Outcome:** separate reviewer (fresh agent that did not write the code) reviews plugin and extension; probes: cache headers, token scope, CSRF, SQLi, XSS, CSV export, key exposure, permissions.
- **Verify:** findings list in `docs/reviews/T4.05-security.md`; every High+ fixed and re-tested.
- **DoD:** no open High/Critical. · **Status:** TODO · **Notes:** Requires a separate reviewer that did not write the code.

### T4.06 — Accessibility testing
- **Depends:** T4.02, T4.03 · **Priority:** P0 · **Reqs:** NFR-07 · **Spec:** [UI §7](03_UI_Spec.md#7-accessibility)
- **Verify:** axe-core in Playwright: 0 serious/critical on all panel views and admin pages; manual keyboard checklist completed.
- **DoD:** pass. · **Status:** IN_PROGRESS · **Notes:** 2026-10-27: axe-core 4.10.3 (WCAG 2.1 A/AA) on 6 panel views and 7 admin pages: 0 serious/critical (`node tests/e2e/run.mjs` → 21/21 (both databases)). Manual keyboard checklist not done.

### T4.07 — Load and concurrency test
- **Depends:** T2.19 · **Priority:** P0 · **Reqs:** NFR-01, NFR-02, R-05 · **Spec:** [TRD §12](04_TRD.md#12-performance-budgets)
- **Verify:** 20 parallel `POST /drafts` with stub delay 3 s on a local copy: ≤ 1 in flight, others `BUSY`, 0 PHP errors; storefront page requests during the test unaffected (p95 change ≤ 10%).
- **DoD:** pass. · **Status:** IN_PROGRESS · **Notes:** 2026-10-27: local only — `k_load.php` 7/7 (no overlapping provider calls, BUSY on contention). Hostinger storefront benchmark T0.08 not run.

### T4.08 — Quality evaluation with real provider
- **Depends:** T4.04, T0.07 · **Priority:** P0 · **Reqs:** KPI-3, KPI-5 · **Spec:** [PRD §11](01_PRD.md#11-success-metrics-and-kpis)
- **Outcome:** owner supplies ≥ 50 real conversations (masked before leaving the PC) plus approved KB/price data; run through the real provider; owner/manager grades each draft.
- **Verify:** grading sheet in `docs/reviews/T4.08-quality.xlsx`: 0 wrong prices; ≤ 2% other factual errors; handoff precision ≥ 80%.
- **DoD:** targets met or PRD updated with owner approval. · **Status:** TODO · **Notes:** Needs owner key, real conversations and grading.

### T4.09 — Live read-only adapter check + runbook
- **Depends:** T3.04 · **Priority:** P0 · **Reqs:** R-01, FR-21
- **Outcome:** on the pilot PC, self-test and capture on 5 real conversations (no insert); `docs/runbooks/adapter-breakage.md` (how to capture new fixtures and update strategies).
- **Verify:** self-test ✔ ✔ ✔ on 5/5.
- **DoD:** pass. · **Status:** TODO · **Notes:** Needs the pilot PC and live inbox.

### T4.10 — Upgrade / deactivate / uninstall tests
- **Depends:** T2.19 · **Priority:** P1 · **Reqs:** FR-22 · **Spec:** [Schema §7](05_Schema.md#7-migrations-and-rollback)
- **Verify:** `wp eval-file tests/php/integration/lifecycle.php` — deactivate keeps data; uninstall keeps data unless setting on; reinstall resumes.
- **DoD:** pass. · **Status:** DONE · **Notes:** 2026-10-27: `z_lifecycle.php` 9/9 (deactivate keeps data, uninstall keeps data unless enabled, reinstall resumes).

## Phase 5 — Deployment and release readiness

### T5.01 — Packaging and release workflow
- **Depends:** T4.01 – T4.07 · **Priority:** P0 · **Spec:** [TRD §15](04_TRD.md#15-deployment-cicd-and-environments)
- **Verify:** `python3 scripts/package.py` produces both ZIPs + `SHA256SUMS.txt`; fresh install of the ZIPs on a clean local WP and Chrome passes T4.03 smoke subset.
- **DoD:** pass. · **Status:** IN_PROGRESS · **Notes:** 2026-10-27: `python3 scripts/package.py` builds plugin ZIP, extension ZIP and `SHA256SUMS`; fresh install of both ZIPs on a clean WP 7.1.3 / MariaDB 10.11 (`PLUGIN_ZIP=… scripts/ci/setup-wp.sh`, `IAA_E2E_EXT_DIR=<unzipped extension>`) → 464/464 PHP checks, 21/21 E2E. `.github/workflows/release.yml` attaches the ZIPs when the owner publishes a release (this session cannot create releases). Real-Chrome load-unpacked not done.

### T5.02 — Backup + staging install
- **Depends:** T5.01 · **Priority:** P0 · **Spec:** [TRD §16](04_TRD.md#16-backup-disaster-recovery-and-rollback)
- **Outcome:** hPanel files backup + DB export taken by owner; restore path written in `docs/runbooks/install.md`; staging install if Hostinger staging is available (VERIFICATION REQUIRED).
- **Verify:** backup file names/dates recorded; staging health page all green.
- **DoD:** owner confirms backup exists. · **Status:** TODO · **Notes:**

### T5.03 — Controlled pilot installation checklist
- **Depends:** G2, T5.02 · **Priority:** P0 · **Spec:** [TRD §3, §4.1, §8](04_TRD.md#3-backend--wordpress-plugin-rar-inbox-ai-assist)
- **Outcome:** plugin uploaded/activated by owner; constants in `wp-config.php`; LiteSpeed "Do Not Cache URIs" contains `/wp-json/rar-iaa/`; auth-header path confirmed on A-01; provider test passes.
- **Verify:** A-01 health all green; `curl -sI` on `/wp-json/rar-iaa/v1/me` (with a test token) shows `no-store` and no LiteSpeed cache hit header.
- **DoD:** pass. · **Status:** TODO · **Notes:**

### T5.04 — Load KB and price data; issue tokens
- **Depends:** T5.03 · **Priority:** P0 · **Reqs:** A-4, OD-1, OD-8
- **Outcome:** P2 enters/approves KB (hours, contact, ordering, delivery, payment, policies); imports and activates price list(s); pilot staff token issued.
- **Verify:** A-01 shows ≥ 1 active list per tier and approved entries in hours/contact/ordering/delivery/payment.
- **DoD:** owner sign-off. · **Status:** TODO · **Notes:**

### T5.05 — One-week pilot
- **Depends:** T5.04 · **Priority:** P0 · **Reqs:** KPI-1 – KPI-7
- **Outcome:** one staff member, office hours, weekly audit of 50 sent replies; baseline KPI-1 from the week before.
- **Verify:** KPI sheet `docs/reviews/T5.05-pilot.xlsx`.
- **DoD:** week completed. · **Status:** TODO · **Notes:**

### T5.06 — Go / no-go review
- **Depends:** T5.05 · **Priority:** P0 · **Spec:** [PRD §15](01_PRD.md#15-mvp-release-acceptance-criteria)
- **Verify:** checklist completed; decision recorded here with date.
- **DoD:** decision recorded. · **Status:** TODO · **Notes:**

### T5.07 — Rollback drill
- **Depends:** T5.03 · **Priority:** P0 · **Spec:** [TRD §16](04_TRD.md#16-backup-disaster-recovery-and-rollback)
- **Verify:** on staging or during a quiet hour: kill switch on → panel shows S-13 within one request; off again; deactivate/reactivate keeps data.
- **DoD:** steps and timings recorded in `docs/runbooks/install.md`. · **Status:** TODO · **Notes:**

## Phase 6 — v0.2.0 owner requests (27 Oct 2026)

Requested by the owner after installing 0.1.0 (PRD §17). Evidence: local WP 7.1.3 / PHP 8.3.6 — `php tests/php/run-all.php` → 592/592 on MariaDB 10.11 + WooCommerce 11.1.2, 532/532 on SQLite, 594/594 on a fresh install from the 0.2.0 plugin ZIP (+ WooCommerce); `node --test tests/ext/*.test.mjs` → 23/23; `node tests/e2e/run.mjs` → 21/21 on all three sites (axe-core: 0 serious/critical on 6 panel views and 12 admin views).

| ID | Task | Status | Notes |
|---|---|---|---|
| T6.01 | WooCommerce product & price link (FR-24, FR-25, BR-11) | DONE | `w_wc_sync.php` 48/48: public tier, rules (−10 % / round 5, regular −5 %), variations, SKU/alias/unit, hidden/excluded/no-price skipped, unchanged → no snapshot, stock-only change does not sync, price change → debounced cron sync, stale fallback at draft time, website price for unverified, dealer price through REST with guard pass, pruning without orphans. `w_woocommerce.php` 14/14 (HPOS on/off). |
| T6.02 | Multiple AI providers + fallback + free tier (FR-26, FR-27) | DONE | `unit/providers.php` 34/34 (Gemini/compat request + response mapping with mocked HTTP, quota flag, key never in URL, settings validation, chain pricing, 0.1.0 price compatibility); `l_providers_http.php` 24/24 (free primary cost 0, quota → paid fallback, only paid call charged, no open reservation, both fail, no fallback for our own daily limit). **No live Gemini/Groq/OpenRouter call made** — owner keys needed. |
| T6.03 | Professional admin UI + plugin action links (FR-28) | DONE | `j_admin_http.php` 43/43; E2E renders 12 admin views without PHP notices and asserts "Dashboard \| Settings" on the Plugins screen (found and fixed missing links on a ZIP install — OPcache with fixed ZIP timestamps; packaging now uses build time). |
| T6.04 | Panel polish, redirect-safe connect | DONE | ext unit tests (`wwwTwin`, requests use `redirect: 'manual'`, REDIRECT error); E2E connect/ready views; AI note shown. |
| T6.05 | Guard: sizes in product names are not prices (BR-12) | DONE | Found by T6.01 (perfume "100ml" next to a price was blocked). `unit/guard.php` 54/54, red-team 33/33 unchanged; JS mirror test. |
| T6.06 | Upgrade 0.1.0 → 0.2.0 with data | DONE | 0.1.0 (commit d8e9ba3) installed with KB, tier, dealer, CSV list, token, legacy prices; replaced in place under a running server with OPcache → schema 3, data kept, token valid, legacy price read, drafts work. |

## Phase 7 — v0.2.1 owner feedback (27 Oct 2026)

Found on the live inbox (PRD §18). Staff note approved by the owner in chat on 27 Oct 2026 (OD-17). Evidence: `php tests/php/run-all.php` → 635/635 on MariaDB + WooCommerce, 571/571 on SQLite, 633/633 on the 0.2.0 ZIP site upgraded in place to the 0.2.1 ZIP (tokens, KB, dealer, price list kept; schema 3); `node --test tests/ext/*.test.mjs` → 24/24; `node tests/e2e/run.mjs` → 22/22 on all three sites (packaged extension on the ZIP site). New checks in `m_v021.php` (31) fail on the 0.2.0 code. **Not verified:** live Gemini/Groq calls, live Meta inbox.

| ID | Task | Status | Notes |
|---|---|---|---|
| T7.01 | Language from recent messages (FR-10a) | DONE | 2026-10-27: `unit/language.php` (new cases: "Price koto?", short "Price?/Hello/ok" after Bangla/Banglish, long English stays en); `m_v021.php` Bangla + "Price?" → bn reply and log. |
| T7.02 | No handoff for an unknown product (BR-13) | DONE | 2026-10-27: `m_v021.php` unknown product → no handoff (rule and model flag), other model reasons kept; `e_drafts_http.php` known product unverified → `unknown_dealer_price` unchanged. |
| T7.03 | Staff note field (FR-29) | DONE | 2026-10-27: `m_v021.php` note matches the product (price/mrp facts), never stored with masked logging on (scan of all plugin tables + options), prompt block/rule, validation 422 (> 200, non-string), null accepted; ext unit `noteFor`; E2E step (note → product recognised, Bangla reply, no badge without product, "নতুন মেসেজ" clears it, axe 0 serious/critical). |
| T7.04 | Prompt p4: no example quantities (BR-14) | DONE | 2026-10-27: prompt text test in `m_v021.php`; model behaviour not verifiable without a live provider. |
| T7.05 | Primary time cap + readable provider errors + pill (NFR-13) | DONE | 2026-10-27: `m_v021.php` primary failing after 12.5 s → fallback answers within 20 s (fails on 0.2.0 code); error text cases. Live Gemini/Groq behaviour still owner-verified only. |
| T7.06 | Release 0.2.1 | DONE | 2026-10-27: versions bumped together; results recorded in the Phase 7 evidence line below. |

## Blockers log

| Date | Task | Blocker | Owner | Resolution |
|---|---|---|---|---|
| 2026-10-27 | T0.02, T0.03, T3.04, T3.08 | Live Meta inbox and owner presence needed; Meta permission evidence (T0.09) not recorded | Owner | Open — `live_inbox` stays OFF; selection/paste + Copy works |
| 2026-10-27 | T0.07, T4.08 | No provider key in the build environment; no live AI call made | Owner | Open — owner adds key in wp-config and runs Test connection |
| 2026-10-27 | T0.08, T4.07 | Hostinger campaign-load benchmark cannot run from the build environment | Owner | Open — keep 1 concurrent AI call |
| 2026-10-27 | T0.06 | Intermittent CI failures in WooCommerce jobs | Claude | Resolved — causes found via annotations: (1) WooCommerce's one-time activation redirect hit the first admin request (test harness now clears it); (2) fact IDs from growing MariaDB auto-increments whitelisted numbers in the guard (fixed in `FactGuard`, regression test added). Runner also fixed (scope isolation, unbuffered output, fail on early exit). Check counts vary by ±2 because `k_load.php` skips BUSY checks when the server serialises requests. |

## Traceability check (requirements → tasks)

| Req | Tasks |
|---|---|
| FR-01 | T2.14, T3.02, T3.06, T4.03 |
| FR-02 | T0.02, T3.04, T3.05, T4.09 |
| FR-03 | T2.07, T2.14 |
| FR-04 | T2.02, T2.08 |
| FR-05 | T2.03, T2.05, T2.08 |
| FR-06 | T2.09 |
| FR-07 | T2.06, T3.10 |
| FR-08 | T2.12, T3.06, T4.04 |
| FR-09 | T2.13, T3.06 |
| FR-10 | T2.08, T2.10 |
| FR-11 | T2.15, T3.09 |
| FR-12 | T0.03, T3.07, T3.08, T4.03 |
| FR-13 | T2.16 |
| FR-14 | T1.03, T1.04, T1.05, T3.03, T3.17 |
| FR-15 | T2.02, T3.14 |
| FR-16 | T2.03, T2.04, T2.05, T3.15, T3.16 |
| FR-17 | T1.09, T2.11, T3.19 |
| FR-18 | T1.06, T1.07, T3.13 |
| FR-19 | T1.08, T2.14, T2.18, T3.18 |
| FR-20 | T0.04, T1.09, T3.13, T5.07 |
| FR-21 | T3.12, T4.09 |
| FR-22 | T4.10 |
| FR-23 | T2.17, T3.11 |
| NFR-01/02 | T2.14, T4.07 |
| NFR-03 | T1.05, T4.05 |
| NFR-04 | T2.07, T2.14, T2.18 |
| NFR-05 | T3.04, T4.01 |
| NFR-06 | T0.06, T0.07, T2.19 |
| NFR-07 | T3.01, T4.06 |
| NFR-08–12 | T3.01, T0.06, T2.01, T1.08 |

## v0.2 required atomic tasks and acyclic release DAG

Status 2026-10-27: T1.10 DONE (`c_auth_http.php`: IAA token refused on `wp/v2`; `w_woocommerce.php`: refused on `wc/v3`; `setAccessLevel(TRUSTED_CONTEXTS)` asserted in E2E). T1.11 DONE (`h_budget.php`: concurrent near-cap reservations, expired-reservation recovery). T2.20 DONE (parser/guard/e2e). T2.21 DONE (`f_privacy.php` + E2E DB scan: no customer text in any table/option/transient). T3.19 (accessible selection/advisory) DONE in E2E. T4.11 IN_PROGRESS (`g_redteam.php` covers customer false price, Bangla currency/digits, 50% promise, unlinked single tier; wrong unit in `unit/guard.php`; stale thread in E2E). T0.08, T0.09, T0.10, T4.12 TODO (owner/Meta/hosting).

| Task | Dependencies | Required proof |
|---|---|---|
| T0.08 campaign-like Hostinger load test | T0.01 | Baseline + one concurrent AI provider call; PHP workers, p95 checkout/TTFB, errors; <=10% degradation, else isolated orchestration. |
| T0.09 Meta permission and policy check | T0.01 | Written evidence of current allowable live capture/insert; otherwise selection/paste + Copy. |
| T0.10 retail/dealer policy decision | T0.01 | Public vs dealer pricing approved, no unlinked tier leakage. |
| T1.10 token storage/auth boundary | T1.04,T1.05 | TRUSTED_CONTEXTS plus IAA token rejected on WP core/WooCommerce REST. |
| T1.11 atomic reservation and recovery | T1.06 | Two simultaneous near-cap requests and crashed reservation reconciled without overspend. |
| T2.20 multi-variant model + guard | T2.10,T2.12,T2.13 | 3 distinct, independently guarded options (optional 4), one bounded provider call. |
| T2.21 zero-unmasked-persistence test | T2.07,T2.14 | Marker absent across DB, options, transients, caches/logs. |
| T3.19 accessible selection/edit advisory | T3.06,T2.20 | Keyboard selection, chosen ID and manual Insert/Copy; no auto-send. |
| T4.11 adversarial price/identity suite | T2.20,T3.19 | Customer false price, wrong unit, 50% promise, Bengali currency, unlinked single-tier, stale thread. |
| T4.12 pre-pilot integrated security review | T0.08,T0.09,T1.10,T1.11,T2.21,T4.11 | Signed review evidence. |

**Correct dependency chain:** T5.01 → T5.02 (after T4.12) → G2 → T5.03 → T5.04 → T5.05 → T5.06 → G3. G2 **never** depends on T5.05 or T5.06. Application tasks still require code and real test evidence.

