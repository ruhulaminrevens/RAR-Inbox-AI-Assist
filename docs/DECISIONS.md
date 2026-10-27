# Unresolved owner / implementation decisions — architecture v0.2

Safe defaults are **not** signed-off decisions. Record date, approver and evidence before G1.

Plugin/extension v0.1.0 (27 Oct 2026) implements every safe default below so the software is usable while decisions are pending. Changing a default later is a setting or a small code change, noted in the last column.

| ID | Decision | Safe default | Implemented in v0.1.0 as | Status |
|---|---|---|---|---|
| OD-1 | Dealer price source | CSV import | wp-admin → Price lists → Import CSV (draft → activate, previous list kept for rollback) | OPEN |
| OD-2 | Product families | Approved listed products | Only items in the active list for the linked dealer's tier | OPEN |
| OD-3 | Exact stock quantity | Do not disclose, buckets only | Setting "Exact stock quantity" OFF | OPEN |
| OD-4 | Dealer tier and identity | Manually verified active dealer link | Staff link a conversation to a dealer in the panel; needs `rar_iaa_link_dealer` | OPEN |
| OD-5 | Outstanding/credit | Out of scope, human handoff | Handoff rule `credit_outstanding` | OPEN |
| OD-6 | Staff roles | Individual WP accounts, least privilege | Administrator + Shop manager mapped automatically; extra roles selectable in Settings | OPEN |
| OD-7 | AI provider/cost account | Verify model, account, USD $10 estimated cap | Anthropic `claude-haiku-5-5`, monthly cap USD 10, token prices must be entered before drafts work | OPEN |
| OD-8 | Who approves KB? | Separate approver / admin | Maker-checker; a sole administrator may self-approve (audited) | OPEN |
| OD-9 | Name and repo | RAR Inbox AI Assist | Plugin slug `rar-inbox-ai-assist`, extension "Inbox AI Assist" | OPEN |
| OD-10 | Retail vs dealer response/privacy | Unverified contacts get public facts only | Unlinked → `audience: unverified`, no tier prices; MRP only if "Public MRP" is ON (default OFF) | OPEN |
| OD-11 | Meta DOM capture/insert platform eligibility | Disabled until permission evidenced; selected text/Copy | Setting "Live inbox read & insert" OFF | OPEN |
| OD-12 | Shared-hosting capacity | One in-flight AI call pending benchmark, isolated backend on failure | "Simultaneous AI calls" = 1 | OPEN |
| OD-13 | Optional fourth variant | Off by default | "Suggestions per click" = 3 | OPEN |
| OD-15 | Dealer price per tier from WooCommerce | Tier rule = CSV until the admin sets "WooCommerce ± %"; public website tier = current price | Products & prices → Price rule per tier | OPEN |
| OD-16 | Free AI tiers (Gemini / compatible) for masked conversations | Off; requires explicit consent box and "free tier" flag per provider | Settings → AI providers | OPEN |
| OD-17 | Staff note for photos (product name typed by staff) | Optional, request-only, masked, never stored | Panel "পণ্য / নোট" field (v0.2.1) | APPROVED by owner 2026-10-27 (chat) |
| OD-14 | Atomic cost reservation storage/cleanup | Transactional reservation ledger, precise DDL needed | `cost_reservations` table, [Schema §14](05_Schema.md#14-cost_reservations-v010) | DDL written; sign-off OPEN |

G2 is **pre-pilot** only; G3 follows pilot and owner go/no-go. No permission from Meta can be substituted by owner risk acceptance.
