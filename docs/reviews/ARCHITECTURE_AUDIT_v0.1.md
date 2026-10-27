# RAR Inbox AI Assist — Architecture Audit & Change Request

**Date:** 27 October 2026  
**Reviewed input:** `RAR-Inbox-AI-Assist-Architecture-v0.1.zip`  
**Scope:** all six `/docs` Markdown files, `CLAUDE.md`, `.cursorrules`.  
**Review method:** static cross-document design review; selected external documentation checks. **No live Meta inbox, WordPress server, API key, production integration, test suite or Chrome Extension code was available for execution.**  
**Status:** **NO-GO for implementation until critical issues are resolved.** Architecture review only. `06_Plan.md` explicitly says implementation is not authorised.

## Executive decision

The archive has a strong structure: clear human-in-the-loop rule (no auto-send), roles and capability matrix, versioned KB and price lists, per-conversation draft binding, logging, rollout phases, tests, and explicit open decisions. However, the locked **single-draft** decision contradicts the original owner requirement to receive **3–4 professional reply suggestions** and select one to insert into the Meta composer. A release-gate circular dependency also makes the written deployment plan impossible to execute as ordered. These issues should be fixed in the source-of-truth documents **before** a coding agent uses them.

## Findings, by severity

| ID | Severity | Affected documents | Evidence (v0.1 lines) | Recommended fix and pass criterion |
|---|---|---|---|---|
| F-01 | **Critical — scope** | PRD, AppFlow, UI, TRD, Schema, Plan, CLAUDE.md | `01_PRD.md:75` defines one draft; `04_TRD.md:461` ADR-09 explicitly rejects three variants | Change MVP to 3 (configurable up to 4) distinct approved-facts-only suggestions per click, default: Professional, Friendly, Short; optionally Sales-focused. One AI call may return an array, avoiding 3–4 separate calls. Selection populates **editable** composer draft; **never** Send. Define per-variant guard/handoff/facts, selected variant log, UI states, response schema, cost limits, QA acceptance tests. Change locked ADR-09 only after owner approval. |
| F-02 | **Critical — release deadlock** | PRD, Plan | `06_Plan.md:16,88–91` G2 requires all PRD §15 (including one-week pilot `01_PRD.md:314`) before T5.03, but pilot T5.05 requires T5.04 and T5.03 | Replace with **G2 = pre-pilot readiness** (QA/security/backup/staging + owner signoff), permits controlled pilot; **G3 = general rollout** requires T5.05 + T5.06 outcomes. Adjust T5.03–T5.07 dependencies and PRD release language. Verify dependency DAG is acyclic. |
| F-03 | **High — dealer price integrity** | PRD, TRD, Plan | `04_TRD.md:224`: allowed guard numbers include **all customer-message numbers**; `01_PRD.md:206` says quoted prices must come from approved tier facts | Classify numeric claims by semantic role. Price assertions must match approved current tier price facts **even when the same price appears in the customer's question**. Customer-supplied numbers can be quoted only as attributed questions, never confirmed as business prices. Add red-team tests: 'দাম ৯০০ টাকা, তাই তো?', 'give 50% discount', reordered Bengali and ASCII amounts, false pack-size, changed tier, price unit mismatch. |
| F-04 | **High — privacy/idempotency** | TRD, Schema, PRD | `04_TRD.md:195,221`: full response cached in a WP transient after placeholder unmasking. `05_Schema.md:123,374` promises no raw conversation/PII in transients | Do **not** persist the unmasked reply in `wp_options` transients. Cache only masked response if safe, or maintain a short-lived deduplication record that contains IDs/status/usage metadata and does not reproduce PII. Use explicit no-PII assertions for every database write, persistent cache, error log and backup. Re-check whether raw request bodies reach access/WAF logs. |
| F-05 | **High — storefront availability** | PRD, TRD, Plan | `01_PRD.md:106` p95 storefront impact objective; `04_TRD.md:198,334,455` synchronous 20s outbound calls on nabiad.com and ‘fit shared hosting’ assumption | Run an actual **baseline + campaign-like load test** before accepting ADR-02. PHP workers are occupied during blocking upstream calls. Test traffic, CPU, worker saturation, REST latency, TTFB/checkout and failure rates with 0/1/2 concurrent AI calls. Default AI concurrency to 1 until measured. If any regression, put AI orchestration on a separate small backend and expose only approved facts via narrowly scoped WooCommerce/WP service. No need to rebuild the whole storefront for this project. |
| F-06 | **High — Meta compliance and DOM reliability** | PRD, TRD, Plan | `01_PRD.md:279` rates collection-policy risk Low–Medium; `04_TRD.md:53–67` DOM capture/insertion unverified | Treat DOM capture as **policy and technical feasibility gates**, not simply owner risk acceptance. Verify applicable Meta permissions/policies before live extraction, and make text-selection/paste-only mode a fully functional supported fallback. Verify latest read + composer insertion against the real UI, customer vs business classification, conversation-switch and selection states. Do not assume user click alone equals platform authorisation. |
| F-07 | **High — token exposure** | TRD, AppFlow, Plan | `04_TRD.md:45` token under `chrome.storage.local`; `02_AppFlow.md:79–88` persistent login; tokens may last 180 days | Document that Chrome local extension storage is not encryption. Call `chrome.storage.local.setAccessLevel({accessLevel:'TRUSTED_CONTEXTS'})` if persistent storage is used; keep provider keys entirely off-browser; consider shorter staff token expiry/session unlock and revoke on lost devices. Add tests asserting content scripts cannot read device tokens and no token leakage in logs/messages. |
| F-08 | **High — unknown customer/dealer pricing** | PRD, TRD | `01_PRD.md:257`, `04_TRD.md:202` use lone tier when a conversation has no dealer link | Withhold confidential **dealer** prices for unverified/unknown conversations by default, even if a single tier exists. Introduce separate public/retail answer flow or require staff-confirmed dealer linking before dealer quotes. Acceptance: unlinked retail user receives no distributor-only pricing. |
| F-09 | **High — customer segmentation** | PRD, Flow, KB, Plan | `01_PRD.md:18,48–53,67` scopes primarily dealer/distributor conversations, excludes Instagram/WhatsApp | Ask for a clear **retail + dealer** policy decision. If both are in MVP, add persona, FAQ, tier-visibility matrix, public-price/stock rules, routing, and tests; if dealers-only, explicitly state retail messages receive a non-price holding reply. Do not silently infer all page customers are authorised dealers. |
| F-10 | **Medium — false 'hard' budget cap** | PRD, TRD, Schema | `01_PRD.md:92,113` says hard monthly stop at USD $10; `04_TRD.md:312` admits overshoot from requests already in flight | Use atomic estimated **cost reservations** before provider calls (reserve worst-case tokens x price; settle/refund from actual usage), plus provider-side spend limits if supported. Otherwise reword claims as 'soft cap with bounded overshoot.' Define exact boundary test with two simultaneous calls. |
| F-11 | **Medium — PII fields mismatch** | PRD, TRD, Schema | `01_PRD.md:77` requires display-name masking; `04_TRD.md:57` capture shape has only `side/text/at`; `04_TRD.md:205` conditions name masking on adapter providing it | Add optional `customer_display_name` as **ephemeral input** used solely for masking, if policy/permission allows; or remove unsupported promise and include person-name masking limitations. Extend masking red-team cases for Bengali addresses, embedded phone separators, emails, IDs and customer-supplied account details. |
| F-12 | **Medium — edited draft bypass** | PRD, Flow, TRD | `01_PRD.md:82` guards model draft before display; `02_AppFlow.md:113–115` lets user edit then insert | Human is responsible for final send, but add a **post-edit advisory check** for price claims or changed facts before insertion, especially when an edited text contains currency, percentage or delivery promises. Never silently modify a human edit. |
| F-13 | **Medium — version/quality verification** | TRD, Plan | `04_TRD.md:339–355` still has numerous 'VERIFICATION REQUIRED' versions; T0.07 planned | Pin dev dependencies during Phase 0, use real Anthropic provider model `claude-haiku-5-5` (verified), validate rate card at runtime; set reasonable model effort/output tokens for short responses. Confirm WordPress/PHP/WooCommerce/Chrome versions on the actual deployment. |
| F-14 | **Medium — server auth boundary** | TRD, Schema, Plan | `04_TRD.md:90` sets WordPress user using `determine_current_user`/`rest_authentication_errors` based on IAA namespace | Add cross-namespace attack tests: an IAA Bearer token **must never** authenticate WooCommerce, WP core, or a third-party REST endpoint. Route-scoping and WordPress filter ordering must be verified in a real WP environment, not assumed from the API contract. |
| F-15 | **Medium — async UI and stale guard** | AppFlow, TRD, Plan | `02_AppFlow.md:97,258–299`; `04_TRD.md:47` relies on tab URL updates; the Meta UI is SPA-style | Verify tab/History API changes on actual Meta. Always re-read open thread identity **immediately at Insert** (already planned), bind draft to page/account/thread, clear or mark stale on account and tab switch, and provide Copy-only if identity cannot be proven. Maintain E2E tests on representative sanitized mock inbox snapshots. |

