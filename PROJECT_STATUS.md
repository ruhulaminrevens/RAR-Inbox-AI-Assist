# Project status — 2026-10-28

**Current stage: 1.0.0 release candidate.** Engineering is complete and verified. Production approval follows the owner checklist in [docs/PRODUCTION_READINESS.md](docs/PRODUCTION_READINESS.md). The product works for any business in Bangladesh with a WordPress site (OD-18). 0.1.0 – 0.2.1 were used on nabiad.com by the owner, and their feedback shaped 0.2.1 and 1.0.0.

| Item | Status |
|---|---|
| WordPress plugin 1.0.0 | **Built and tested** — counts in [06_Plan.md](docs/06_Plan.md) Phase 8; upgrade 0.2.x → 1.0.0 with data verified |
| Chrome extension 1.0.0 | **Built and tested** — connects to any site (host access granted on connect) |
| WooCommerce link | Website price for customers, dealer tiers, live stock, auto-sync with rollback |
| AI providers | Gemini / Groq (free), Anthropic / OpenAI (paid), fallback with 12 s cut-over. **Live calls verified by the owner** (Groq, Gemini; 27 Oct 2026) |
| Real inbox drafting | Owner used it on the real Meta inbox (selection, staff note, website price) — see readiness §B |
| Live inbox insert on the real inbox | Not yet evidenced (G0) — optional; select/paste + Copy is always available |
| Accessibility | axe-core WCAG 2.1 A/AA: 0 serious/critical on panel and admin views |
| Security | Self-review recorded in readiness §A; independent review is owner check C7 |
| Hosting load check, backup, pilot, go/no-go | Owner checks C1, C6, C9, C10 |

Gate summary: AUTH passed · G0 open (live insert evidence) · G1 decisions use approved defaults where recorded (OD-17, OD-18) · G2/G3 = readiness §C sign-off.
