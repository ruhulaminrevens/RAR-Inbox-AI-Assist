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

### Latest v1.0.0 — tested release-candidate build

**Current source version: `v1.0.0`** · [✅ Passing CI run](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/runs/40558540649) · **Not yet approved for production**

The latest v1.0.0 packages are available together as the GitHub Actions artifact **`rar-inbox-ai-assist-build`**.

<div align="center">

### [⬇️ Download latest v1.0.0 build (GitHub Actions)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/actions/runs/40558540649#artifacts)

*Open the run → scroll to **Artifacts** → download **rar-inbox-ai-assist-build**. GitHub sign-in may be required.*

</div>

After extracting the downloaded build artifact, you will have:

| Component | v1.0.0 installable file | Install method |
|:--|:--|:--|
| **WordPress plugin** | `rar-inbox-ai-assist-1.0.0.zip` | WP Admin → Plugins → Add New → Upload Plugin |
| **Chrome extension** | `rar-inbox-ai-assist-extension-1.0.0.zip` | Extract extension ZIP → `chrome://extensions` → Developer mode → Load unpacked |
| **Integrity verification** | `SHA256SUMS` | Verify both ZIPs before installing |

> [!NOTE]
> The v1.0.0 download is a **CI build artifact**, not a permanent GitHub Release. It contains both installable ZIPs inside an outer ZIP and can expire under GitHub artifact retention rules. **No v1.0.0 GitHub Release has been published as of this README update.** For v1.0.0, use the CI link above or build the two ZIPs from source.

### Direct individual ZIP downloads — published v0.2.0 pre-release

If you specifically need the previously published **v0.2.0** version, these are **permanent versioned release asset links** (not the latest v1.0.0):

| Download | Direct file |
|:--|:--|
| **WordPress Plugin v0.2.0** | [⬇️ Download WordPress Plugin (.zip)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v0.2.0/rar-inbox-ai-assist-0.2.0.zip) |
| **Chrome Extension v0.2.0** | [⬇️ Download Chrome Extension (.zip)](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v0.2.0/rar-inbox-ai-assist-extension-0.2.0.zip) |
| **SHA-256 checksums** | [⬇️ Download SHA256SUMS](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/download/v0.2.0/SHA256SUMS) |

[**All published GitHub Releases**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases) · [**v0.2.0 Pre-release**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/releases/tag/v0.2.0) · [**Download full source (not installable)**](https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist/archive/refs/heads/main.zip)

> [!WARNING]
> **Do not mix versions.** Use the **v1.0.0 plugin and v1.0.0 extension together**, or both v0.2.0 files together. Do **not** upload GitHub's full *Source code* ZIP into WordPress, and do not select an unextracted Chrome ZIP for *Load unpacked*. Before updating an existing plugin, back up site files and the database.

### Quick start

1. Download **both ZIPs of the same version** using the links above.
2. **WordPress:** take a files/database backup, install or replace the plugin ZIP, then configure **Inbox AI Assist → Settings → AI providers** with a supported Gemini, Groq, Anthropic or OpenAI key. Use **Test** to verify connectivity.
3. **Business facts:** add approved knowledge-base answers, and sync WooCommerce products/prices when applicable.
4. **Staff access:** create a separate access token for every authorised user.
5. **Chrome:** extract the extension ZIP to a permanent folder, then visit `chrome://extensions`, enable **Developer mode** and choose **Load unpacked**.
6. Open the Meta Business Suite inbox, connect the side panel to the WordPress site, **select or paste a test message**, choose a suggestion, edit, copy and review before manually pressing Send.

**Full step-by-step setup:** [Installation Guide](docs/INSTALL.md) · [Production Readiness Checklist](docs/PRODUCTION_READINESS.md) · [Privacy Guide](docs/PRIVACY.md)

### For maintainers: publish permanent v1.0.0 direct links

When ready to publish this release candidate, use **GitHub → Releases → Draft a new release**, create tag `v1.0.0` from the verified commit, select **pre-release** until owner approval, then publish. The existing [release workflow](.github/workflows/release.yml) is designed to attach `rar-inbox-ai-assist-1.0.0.zip`, `rar-inbox-ai-assist-extension-1.0.0.zip` and `SHA256SUMS`. Verify the assets appear before replacing the CI link with permanent v1.0.0 asset URLs.

```bash
# Build installable ZIPs locally, if preferred
git clone https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist.git
cd RAR-Inbox-AI-Assist
python3 scripts/package.py
# Output: dist/rar-inbox-ai-assist-1.0.0.zip
#         dist/rar-inbox-ai-assist-extension-1.0.0.zip
#         dist/SHA256SUMS
```

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