## Required document change map

**01_PRD.md:** change FR-01, FR-08, FR-12 and associated US acceptance criteria to multiple suggestions with per-variant guards; add public/dealer pricing decisions, privacy persistence rule, realistic spend/hosting targets, pre-pilot vs general-release distinction.

**02_AppFlow.md:** convert S-05 into Suggestions Ready showing 3–4 selectable cards, selected variant editor, facts/warnings per card, shared conversation-stale guard, keyboard focus and fallback states. Add preview of selection without any auto-send action.

**03_UI_Spec.md:** define `ReplySuggestionCard` (selected, unselected, warned, blocked, handoff, generating), compact 320px responsive layout, labels and accessible radio/list semantics; update actionable Copy/Insert states and per-variant warnings.

**04_TRD.md:** revise ADR-09, endpoint response `suggestions[]`, prompt output schema, one-call cost and validation, choice IDs, PII-safe idempotency and token storage, policy gate, hosting load criteria, price-claim guard.

**05_Schema.md:** add `suggestion_count` and `selected_variant_id` (or create a properly normalised suggestion-selection table **only if there is a demonstrated use case**); avoid storing full generated text unmasked. Keep a controlled audit of which suggestion was inserted/copied.

**06_Plan.md:** split gate G2/G3 and fix DAG; add atomic tasks for multi-suggestion changes, policy review, zero-PII persisted state tests, false-price red team, worker saturation, post-edit checks, token storage and customer segmentation; amend traceability.

