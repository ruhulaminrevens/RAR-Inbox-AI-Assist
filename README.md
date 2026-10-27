<div align="center">

# RAR Inbox AI Assist

### Smarter replies. Verified business facts. Human control.

**AI-powered reply suggestions for Meta Business Suite Messenger — built for Nabiad Distribution Limited**

[![Version](https://img.shields.io/badge/version-v0.2.0-2563eb?style=flat-square)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist)
[![Development stage](https://img.shields.io/badge/status-pre--pilot-f59e0b?style=flat-square)](PROJECT_STATUS.md)
[![CI](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml/badge.svg)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml)
[![Chrome](https://img.shields.io/badge/Chrome-Manifest%20V3-4285F4?style=flat-square)](extension/)
[![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759B?style=flat-square)](plugin/rar-inbox-ai-assist/)

[**Download Builds**](#-download--installation) · [**Installation Guide**](docs/INSTALL.md) · [**Features**](#-key-features) · [**Tests**](#-verification--testing) · [**Documentation**](docs/README.md)

</div>

> [!IMPORTANT]
> **v0.2.0 is a pre-pilot build, not approved for production.** Use a staging WordPress site first. Live Meta inbox capture/insertion is **disabled by default** and still requires platform-permission and real-inbox verification. Suggestions are never automatically sent.

---

## ✨ Overview

RAR Inbox AI Assist helps a human support or sales representative prepare **three context-aware, fact-checked replies** for a customer message. The representative chooses a tone, edits the text and copies it into Meta Business Suite. An optional fourth sales-focused suggestion can be enabled.

The WordPress plugin provides approved business knowledge, WooCommerce-aware product and price information, staff access controls and AI-provider integration. The Chrome extension provides a lightweight Bengali-first reply workspace directly alongside Messenger.

### Human-in-the-loop workflow

```text
Customer's Messenger message
           ↓
Staff selects text (or pastes it into the side panel)
           ↓
WordPress API → approved facts + PII masking → AI provider
           ↓
Professional   |   Friendly   |   Short   |   Sales-focused (optional)
           ↓
Select a suggestion → Review/Edit → Copy → Paste → Staff clicks Send
```

**No auto-send. No guessed dealer prices. No automatic inbox scraping in the default safe mode.**

## 📦 Download & installation

### Direct, verified v0.2.0 build

The current **successful CI build** contains two installation packages:

| Package | File | How to obtain |
|:--|:--|:--|
| **WordPress plugin** | `rar-inbox-ai-assist-0.2.0.zip` | [**Download verified CI build**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/runs/40515346439#artifacts) |
| **Chrome extension** | `rar-inbox-ai-assist-extension-0.2.0.zip` | [**Download verified CI build**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/runs/40515346439#artifacts) |
| Integrity checks | `SHA256SUMS` | Included in the same build artifact |

**Download steps:** Open the CI build link above → under **Artifacts**, download `rar-inbox-ai-assist-build` (GitHub sign-in may be needed) → extract that artifact once to obtain **both** installation ZIPs and `SHA256SUMS`. Workflow artifacts are temporary; they are **not permanent release-download URLs**.

[**All CI Builds**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml) · [**Releases (none published yet)**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases) · [**Full Source ZIP**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/archive/refs/heads/main.zip)

> [!CAUTION]
> **Do not upload the complete GitHub Source Code ZIP into WordPress.** Upload only `rar-inbox-ai-assist-0.2.0.zip`. Chrome requires the **extracted extension ZIP folder**, with `manifest.json` at its root.

### Quick start: WordPress (staging recommended)

1. **Back up both files and database**, then prepare a WordPress staging site.
2. In **WP Admin → Plugins → Add New → Upload Plugin**, upload `rar-inbox-ai-assist-0.2.0.zip` and activate.
3. Open **Inbox AI Assist → Settings**. Configure an AI provider (Gemini, Anthropic, OpenAI or a supported OpenAI-compatible endpoint), the correct token pricing and budget limits. Keep keys in secure server configuration where possible.
4. Use **Test connection**, then add and approve the knowledge-base facts, dealer/price rules and product details your team is allowed to share.
5. With LiteSpeed Cache, exclude the private API route `/wp-json/rar-iaa/`. Create an individual access token for each staff member.

### Quick start: Chrome

1. Extract `rar-inbox-ai-assist-extension-0.2.0.zip` to a permanent folder on your Windows PC.
2. Open `chrome://extensions` → turn on **Developer mode** → select **Load unpacked** → choose the extracted folder containing `manifest.json`.
3. Open [Meta Business Suite Inbox](https://business.facebook.com/latest/inbox) and the extension's side panel.
4. Connect using the WordPress site address and a staff token created in **Inbox AI Assist → Access tokens**.
5. **Safe first test:** select a test customer's message → generate suggestions → select and edit one → **Copy** → manually paste and review in the Messenger composer. **You** click Send.

**Detailed instructions:** [docs/INSTALL.md](docs/INSTALL.md) — requirements, API settings, CSV format, caching, rollback and troubleshooting.

---

## 🚀 Key features

| Area | Capabilities |
|:--|:--|
| **AI reply assistant** | 3 independently checked suggestions — Professional, Friendly and Short; optional 4th Sales-focused |
| **Languages** | Bangla, Banglish and English reply workflows |
| **Business knowledge** | Approved FAQ answers, hours, contact details, ordering and policies |
| **WooCommerce integration** | Product catalogue, website prices, stock status, dealer-tier price rules and sync snapshots |
| **Dealer pricing** | Verified dealer-to-tier links; confidential dealer prices withheld from unlinked contacts |
| **AI providers** | Gemini, Anthropic, OpenAI and configured OpenAI-compatible endpoints, with primary/fallback support |
| **Human control** | Select → Edit → Copy; optional live Insert remains off until separately authorised/tested |
| **Privacy and safety** | PII masking before AI egress, per-variant fact guard, revocable staff tokens and human handoff |
| **Operational controls** | Rate limits, cost budget, concurrency cap, diagnostics, audit and emergency kill switch |

### What's new in v0.2.0

- **WooCommerce-powered pricing:** Products and stock can use WooCommerce data, with tier-based rules, website-price eligibility, syncing and rollback.
- **More AI choices:** Google Gemini and OpenAI-compatible providers alongside Anthropic/OpenAI, with configurable fallback.
- **Improved administration:** Setup checklist, Products & Prices page, provider configuration and enhanced plugin navigation.
- **Safer panel connection:** Improved handling of the site's `www` variation and cross-origin redirects.

See the complete [CHANGELOG.md](CHANGELOG.md).

## 🛡️ How reply accuracy is protected

- **Only approved business facts** support company claims and quotations.
- Dealer-tier prices require a **linked, active dealer**; a price supplied by a customer does not become an approved quote.
- Unsupported prices and percentages trigger guard handling instead of confident fabricated answers.
- Complaints, credit, discounts, order confirmation and other sensitive cases can be escalated to a human.
- Customer message text is masked before AI-provider calls, with controls designed to prevent persistence of raw conversation text.

These are **implemented design safeguards**, not a guarantee of perfect replies. **Staff must review every draft before sending.**

## ✅ Verification & current limitations

| Item | Latest repository-reported status |
|:--|:--|
| GitHub CI workflow | **Passing** on v0.2.0 main branch |
| PHP plugin checks | **592/592** on MariaDB + WooCommerce; **532/532** on SQLite |
| Extension unit tests | **23/23** |
| Mock-inbox end-to-end tests | **21/21**, including accessibility checks |
| Real Meta Business Suite capture/insert | **Not verified**; disabled by default |
| Real provider API key + billing workflow | **Not verified** |
| Hostinger storefront load/campaign benchmark | **Not completed** |
| Independent security review | **Pending** |
| Production approval | **Not approved** |

[**View CI Results**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml) · [**Live Project Status**](PROJECT_STATUS.md) · [**Implementation Plan**](docs/06_Plan.md)

## 🧑‍💻 For developers

```bash
git clone https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist.git
cd RAR-Inbox-AI-Assist

# Generate the two installable v0.2.0 ZIPs + checksums
python3 scripts/package.py

# Chrome extension unit tests
npm ci
npm run test:ext

# Documentation and token-design checks
python3 scripts/check_docs.py
python3 scripts/check_tokens.py
```

The WordPress integration suite needs a dedicated WordPress test environment; see [docs/INSTALL.md](docs/INSTALL.md), [CI workflow](.github/workflows/ci.yml) and [tests/](tests/). Never run test fixtures on the live store.

## 📚 Project documentation

| File | Purpose |
|:--|:--|
| [Product Requirements](docs/01_PRD.md) | Scope, user stories and acceptance criteria |
| [Application Flow](docs/02_AppFlow.md) | States, user journeys and fallbacks |
| [UI Specification](docs/03_UI_Spec.md) | Design tokens and components |
| [Technical Requirements](docs/04_TRD.md) | Architecture, APIs and security |
| [Database Schema](docs/05_Schema.md) | Data tables, integrity and permissions |
| [Implementation Plan](docs/06_Plan.md) | Tasks, quality gates and release plan |
| [Installation Guide](docs/INSTALL.md) | Installation and testing procedures |

Contributions should follow [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately; see [SECURITY.md](SECURITY.md).

---

<div align="center">

**Developed for Nabiad Distribution Limited**  
*AI drafts the reply. Your team makes the final decision.*

[Repository](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist) · [Download Build](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/runs/40515346439#artifacts) · [Installation Guide](docs/INSTALL.md)

</div>
