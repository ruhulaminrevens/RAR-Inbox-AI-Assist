=== RAR Inbox AI Assist ===
Contributors: ruhulaminrevens
Tags: messenger, ai, woocommerce, customer support, bangla
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fact-checked AI reply suggestions for Meta Business Suite inbox staff in Bangladesh. Uses approved answers and WooCommerce prices. Never sends messages.

== Description ==

Staff select (or paste) a customer's message in Meta Business Suite, and the companion Chrome extension shows three reply suggestions (Professional, Friendly, Short) in Bangla, Banglish or English. The suggestions come only from facts the business approved here.

* **Approved answers** (maker-checker): hours, contact, delivery, payment and returns.
* **WooCommerce link**: the public website price for regular customers, plus optional dealer price tiers (WooCommerce price ± %, or CSV). Stock is read live.
* **Fact guard**: every number in every suggestion must match an approved fact. Unapproved prices are blocked, and a price the customer suggests never becomes a quote.
* **Free or paid AI**: Google Gemini and Groq/OpenRouter (free tiers) or Anthropic/OpenAI (paid), with automatic fallback. Budget, daily and per-minute limits.
* **Privacy**: phone numbers, e-mails, addresses and names are masked before any AI call. Conversation text is never stored.
* **Human in control**: staff copy (or, optionally, insert) and press Send themselves. Per-person revocable tokens, audit log and a kill switch.

== Installation ==

1. Upload the plugin ZIP (Plugins → Add New → Upload Plugin) and activate.
2. Inbox AI Assist → Settings → AI providers: add a Gemini and/or Groq key (free) or a paid key, then press Test.
3. Add approved answers. With WooCommerce, press Products & prices → Sync now.
4. Each staff member creates an access token and connects the Chrome extension with your site address.

Full guide: docs/INSTALL.md in the repository.

== Frequently Asked Questions ==

= Does it send messages? =
No. Staff always press Send in Meta Business Suite.

= Can it use a free AI? =
Yes. Google Gemini and Groq both offer free tiers (data-use terms apply; the plugin asks for explicit consent). A paid provider can be the fallback.

= Will customers see dealer prices? =
Only in a conversation a staff member has linked to an active dealer.

== Changelog ==

= 1.0.0 =
* Works for any business: no built-in company name. The business name comes from the site title. The extension connects to any site.
* Public website prices are on by default with WooCommerce. No unit warning for website prices. The AI never asks retail customers for dealer details.
* Polished admin and panel: version-only header, clearer health checks, compact diagnostics, folded how-to.
* Faster product matching on large catalogues.

= 0.2.1 =
* Reply language from recent messages, staff note for product photos, 12-second primary cut-over to the fallback AI.

= 0.2.0 =
* WooCommerce link, free and paid AI providers with fallback, professional admin UI.