**CLAUDE.md / .cursorrules:** update instructions to require per-variant guard, no auto-send, privacy-safe caches, release-gate dependency checks, and explicit acknowledgement of locked decision changes.

## Suggested implementation approach (subject to scope approval)

1. **Gate A — Correct the six docs** and record approvals for ADR-09, dealer/retail price rules, and the hosting design. Keep code authorisation as **NO** until the owner decides.
2. **Gate B — Proof of feasibility:** controlled, policy-reviewed Meta read/selection/insert spike; demonstrate correct thread identity and graceful Copy fallback, with no automatic Send.
3. **Gate C — Backend/AI facts:** approved KB, validated prices, fact guard and independent per-variant validation; no raw PII persisted.
4. **Gate D — Hosting/performance:** representative storefront campaign traffic benchmark while AI drafting runs; choose on-site WP calls vs isolated backend based on evidence.
5. **Gate E — Pilot:** controlled one-person rollout after QA and backup; then general-release go/no-go decision.

## What is verified vs not verified

- **Verified externally:** Chrome Side Panel supports MV3; panel is available since Chrome 114, `sidePanel.open()` since Chrome 116. Anthropic publishes `claude-haiku-5-5` as a currently active model ID, released 7 Oct 2026. Hostinger notes that exhaustion of PHP workers can delay or time out site requests. Chrome warns extension storage is not encrypted and local storage can be exposed to content scripts unless the access level is changed. Meta describes automated data collection restrictions.
- **Not verified:** staff PC browser version, live Meta selectors and composer insertion, Meta permission eligibility for this workflow, Hostinger account capacity/metrics, actual WP/PHP/WooCommerce versions, real API credentials, model latency/cost on this account, production fact corpus, ability to keep p95 ≤ 12s at peak campaign load.

## Official documentation checked

- Chrome Side Panel API: https://developer.chrome.com/docs/extensions/reference/api/sidePanel
- Chrome Storage API: https://developer.chrome.com/docs/extensions/reference/api/storage
- Chrome extension privacy: https://developer.chrome.com/docs/extensions/develop/security-privacy/user-privacy
- Claude Haiku 5.5 Model: https://platform.claude.com/docs/en/models/haiku-5-5/overview
- WordPress REST API auth: https://developer.wordpress.org/reference/hooks/rest_authentication_errors/
- Hostinger resource limits/worker guidance: https://www.hostinger.com/support/1583532-what-to-do-if-your-hosting-plan-limits-are-reached-in-hostinger/
- Meta Facebook scraping explanation: https://www.facebook.com/help/463983701520800
- Meta Automated Data Collection Terms (may require login): https://www.facebook.com/legal/automated_data_collection_terms

---

**Audit conclusion:** The source files are a useful v0.1 architecture foundation; **not a final approved implementation specification**. Approve the required scope decisions, fix the two critical blockers and remaining high-severity findings, rerun document consistency and dependency checks, and only then issue a revised v0.2 pack. No original ZIP content was modified by this audit.