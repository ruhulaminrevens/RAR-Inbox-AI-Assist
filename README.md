<div align="center">

# RAR Inbox AI Assist

**AI-assisted customer replies — powered by your business facts, approved by your team.**

A Chrome extension and WordPress plugin for preparing professional replies in Meta Business Suite.

[![Version](https://img.shields.io/badge/version-1.0.0-2563eb?style=flat-square)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/tag/v1.0.0)
[![Release](https://img.shields.io/badge/stage-pre--release-f59e0b?style=flat-square)](docs/PRODUCTION_READINESS.md)
[![CI](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml/badge.svg)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-64748b?style=flat-square)](plugin/rar-inbox-ai-assist/rar-inbox-ai-assist.php)

[**Download WordPress Plugin**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/rar-inbox-ai-assist-1.0.0.zip) &nbsp;·&nbsp; [**Download Chrome Extension**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/rar-inbox-ai-assist-extension-1.0.0.zip) &nbsp;·&nbsp; [**Installation Guide**](docs/INSTALL.md)

</div>

---

> [!IMPORTANT]
> **v1.0.0 is a release candidate, not yet approved for production.** Take a full backup and test on staging first. Complete the [production readiness checklist](docs/PRODUCTION_READINESS.md) before wider deployment. The software **never sends messages automatically**.

## Download v1.0.0

**Official GitHub Pre-release** — both installation packages are available separately, without downloading the full repository or a CI artifact.

| Package | Direct download | Installation |
|:--|:--|:--|
| **WordPress Plugin** | [**Download Plugin ZIP**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/rar-inbox-ai-assist-1.0.0.zip) | WordPress → Plugins → Add New → Upload Plugin |
| **Chrome Extension** | [**Download Extension ZIP**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/rar-inbox-ai-assist-extension-1.0.0.zip) | Extract ZIP → `chrome://extensions` → Developer mode → Load unpacked |
| **SHA-256 checksums** | [Download SHA256SUMS](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/SHA256SUMS) | Verify both packages before installing |

[**View v1.0.0 Release**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/tag/v1.0.0) · [All versions & release history](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases) · [Download source code (not installable)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/archive/refs/heads/main.zip)

> [!CAUTION]
> Install the **same version** of the plugin and extension. Do not upload the repository's **Source code ZIP** to WordPress. For Chrome, extract the extension ZIP first and select the folder containing `manifest.json`. If updating an existing WordPress install, back up site files and the database.

## How it works

```text
Customer message in Meta Business Suite
                 ↓
Staff selects or pastes message text
                 ↓
WordPress API → approved business facts → AI provider
                 ↓
     Professional · Friendly · Short
                 ↓
       Select → Edit → Copy
                 ↓
         Staff reviews & Sends
```

By default, the extension uses **selected or pasted text** and Copy. Automated live conversation capture and composer insertion are optional features requiring separate Meta policy/permission checks and testing. There is **no automatic sending**.

## Quick installation

1. **WordPress:** Back up your site. Upload the [plugin ZIP](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/rar-inbox-ai-assist-1.0.0.zip) to *Plugins → Add New → Upload Plugin* and activate it.
2. **AI provider:** In *Inbox AI Assist → Settings → AI providers*, configure Gemini, Groq/OpenAI-compatible, Anthropic or OpenAI. Test the connection and set appropriate rate/budget limits.
3. **Business knowledge:** Add and approve FAQs, policies and contact information. If WooCommerce is installed, use *Products & prices → Sync now* to enable approved product facts and pricing.
4. **Staff token:** Create a separate token for each staff member under *Inbox AI Assist → Access tokens*.
5. **Chrome:** Extract the [extension ZIP](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v1.0.0/rar-inbox-ai-assist-extension-1.0.0.zip); open `chrome://extensions`, enable Developer mode and select **Load unpacked**. Open Meta Business Suite and connect the side panel using your WordPress address and staff token.
6. **First test:** Select or paste a customer message, generate three suggestions, edit a selected reply, Copy and review it before manually pressing Send.

**Full instructions:** [Installation & troubleshooting](docs/INSTALL.md) · [Production checklist](docs/PRODUCTION_READINESS.md)

## Features

| Capability | Description |
|:--|:--|
| **AI reply suggestions** | Three independently checked options: Professional, Friendly and Short; optional Sales-focused fourth |
| **Language support** | Bangla, Banglish and English responses |
| **WooCommerce integration** | Product details, approved website prices, stock and dealer-tier rules |
| **Multiple AI providers** | Gemini, Groq/OpenAI-compatible, Anthropic and OpenAI, with configurable fallback |
| **Business knowledge** | Reviewed FAQs, business hours, delivery, payment and return policies |
| **Human-in-the-loop** | Explicit selection, editing and manual sending; no background messaging |
| **Security controls** | PII masking, per-user tokens, fact checks, usage caps, audit and emergency stop |

### Designed for fact-based replies

The assistant uses approved information instead of making up product prices or policies. Dealer-tier prices require an active, verified dealer link; customer-supplied numbers do not authorise a company price quote. Sensitive cases can be escalated for human review.

These safeguards reduce risk but **do not guarantee perfect answers**. Staff must verify replies before sending.

## Quality & release status

| Check | Status |
|:--|:--|
| PHP/WordPress plugin tests, WooCommerce compatibility | [Automated CI passing](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml) |
| Extension unit tests and mock-inbox E2E tests | [Automated CI passing](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/workflows/ci.yml) |
| Real-site provider/draft evidence | [Recorded in production-readiness notes](docs/PRODUCTION_READINESS.md) |
| Live Meta read/insert mode | Requires independent policy/permission and live-composer verification |
| Full hosting load, independent security review, owner sign-off | Follow [remaining production gates](docs/PRODUCTION_READINESS.md) |

**Release status:** Pre-release. Production approval is separate from successful CI tests.

## Developer setup

```bash
git clone https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist.git
cd RAR-Inbox-AI-Assist

# Build separately installable ZIPs and checksums
python3 scripts/package.py

# Extension and documentation checks
npm ci
npm run test:ext
python3 scripts/check_docs.py
python3 scripts/check_tokens.py
```

WordPress integration/E2E tests require an isolated test installation. See the [CI workflow](.github/workflows/ci.yml), [tests](tests/) and [developer guidance](CLAUDE.md). Never run test fixtures against a live shop.

## Documentation

[Installation](docs/INSTALL.md) · [Production Readiness](docs/PRODUCTION_READINESS.md) · [Privacy](docs/PRIVACY.md) · [PRD](docs/01_PRD.md) · [App Flow](docs/02_AppFlow.md) · [UI Spec](docs/03_UI_Spec.md) · [Technical Requirements](docs/04_TRD.md) · [Database Schema](docs/05_Schema.md) · [Implementation Plan](docs/06_Plan.md)

[Contributing](CONTRIBUTING.md) · [Security](SECURITY.md) · [Changelog](CHANGELOG.md)

---

<div align="center">

**Developed for Nabiad Distribution Limited · Built for human-reviewed customer support.**

*AI prepares the draft. Your team makes the final decision.*

</div>
