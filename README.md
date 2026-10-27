<div align="center">

# RAR Inbox AI Assist

### Smarter replies. Verified business facts. Human control.

**Fact-checked AI reply suggestions for Meta Business Suite inbox — for any business in Bangladesh with a WordPress site**

[![Version](https://img.shields.io/badge/version-v1.0.0-2563eb?style=flat-square)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist)
[![Status](https://img.shields.io/badge/status-release%20candidate-0e7a4e?style=flat-square)](docs/PRODUCTION_READINESS.md)
[![CI](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml/badge.svg)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml)
[![Chrome](https://img.shields.io/badge/Chrome-Manifest%20V3-4285F4?style=flat-square)](extension/)
[![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759B?style=flat-square)](plugin/rar-inbox-ai-assist/)

[**Download**](#-download--installation) · [**Installation Guide**](docs/INSTALL.md) · [**Production readiness**](docs/PRODUCTION_READINESS.md) · [**Privacy**](docs/PRIVACY.md) · [**Documentation**](docs/README.md)

</div>

> [!IMPORTANT]
> **v1.0.0 is a release candidate, not yet approved for production** until the owner signs it off. Engineering is complete and tested. Production approval is the owner's sign-off in [PRODUCTION_READINESS.md](docs/PRODUCTION_READINESS.md) (backup, quality check on real messages, live-mode decision, hosting check, security review). **Messages are never sent automatically.**

---

## ✨ Overview

A staff member selects a customer's Messenger/Instagram message (or pastes it). The side panel then offers **three fact-checked replies** — Professional, Friendly and Short — in Bangla, Banglish or English. The staff member picks one, edits it, copies it into Meta Business Suite and presses Send.

The WordPress plugin holds what the AI may say: approved answers, WooCommerce website prices, optional dealer price tiers, staff access, AI providers and limits. The Chrome extension is a Bangla-first side panel next to the Meta inbox.

```text
Customer's message ─► staff selects / pastes (or live read on click)
        ─► your WordPress site: approved facts + masking ─► AI (free or paid)
        ─► Professional | Friendly | Short ─► fact guard on every number
        ─► staff edits ─► Copy (or Insert) ─► staff presses Send
```

**No auto-send. No invented prices. No background reading of the inbox.**

## 📦 Download & installation

Installable ZIPs are attached to each [GitHub Release](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases) by the release workflow, or built locally with `python3 scripts/package.py`:

| Package | File | Install |
|:--|:--|:--|
| **WordPress plugin** | `rar-inbox-ai-assist-1.0.0.zip` | WordPress → Plugins → Add New → Upload Plugin |
| **Chrome extension** | `rar-inbox-ai-assist-extension-1.0.0.zip` | Unzip → `chrome://extensions` → Developer mode → Load unpacked |
| **Checksums** | `SHA256SUMS` | Verify the downloads |

> [!CAUTION]
> Do not upload the GitHub *Source code* ZIP into WordPress. Upload only the plugin ZIP. Chrome needs the **unzipped** extension folder, with `manifest.json` at its root.

**Quick start:** install the plugin → *Settings → AI providers*: add a free Gemini and/or Groq key and press **Test** → add approved answers → *Products & prices → Sync now* (WooCommerce) → each staff member creates a token → in the side panel enter your site address and the token. Full steps: [docs/INSTALL.md](docs/INSTALL.md).

## 🚀 Key features

| Area | Capabilities |
|:--|:--|
| **Reply assistant** | 3 independently checked suggestions (optional 4th Sales-focused). Staff note for product photos. Rewrite (shorter, polite, formal, Bangla/English) with undo |
| **Languages** | Bangla, Banglish (reply in Bangla script or Banglish) and English, following the customer's recent messages |
| **Business knowledge** | Approved answers with maker-checker approval and effective dates |
| **WooCommerce** | Website price for regular customers; dealer tiers as WooCommerce price ± % or CSV; live stock; automatic sync with rollback snapshots |
| **AI providers** | Google Gemini and Groq/OpenRouter (free tiers), Anthropic and OpenAI (paid); primary + automatic fallback; per-provider tests |
| **Human control** | Select → Edit → Copy; optional live read + Insert on click; never sends |
| **Privacy & safety** | Masking before any AI call, fact guard on every number, no stored conversation text, revocable per-person tokens, handoff flags |
| **Operations** | Per-minute/daily limits, monthly budget, concurrency cap, health checks, audit log, CSV export, kill switch |

## 🛡️ How accuracy is protected

- Suggestions may only use **approved answers and price lists**.
- **Every number is checked.** An unapproved price or percentage is blocked and replaced by a polite holding reply.
- A price the **customer** writes never becomes a quote.
- **Dealer prices** appear only in a conversation linked to an active dealer.
- Regular customers are never asked for dealer details.
- Complaints, credit, discounts, order confirmations and returns are flagged for a person.

These safeguards are built in and tested, but they do not guarantee perfect replies. **Staff review every reply before sending.**

## ✅ Verification

| Item | Status |
|:--|:--|
| Plugin tests (PHP 7.4–8.4, MariaDB + WooCommerce HPOS on/off, SQLite) | Passing — counts in [06_Plan.md](docs/06_Plan.md) Phase 8 |
| Extension unit tests · mock-inbox end-to-end · axe-core WCAG 2.1 A/AA | Passing |
| GitHub CI | [Latest results](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml) |
| Live use on a real site (Gemini + Groq, real inbox drafting) | Evidence recorded in [PRODUCTION_READINESS.md](docs/PRODUCTION_READINESS.md) §B |
| Owner production checks and sign-off | [PRODUCTION_READINESS.md](docs/PRODUCTION_READINESS.md) §C |

## 🧑‍💻 For developers

```bash
git clone https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist.git
cd RAR-Inbox-AI-Assist
python3 scripts/package.py            # plugin ZIP, extension ZIP, SHA256SUMS
npm ci && node --test tests/ext/*.test.mjs
python3 scripts/check_docs.py && python3 scripts/check_tokens.py
```

The WordPress suites need a throwaway WordPress site (`bash scripts/ci/setup-wp.sh`); see [CLAUDE.md](CLAUDE.md) and the [CI workflow](.github/workflows/ci.yml). Never run test fixtures on a live store.

## 📚 Documentation

| File | Purpose |
|:--|:--|
| [Installation Guide](docs/INSTALL.md) | Install, connect AI (free first), products, extension, daily use |
| [Production readiness](docs/PRODUCTION_READINESS.md) | What is verified and the owner's approval checklist |
| [Privacy](docs/PRIVACY.md) | What data is handled, where it goes, what is stored |
| [Product Requirements](docs/01_PRD.md) · [App Flow](docs/02_AppFlow.md) · [UI Spec](docs/03_UI_Spec.md) | Scope, flows, design |
| [Technical Requirements](docs/04_TRD.md) · [Schema](docs/05_Schema.md) · [Plan](docs/06_Plan.md) | Architecture, data, tasks and gates |

Contributions: [CONTRIBUTING.md](CONTRIBUTING.md). Security reports: [SECURITY.md](SECURITY.md). Licence: GPL-2.0-or-later.

---

<div align="center">

*AI drafts the reply. Your team makes the final decision.*

</div>
