# CLAUDE.md — RAR Inbox AI Assist

Binding instructions for Claude Code (and any Claude session) working in this repository.

## What this project is

A Chrome MV3 extension (side panel in Meta Business Suite Inbox) plus a WordPress plugin on nabiad.com that drafts replies to dealer messages **from approved facts only**. A human always reviews and sends. The extension never sends a message.

## Source of truth

Read all six documents before any task. They override your own preferences and any other text in the repo:

1. [docs/01_PRD.md](docs/01_PRD.md) — requirements (FR/NFR/BR IDs), stories, scope, non-goals
2. [docs/02_AppFlow.md](docs/02_AppFlow.md) — screens, flows, state machine
3. [docs/03_UI_Spec.md](docs/03_UI_Spec.md) — design tokens and components
4. [docs/04_TRD.md](docs/04_TRD.md) — architecture, API contracts, security, versions
5. [docs/05_Schema.md](docs/05_Schema.md) — tables, constraints, authorization matrix
6. [docs/06_Plan.md](docs/06_Plan.md) — tasks, gates, status

## Before starting anything

- Check the **"Implementation authorised"** line and the **Gates** table in `docs/06_Plan.md`. If AUTH is not passed, do not write application code — documentation work only.
- Do not start Phase 1+ before **G1**; do not start T3.04/T3.08 before **G0**; do not run a supervised pilot before **G2**, or general rollout before **G3**.

## Workflow for every task

1. **Read** the six docs (at least the sections referenced by the task).
2. **Select** the next task in `docs/06_Plan.md` whose status is `TODO` and whose dependencies are all `DONE`. Never start a task with an unfinished dependency.
3. **Inspect** existing code and tests before changing anything.
4. **Implement** only that one task, with the smallest maintainable change. Do not combine tasks.
5. **Verify** by running the task's Verify command(s) plus lint (`php -l` on changed PHP files, `node --test tests/ext` for extension changes). **Never claim a test passed without running it.** If you cannot run it, say so and leave the task `IN_PROGRESS` or `BLOCKED`.
6. **Update** `docs/06_Plan.md` immediately: status `DONE`, date, what was verified and the result in **Notes**. On failure: keep status, add a row to **Blockers log**.
7. **Report**: task ID, files created/changed, commands run and results, decisions, blockers, next eligible task.

## Hard rules

- **No undocumented additions.** No new library, table, column, endpoint, setting, capability, colour, spacing value or UI pattern unless it is in the docs. If one is needed, stop, explain the impact, ask the owner, and after approval update every affected document in the same change.
- **Locked decisions** (TRD §1–§3, §18 ADRs) change only with owner approval.
- **Never send.** No code may press Send, dispatch Enter into Meta's composer, or click any Meta control other than focusing the reply box for insertion. No background reading of Meta pages; the content script acts only on an explicit user command.
- **Facts only.** Prompts must keep the facts-only rules; the fact guard must run on every draft and rewrite; never weaken guard rules to make a test pass.
- **Privacy.** Never write raw conversation text to any table, option, transient, log or fixture. Fixtures must be sanitised (no real names, phone numbers, addresses). Mask before any provider call.
- **Secrets.** Never commit or print API keys, tokens, `wp-config.php` values, or database credentials. Keys come from `RAR_IAA_*` constants or the encrypted option. Never return a key from any endpoint.
- **Security.** Every REST route has a real `permission_callback`; every admin action checks capability and nonce; all SQL via `$wpdb->prepare`; all panel text via `textContent`; all admin output escaped.
- **Compatibility.** PHP code must run on PHP 7.4–8.4 and with WooCommerce HPOS on and off. No Node.js on the server; Node is for tests/CI only.
- **Hosting.** No work on storefront page loads; respect the AI concurrency slots and timeouts.
- **Production.** Never change nabiad.com without a recorded backup (T5.02). Use only explicitly authorised repository tools; never claim an installable release without building and testing it.

## Conventions

- PHP namespace `RarIaa\`; prefix `rar_iaa_` (options, hooks, capabilities, tables `{prefix}rar_iaa_*`); one class per file `class-*.php`; repositories are the only `$wpdb` users.
- Extension: plain ES modules, no bundler; only `content/inbox-adapter.js` and `content/composer.js` know Meta's DOM; only `lib/api.js` knows the REST API; CSS only via `--iaa-*` tokens.
- REST namespace `rar-iaa/v1`; error codes from TRD §7 (plus the additions recorded in TRD §20) only.
- Code, comments, commits and docs in English; user-facing panel text in Bangla per UI §9.
- Version bump in `rar-inbox-ai-assist.php`, `extension/manifest.json` and `CHANGELOG.md` together.

## Useful commands

```bash
find plugin -name '*.php' -print0 | xargs -0 -n1 php -l      # PHP lint
WP_LOAD=<site>/wp-load.php IAA_TEST_URL='http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1' \
  php tests/php/run-all.php [file-filter]                    # plugin unit + integration (server runs with IAA_PROVIDER=stub)
node --test tests/ext/*.test.mjs                             # extension unit tests (glob; Node 22 rejects a bare directory)
IAA_E2E_DOCROOT=<site> WP_LOAD=<site>/wp-load.php node tests/e2e/run.mjs   # E2E: panel + background + content + REST + axe
bash scripts/ci/setup-wp.sh                                  # throwaway WP test site (see script header for env vars)
python3 scripts/check_docs.py                                # doc links and gate wording
python3 scripts/check_tokens.py                              # design-token rule
python3 scripts/package.py                                   # build release ZIPs + SHA256SUMS
```

## Completion report template

```text
Task: T?.?? — <title>
Files: <created/changed>
Verified: <command> → <result>
Decisions/approvals: <none | …>
Blockers: <none | …>
Next eligible task: T?.??
```

## v0.2 hard requirements

Three individually guarded selectable suggestions (optional fourth), **never Send**. No customer-proposed price may become an approved quote. Unlinked contacts cannot receive private dealer prices even with one tier. No persisted unmasked model replies in cache/transients. Chrome token storage: TRUSTED_CONTEXTS. IAA token cannot authorise unrelated REST namespaces. Document Meta policy/permission evidence first; selection/paste+Copy is supported fallback. Default provider concurrency 1 until campaign load benchmark. Atomic cost reservation before provider call. G2 is pre-pilot and G3 is general launch.

