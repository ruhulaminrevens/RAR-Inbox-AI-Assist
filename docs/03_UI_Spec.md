# 03 — Design System & UI/UX Specification

**Status:** Draft v0.2 · 27 Oct 2026
**Related:** [01_PRD](01_PRD.md) · [02_AppFlow](02_AppFlow.md) · [04_TRD](04_TRD.md) · [05_Schema](05_Schema.md) · [06_Plan](06_Plan.md)

**Rule:** no agent may add colours, spacing, type sizes, radii, shadows, icons or component patterns that are not in this document. Change this document first, then the code.

Scope: the **extension side panel** gets the full design system below. The **wp-admin pages** use WordPress core admin markup and classes (`wrap`, `form-table`, `widefat`, `notice`, `button`, `button-primary`) plus a small set of the tokens below for status badges and cards, so they look correct with or without RAR Admin Studio.

---

## 1. Design principles

1. **Trust before speed.** Facts used, warnings and handoff are always visible next to the draft — never hidden behind a click.
2. **The human sends.** The strongest button is "বসান" (Insert), never "Send". There is no Send-looking control anywhere.
3. **Calm, compact, Bangla-first.** Narrow panel (320–480 px), Bangla labels with English terms where clearer, generous line height for Bangla conjuncts.
4. **One clear next action** per state.
5. **Brand continuity.** Colours follow the Nabiad / RAR Admin Studio family (main green #0E7A4E, deep green, gold accent), light workspace by default.

## 2. Colour tokens

All tokens are CSS custom properties on `:root` with prefix `--iaa-`. Dark mode is applied by `prefers-color-scheme: dark` unless the user picks Light/Dark in S-12 (`data-theme="light|dark"` on `<html>`).

### 2.1 Light theme

| Token | HEX | Use |
|---|---|---|
| `--iaa-bg` | `#F6F8F7` | Panel background |
| `--iaa-surface` | `#FFFFFF` | Cards, dialogs, inputs |
| `--iaa-surface-2` | `#EEF3F0` | Secondary surfaces, chips |
| `--iaa-border` | `#D5DFD9` | Decorative dividers (non-essential) |
| `--iaa-border-strong` | `#6F8679` | Input and control borders (meets 3:1) |
| `--iaa-text` | `#13221B` | Body text |
| `--iaa-text-muted` | `#51645A` | Secondary text, captions |
| `--iaa-text-disabled` | `#7D8F86` | Disabled labels (exempt from contrast rule, still 3.4:1) |
| `--iaa-primary` | `#0E7A4E` | Primary buttons, links, active states |
| `--iaa-primary-hover` | `#0B6440` | Primary hover/pressed |
| `--iaa-on-primary` | `#FFFFFF` | Text on primary |
| `--iaa-secondary` | `#0B2E22` | Header bar background |
| `--iaa-on-secondary` | `#FFFFFF` | Text on header |
| `--iaa-accent` | `#C8962E` | Decorative accent only (brand stripe, dealer tier dot). **Never** for text on white. |
| `--iaa-on-accent` | `#1A1205` | Text on accent backgrounds |
| `--iaa-success` | `#1E6B45` | Success text/icons |
| `--iaa-success-bg` | `#E7F5EC` | Success background |
| `--iaa-warning` | `#8A4B00` | Warning text/icons |
| `--iaa-warning-bg` | `#FFF4E0` | Warning background, highlighted unmatched numbers |
| `--iaa-danger` | `#B42318` | Error/blocked text/icons |
| `--iaa-danger-bg` | `#FDECEA` | Error background |
| `--iaa-info` | `#1D5FA8` | Info text, focus ring |
| `--iaa-info-bg` | `#E8F0FA` | Info background |
| `--iaa-focus` | `#1D5FA8` | Focus ring |

### 2.2 Dark theme

| Token | HEX |
|---|---|
| `--iaa-bg` | `#0B1712` |
| `--iaa-surface` | `#10211A` |
| `--iaa-surface-2` | `#163026` |
| `--iaa-border` | `#24382F` |
| `--iaa-border-strong` | `#5E7A6D` |
| `--iaa-text` | `#E8F1EC` |
| `--iaa-text-muted` | `#A3B6AC` |
| `--iaa-text-disabled` | `#6F8379` |
| `--iaa-primary` | `#34C17F` |
| `--iaa-primary-hover` | `#4FD394` |
| `--iaa-on-primary` | `#06120C` |
| `--iaa-secondary` | `#06100C` |
| `--iaa-on-secondary` | `#E8F1EC` |
| `--iaa-accent` | `#E2B04F` |
| `--iaa-on-accent` | `#1A1205` |
| `--iaa-success` | `#4CC38A` |
| `--iaa-success-bg` | `#12301F` |
| `--iaa-warning` | `#F0B44C` |
| `--iaa-warning-bg` | `#33250B` |
| `--iaa-danger` | `#F2867B` |
| `--iaa-danger-bg` | `#3A1714` |
| `--iaa-info` | `#7FB2F0` |
| `--iaa-info-bg` | `#132741` |
| `--iaa-focus` | `#7FB2F0` |

### 2.3 Measured contrast (WCAG 2.x formula, computed 27 Oct 2026)

| Pair | Ratio | Requirement |
|---|---|---|
| L text / surface | 16.51 | ≥ 4.5 ✔ |
| L text-muted / bg | 5.93 | ≥ 4.5 ✔ |
| L on-primary / primary | 5.37 | ≥ 4.5 ✔ |
| L primary (link) / surface | 5.37 | ≥ 4.5 ✔ |
| L warning / warning-bg | 6.24 | ≥ 4.5 ✔ |
| L danger / danger-bg | 5.75 | ≥ 4.5 ✔ |
| L success / success-bg | 5.75 | ≥ 4.5 ✔ |
| L info / info-bg | 5.62 | ≥ 4.5 ✔ |
| L border-strong / surface | 3.92 | ≥ 3 (UI component) ✔ |
| L focus / surface | 6.45 | ≥ 3 ✔ |
| L on-accent / accent | 6.94 | ≥ 4.5 ✔ |
| D text / surface | 14.53 | ✔ |
| D text-muted / surface | 7.85 | ✔ |
| D on-primary / primary | 8.26 | ✔ |
| D warning / surface | 9.04 | ✔ |
| D danger / surface | 6.75 | ✔ |
| D border-strong / surface | 3.57 | ≥ 3 ✔ |

`--iaa-accent` on white is 2.67 — it is therefore restricted to decoration. Any new pair must be measured and added here.

## 3. Typography

- **Family:** `"Hind Siliguri", system-ui, "Segoe UI", sans-serif` for all panel text (covers Bangla and Latin). Bundled as WOFF2, weights **400, 500, 600** only (SIL OFL 1.1; licence file shipped). Monospace for request IDs: `ui-monospace, "Cascadia Mono", Consolas, monospace`.
- **Minimum size:** 13 px. Bangla body line height 1.6.

| Token | Size / line height | Weight | Use |
|---|---|---|---|
| `--iaa-fs-xs` | 12px / 1.4 | 500 | Only badge text and request ID (Latin/digits) |
| `--iaa-fs-sm` | 13px / 1.5 | 400 | Captions, chip text, help text |
| `--iaa-fs-md` | 14px / 1.6 | 400 | Body, buttons, inputs |
| `--iaa-fs-lg` | 16px / 1.6 | 400 | Draft text area |
| `--iaa-fs-xl` | 18px / 1.4 | 600 | Section headings |
| `--iaa-fs-2xl` | 20px / 1.3 | 600 | Panel title |

Weights: `--iaa-fw-regular: 400`, `--iaa-fw-medium: 500`, `--iaa-fw-semibold: 600`.

## 4. Spacing, layout, radius, shadow

- **Spacing scale (4 px base):** `--iaa-space-1: 4px`, `-2: 8px`, `-3: 12px`, `-4: 16px`, `-5: 20px`, `-6: 24px`, `-8: 32px`.
- **Panel layout:** single column; side gutter 12 px (`--iaa-space-3`); vertical rhythm 12 px between blocks; sticky header (48 px) and sticky action bar at the bottom (64 px) so Insert/Copy are always visible.
- **Widths:** Chrome side panel width is user-controlled; design for **320 px minimum**, comfortable 360–480 px; content max-width 560 px centred if wider.
- **Breakpoints (panel):** `< 360px` compact (action labels shorten: "বসান", "কপি" icons + text stay; rewrite menu collapses to one button); `≥ 360px` default. wp-admin follows WordPress breakpoints (782 px, 960 px).
- **Radius:** `--iaa-radius-sm: 6px` (inputs, chips), `--iaa-radius-md: 10px` (cards, buttons), `--iaa-radius-lg: 14px` (dialogs), `--iaa-radius-pill: 999px` (badges).
- **Shadows:** `--iaa-shadow-sm: 0 1px 2px rgba(11,46,34,.08)`; `--iaa-shadow-md: 0 4px 16px rgba(11,46,34,.12)` (dialogs, toasts). Dark theme: `rgba(0,0,0,.4)` / `.5`.
- **Touch/click targets:** ≥ 32 px high (sm), ≥ 40 px (md); 8 px gap between adjacent targets (meets WCAG 2.2 target-size minimum 24 px).

## 5. Iconography

Lucide line icons (ISC licence), inline SVG, 16 px in text, 20 px in buttons, stroke 1.75, `currentColor`. Allowed set: `sparkles` (Draft), `clipboard-copy` (Copy), `corner-down-left` (Insert), `refresh-cw` (Regenerate), `wand-2` (Rewrite), `link-2` (Link dealer), `user-round-check` (Handoff), `shield-alert` (Blocked), `triangle-alert` (Warning), `circle-check` (Success), `circle-x` (Error), `search`, `settings`, `x`, `chevron-down`, `info`, `plug` (Connect), `power` (Service disabled), `book-open` (Quick answers). Icons never stand alone without a text label or `aria-label`.

## 6. Components

Naming: CSS classes `iaa-{component}` with BEM modifiers `iaa-btn--primary`, `iaa-btn--sm`; JS modules `ui/{component}.js` exporting `render{Component}(props)`; no inline styles except computed positions.

### 6.1 Buttons — `iaa-btn`

| Variant | Background / text | Border | Use |
|---|---|---|---|
| `--primary` | primary / on-primary | none | Draft, Insert, Connect (one per view) |
| `--secondary` | surface / primary | 1px border-strong | Copy, Link dealer |
| `--ghost` | transparent / text | none | Regenerate, Paste, Cancel |
| `--danger` | danger-bg / danger | 1px danger | Reject, Disconnect, Revoke |

Sizes: `--sm` 32 px height, 12 px horizontal padding, fs-sm; `--md` 40 px, 16 px padding, fs-md (default).
States: hover → primary-hover / surface-2; active → translateY(1px); focus-visible → 2px `--iaa-focus` outline with 2px offset; disabled → `opacity:.5`, `cursor:not-allowed`, `aria-disabled="true"`; loading → spinner (16 px) replaces icon, label kept, button disabled.

### 6.2 Form controls — `iaa-field`, `iaa-input`, `iaa-textarea`, `iaa-select`
- Label above (fs-sm, medium); input 40 px (textarea min 6 lines for drafts, auto-grow to 16 lines); 1px `--iaa-border-strong`, radius-sm, surface background.
- Focus: border `--iaa-focus` + 2px ring. Error: border `--iaa-danger`, message below with `circle-x` icon, `aria-invalid="true"`, `aria-describedby`. Help text fs-sm muted.
- Character counter on draft textarea: "১২৩ / ২০০০" style counter shown only above 1,800.

### 6.3 Suggestion cards and selected draft — `iaa-draft`
Order: three (optional four) tone cards with independent guard/handoff/fact indicators → explicit radio selection → selected editable textarea → Insert/Copy actions → audience/dealer chip and request ID. Unmatched numbers from the guard are listed in WarningList and, in the read-only preview (shown before editing), wrapped in `<mark class="iaa-mark--warn">` (warning-bg background, warning text).

### 6.4 Chips and badges
- `iaa-chip` (facts): surface-2 background, radius-sm, fs-sm, icon `book-open` (KB) or `info` (price item); truncate at 28 chars with full text in `title`.
- `iaa-badge` statuses: `--approved` success, `--draft` info, `--archived` muted, `--active` success, `--blocked` danger, `--warned` warning, `--handoff` warning. Pill radius, fs-xs, medium.
- `iaa-dealer-chip`: accent dot (decorative) + "D-014 · নাম · Tier A"; when unknown: muted "ডিলার লিংক নেই" + Link button.

### 6.5 Alerts and banners — `iaa-alert`
`--info`, `--success`, `--warning`, `--danger`. Icon + title (medium) + body. Blocked and handoff banners use `role="alert"`; others `role="status"`.

### 6.6 Dialog — `iaa-dialog`
Native `<dialog>` with `aria-labelledby`; radius-lg; shadow-md; max-width 440 px; focus trapped; Esc closes; backdrop `rgba(6,16,12,.5)`.

### 6.7 Dropdown / menu — `iaa-menu`
Rewrite menu: button with `aria-haspopup="menu"`; items as `role="menuitem"`; arrow keys move; Esc closes.

### 6.8 Toast — `iaa-toast`
Bottom of panel above action bar; 4 s auto-dismiss (8 s for errors); `role="status"`; at most one at a time; close button.

### 6.9 Tables (wp-admin)
WordPress `widefat striped` tables; status shown with `iaa-badge`; row actions as WordPress row-action links; pagination via `tablenav`.

### 6.10 Header and navigation
Panel header: secondary background, 48 px, title "Inbox AI Assist", connection dot (success = connected, danger = disconnected, warning = disabled), ⚙ button. No tabs in the panel; views switch by state.

### 6.11 Skeleton, empty, error
- Skeleton: three grey bars (surface-2) with 1.2 s shimmer; respects reduced motion (static).
- Empty state: icon 32 px muted, one-line title, one-line help, one button.
- Error state: `iaa-alert--danger` + actions defined in [02_AppFlow S-08](02_AppFlow.md#s-08-error).

## 7. Accessibility

- Target WCAG 2.2 AA: contrast per §2.3; focus visible on every control (`:focus-visible`, never removed); focus not obscured by sticky header/action bar (scroll-padding 64 px); target size ≥ 24 px; no information by colour alone (icons + text on badges and banners).
- Live region `#iaa-live` (`aria-live="polite"`) announces: generating, draft ready, number of warnings, inserted, copied, errors.
- All text in Bangla has `lang="bn"`; English drafts set `lang="en"` on the textarea.
- Keyboard map in [02_AppFlow §10](02_AppFlow.md#10-accessibility-and-keyboard).
- Testing: axe-core run on panel views and admin pages (T4.06) with zero serious/critical issues; manual keyboard pass.

## 8. Motion

- Durations: `--iaa-dur-fast: 120ms`, `--iaa-dur-base: 180ms`; easing `cubic-bezier(.2,.7,.2,1)`.
- Allowed: fade/slide 8 px for toasts and dialogs, shimmer for skeleton, spinner rotation.
- `@media (prefers-reduced-motion: reduce)`: no slide, no shimmer, spinner replaced with static "…" text; durations 0.

## 9. Copy (microcopy) rules

- Bangla first; keep English technical words that staff already use (Insert/Copy may appear in tooltips).
- Primary button labels (fixed): "খসড়া তৈরি করুন", "বসান", "কপি", "আবার তৈরি", "লিংক করুন", "সংযোগ করুন".
- Errors say what happened and what to do, never blame the user. Show request ID on errors.
- Never use the word "Send"/"পাঠান" on any control.

## 10. Design-token implementation

- Single file `extension/sidepanel/tokens.css` holds §2–§4 and §8 tokens for both themes; components use only `var(--iaa-*)`.
- wp-admin: `plugin/rar-inbox-ai-assist/assets/admin.css` imports the same token values (copied by `scripts/sync-tokens.py`, checked in CI that both files match).
- A CI check (`scripts/check-tokens.py`) fails if a hex colour, `px` size outside the scale, or `font-family` appears outside `tokens.css`/`admin.css` token blocks.

## 11. v0.2 selectable reply component contract

Use `iaa-suggestion-card` in a vertical stack down to 320 px usable width, existing `--iaa-*` tokens, and a native radio/radiogroup with keyboard arrows and Space selection. Expose labels Professional (পেশাদার), Friendly (আন্তরিক), Short (সংক্ষিপ্ত), optional Sales-focused (বিক্রয় সহায়ক). States: loading, available, selected, warned, blocked, handoff, stale. Each variant has its **own** fact chips/guard warnings. Blocked commercial claims are not insertable. On selection, fill the editable composer; Copy/Insert are user-initiated, never Send. Label confidential dealer vs public-only facts with text, not only colour. Provide paste + Copy layout when live Meta insertion is not authorised/reliable.


## 12. v0.2.0 admin and panel components

- **Admin shell** (`Admin\Ui::header`): dark brand bar (`--iaa-secondary`) with logo, version, business name, "AI: primary → fallback" pill and service status pill; white section navigation with an underlined active item; title row with subtitle and primary action. All plugin pages use it.
- **Panels/cards**: white surface, `--iaa-radius-lg`, `--iaa-shadow-sm`; KPI stat cards; responsive grids (auto-fit, min 280 px).
- **Setup checklist** (Overview): progress bar + six steps (AI provider, test, answers, products, dealers, extension token) with done state and a call-to-action button.
- **Empty states**: icon, title, one-sentence explanation, primary button to the next step.
- **Settings tabs**: one form, panes shown by `admin.js` (no data lost on save); provider cards (primary/fallback highlighted, free-tier badge), warning-coloured free-tier consent box, sticky save bar.
- **Panel**: numbered step lists (`.iaa-steps`) on connect and ready screens; meta line under suggestions with the AI used.
- Accessibility: every control labelled (axe-core WCAG 2.1 A/AA: 0 serious/critical on 6 panel views and 12 admin views).
