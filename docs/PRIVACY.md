# Privacy policy — RAR Inbox AI Assist (Chrome extension and WordPress plugin)

*Version 1.0.0 · 28 Oct 2026.* This policy describes the software. Each business that installs it is the controller of its customers' data and should mention AI-assisted replies in its own privacy notice where required.

## What the extension does

The Chrome extension shows a side panel next to Meta Business Suite. It reads page text **only when a staff member clicks**: either text they selected or pasted, or the open conversation when the business has turned on "Live inbox read & insert". It never reads pages in the background and never sends messages.

## Data handled

| Data | Where it goes | Stored? |
|---|---|---|
| Customer message text the staff member selects, pastes or (live mode) the open conversation's last ≤ 20 messages | Sent over HTTPS to **the business's own WordPress site** | **No.** Used in memory for one request only |
| Staff note (product name, optional) | Same as above | **No** |
| Masked text (phone numbers, e-mails, addresses, long numbers and the customer's name replaced by placeholders) | Sent by the WordPress site to the AI provider the business chose (Google Gemini, Groq/OpenRouter, Anthropic, OpenAI) | Not by this software. The provider's own terms apply (free tiers may let the provider use or review the text) |
| Suggestions | Shown to the staff member; copied or inserted only when they click | **No** (unless the business turns on "Masked text in logs", which stores masked text for quality review and deletes it after the retention period) |
| Access token, site address, theme choice | Chrome's local extension storage (not readable by web pages or content scripts) | Yes, on that PC until the staff member disconnects |
| Usage metadata: time, staff user, request ID, guard result, outcome (copied/inserted/rejected), token counts, cost estimate | The business's WordPress database | Yes, deleted after the retention period (7–90 days, default 30) |

The extension sends nothing to the developer or any analytics service. It contains no advertising and no tracking.

## Permissions (Chrome)

- `sidePanel`, `storage`, `clipboardWrite`: the panel itself, saving the connection, and the **কপি** button.
- Access to `business.facebook.com`: the panel works next to the Meta inbox. Selection, capture and insert happen only on a staff member's click.
- Access to the business's own site: requested once when the staff member connects (Chrome asks), so the extension can call that site's API.

## Control and deletion

- A staff member can disconnect in the panel (the token is revoked) or remove the extension.
- The business can revoke any token, stop the service (kill switch), shorten retention, or delete all plugin data on uninstall (Settings → Advanced).

## Contact

Questions about how a business uses this tool go to that business. For questions about the software, see the repository: https://github.com/ruhulaminrevens/RAR-Inbox-AI-Assist (security reports: `SECURITY.md`).
