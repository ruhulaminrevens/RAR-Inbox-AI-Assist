# Production readiness — RAR Inbox AI Assist 1.0.0

**Status (28 Oct 2026): release candidate.** The engineering work for 1.0.0 is complete and verified (section A). Real use on one live site has already produced evidence (section B). **Production approval is the owner's decision.** It is given by completing and signing section C. This document does not claim approval by itself.

## A. Verified by the build (automated, repeatable)

| Area | Evidence |
|---|---|
| Plugin tests | `php tests/php/run-all.php`: unit and integration tests, including red-team guard, privacy scans, budget, WooCommerce HPOS on/off, admin pages, upgrade and lifecycle. Counts per run are in [06_Plan.md](06_Plan.md) Phase 8. |
| Extension tests | `node --test tests/ext/*.test.mjs` (API client, redirects, token storage, guard mirror, state rules, manifest). |
| End-to-end | `node tests/e2e/run.mjs`: panel + background + content script + REST against WordPress with a mock inbox, axe-core WCAG 2.1 A/AA (0 serious/critical). It also proves that Send is never pressed and that no customer text is stored. |
| Compatibility | PHP 7.4–8.4 (PHPCompatibility + `php -l` in CI), WooCommerce HPOS on/off, MariaDB and SQLite. |
| CI | GitHub Actions runs everything above on every pull request. |
| Upgrade | 0.2.x → 1.0.0 in place, data kept (tokens, answers, dealers, price lists). |
| Security controls (self-reviewed) | Every REST route has a real `permission_callback`; every admin action checks capability and nonce in one dispatcher; SQL only through `$wpdb->prepare` in repositories; all panel text via `textContent`; admin output escaped; CSV export protected against formula injection; tokens hashed, revocable, limited to `rar-iaa/v1`, never sent through a redirect; keys never returned by any endpoint; extension holds host permission only for the business's own site (granted on connect). |
| Privacy controls | Phone numbers, e-mails, addresses, long numbers and the customer's display name are masked before any AI call. Raw conversation text, staff notes and unmasked replies are never written to tables, options, transients or logs (scanned by tests). |
| Facts guard | Every number in every suggestion is checked against approved facts. Unapproved prices or percentages are blocked and replaced by a holding reply. Customer-supplied prices never authorise a quote. |

## B. Evidence from live use (nabiad.com, 27 Oct 2026, owner screenshots)

- **AI providers:** Groq `openai/gpt-oss-120b` test OK (304–404 ms). Gemini `gemini-3.1-flash-lite` test OK (3,358 ms) after transient Google-side `503 UNAVAILABLE`/timeouts. 1.0.0 handles those with a 12-second cut-over to the fallback.
- **Real inbox drafting:** customer photo + "এই প্রোডাক্ট টা available আছে?" + "Price koto?". After the 0.2.1/1.0.0 fixes, suggestions in Bangla quoted the correct website price (৳2,250, matching the shop page) for the recognised product.
- **Feedback fixed in 0.2.1/1.0.0:** English replies to Banglish, manager badge for an unknown product, requests for dealer details from retail customers, unit warning on website prices, photo products (staff note).
- Not yet shown: live **Insert** into Meta's reply box on the real inbox, or a reading of whole conversations with both sides told apart (G0).

## C. Owner checks before approving production

Work through the checks in order. Each one says how to do it. Record the date and result, then sign.

| # | Check | How | Plan task | Date / result |
|---|---|---|---|---|
| 1 | **Backup** of files and database taken; restore path known | Hosting panel → Backups; note the backup date | T5.02 | |
| 2 | **AI provider terms accepted** | Free tiers: data-use consent ticked knowingly. Paid: monthly budget set. Keys only in wp-config or Settings, never shared | T0.07 | |
| 3 | **Approved answers and prices loaded** | At least: hours, contact, delivery, payment, returns. WooCommerce synced, or CSV lists active. Public prices ON only if shop prices are public | T5.04 | |
| 4 | **Quality check on real messages** | 50 real customer messages (any mix). Count suggestions you would send after at most small edits. Count wrong facts. Target: **0 wrong prices**, ≤ 1 other factual error, ≥ 80 % usable | T4.08 | |
| 5 | **Meta usage decision for live mode** | Either keep *Live inbox read & insert* **OFF** (select/paste + Copy, always allowed), or turn it ON after deciding it fits your use of Meta Business Suite. If ON, check 10 conversations: the right messages are read, customer and business sides are correct, **বসান** fills the box, and Send is never pressed | T0.09, T4.09 | |
| 6 | **Hosting load** | Keep *Simultaneous AI calls* = 1. During a busy hour, generate 10 suggestions while browsing the shop; the storefront must stay normal | T0.08 / T4.07 | |
| 7 | **Security review** | An independent reviewer reads `SECURITY.md`, section A and the code. Alternatively, the owner records acceptance of the residual risk | T4.05 | |
| 8 | **Staff ready** | Each person has their own WordPress account and token, knows that **they** press Send, knows **Stop service** on the Overview, and revokes the token if a PC is lost | T5.03 | |
| 9 | **Pilot week** | 1–2 staff for one week; review Logs (usage, handoffs, guard blocks) | T5.05 | |
| 10 | **Go / no-go** | Decide from items 1–9 | T5.06 | |

### Sign-off

| Role | Name | Decision (approve / reject) | Date | Notes |
|---|---|---|---|---|
| Owner | | | | |
| Reviewer (optional) | | | | |

When items 1–8 are recorded, the plan's gate **G2 (pre-pilot)** passes. Items 9–10 pass **G3 (general release)**. Update [06_Plan.md](06_Plan.md) Gates with the same dates.
