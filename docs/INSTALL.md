# Installation guide — RAR Inbox AI Assist 1.0.0

For any business in Bangladesh that answers customers and dealers in **Meta Business Suite** (Facebook/Instagram inbox) and runs a **WordPress** site, with or without WooCommerce. Nothing here sends a Messenger message: staff always press Send in Meta themselves.

Before using it on a live store, work through [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md).

## 1. What you need

| Item | Requirement | Where to check |
|---|---|---|
| WordPress | 6.4 or newer (tested on 7.1.3) | Dashboard → Updates |
| PHP | 7.4 – 8.4 (tested on 8.3; CI on 7.4, 8.3, 8.4); `sodium` extension only if API keys are saved in Settings instead of wp-config | Tools → Site Health → Info → Server |
| Database | MariaDB 10.6+ / MySQL 8 (tested on MariaDB 10.11) | Site Health → Database |
| WooCommerce | Optional, 8.0+ (tested on 11.1.2, HPOS on and off). Without it, prices come from CSV lists | Plugins |
| HTTPS | Required (tokens travel in request headers) | browser padlock |
| AI provider | At least one. **Free:** Google Gemini (aistudio.google.com) and/or Groq (console.groq.com). **Paid:** Anthropic (best Bangla), OpenAI, or any OpenAI-compatible service | provider websites |
| Staff PCs | Google Chrome 116 or newer (desktop) | chrome://settings/help |
| Staff accounts | One WordPress account per person (Administrator and Shop manager are mapped automatically; other roles can be allowed in Settings) | Users |

## 2. Backup (mandatory on a live site)

Take a files + database backup in your hosting panel (Hostinger: hPanel → Websites → your site → **Backups**) and note its date.

## 3. Install or update the WordPress plugin

1. wp-admin → Plugins → Add New → **Upload Plugin** → choose `rar-inbox-ai-assist-1.0.0.zip` → Install → **Activate**.
   *Updating from 0.x:* choose **Replace current with uploaded**. Data and settings are kept; the database upgrades itself.
2. The Plugins screen now shows **Dashboard | Settings** next to Deactivate.
3. **Settings → Replies:** the *Business name* is taken from your site title unless you type one. *Public prices* is already ON when WooCommerce is active (customers may hear the website price); turn it off if your shop prices are not public.
4. **LiteSpeed Cache** (if active): LiteSpeed Cache → Cache → Excludes → *Do Not Cache URIs* → add `/wp-json/rar-iaa/` → Save → Purge All.

Emergency stop at any time: Overview → **Stop service (kill switch)**, or `define( 'RAR_IAA_DISABLE', true );` in wp-config.php.

## 4. Connect AI (free first, paid only when needed)

**Free setup (no credit card):**

1. **Gemini key:** aistudio.google.com → *Get API key* → *Create API key*. Do not enable billing on that Google project if you want to stay free.
2. **Groq key (backup):** console.groq.com → *API Keys* → *Create API Key*.
3. **Inbox AI Assist → Settings → AI providers:**
   - *Google Gemini* card: paste the key, model `gemini-3.1-flash-lite` (or a newer Flash-Lite id from AI Studio), tick **Free tier**.
   - *OpenAI-compatible* card: base URL `https://api.groq.com/openai/v1`, model `openai/gpt-oss-120b`, paste the key, tick **Free tier**.
   - Primary **Google Gemini**, Fallback **OpenAI-compatible**. If Gemini is slow, the plugin moves to the fallback after 12 seconds.
   - Read the yellow **Free-tier data use** box and tick the consent (free tiers may let the provider use and review the masked text).
   - **Save settings**, then **Test Google Gemini** and **Test OpenAI-compatible** — both must say OK.

**Paid backup (best Bangla):** Anthropic key from console.anthropic.com, model `claude-haiku-5-5`, enter its input/output prices (USD per million tokens) from the pricing page. A paid provider without prices is not used, because the monthly budget (default USD 10) depends on them.

Keys can also live in `wp-config.php` (above `/* That's all, stop editing! */`), which keeps them out of the database:

```php
define( 'RAR_IAA_GEMINI_API_KEY', 'paste-your-key' );
define( 'RAR_IAA_COMPAT_API_KEY', 'paste-your-key' );   // Groq / OpenRouter
define( 'RAR_IAA_ANTHROPIC_API_KEY', 'paste-your-key' );
define( 'RAR_IAA_OPENAI_API_KEY', 'paste-your-key' );
```

Never paste a key into chat, e-mail or Git. Free tiers have daily/minute limits; the plugin's own limits (Settings → Limits & budget) apply as well.

## 5. Products and prices

**With WooCommerce:** Products & prices → *WooCommerce link* → **Sync now**. Every published product is linked (variations separately; hidden products and products marked "exclude" are skipped) and the public **WEB — Website price** tier is created. Price edits in WooCommerce are picked up automatically. Improve matching in each product's *Product data → General → Inbox AI: other names* with the names customers write (for example `জাগুয়ার | jaguar red`).

**Dealer prices (optional):** Dealers & tiers → create tiers (A, B …) and dealers. For each tier set a price rule (for example *WooCommerce current price −8 %, round to 5 ৳*) or upload a CSV list. Dealer prices are used only in a conversation a staff member has linked to that dealer.

CSV columns (UTF-8, Bangla digits and ৳ accepted, max 5,000 rows / 2 MB):

```csv
item_code,product_name,unit,price_bdt,aliases,pack_size,mrp_bdt,wc_product_id,stock_mode,manual_stock_status
SKU-001,Example Product 100ml,pcs,1250,example|উদাহরণ,,1400,,manual,in_stock
```

## 6. Approved answers

**Knowledge base** → add office hours, contact numbers, delivery, payment and return policies in Bangla and English → a second administrator approves (a sole administrator may approve their own). The AI only states facts written here or in your price lists.

## 7. Chrome extension (each staff PC)

1. Unzip `rar-inbox-ai-assist-extension-1.0.0.zip` into a permanent folder. Do not delete the folder afterwards.
2. Chrome → `chrome://extensions` → **Developer mode** → **Load unpacked** → select that folder → pin "Inbox AI Assist".
3. The staff member logs into **your** WordPress site with their own account → Inbox AI Assist → **Access tokens** → label (for example "Office PC 2") → **Create token** → copy it (shown once).
4. Open `business.facebook.com/latest/inbox` → click the extension icon → side panel → enter **your site address** (shown on the Access tokens page) and the token → **সংযোগ করুন** → Chrome asks once to allow the extension on your site → **Allow**. `www.` or not, the panel finds the right address; the token is never sent through a redirect.
5. Updating: unzip the new version over the same folder and click ↻ on `chrome://extensions`. If the panel then asks for **অনুমতি দিন**, click it once.

## 8. Daily use

- **Default (safe mode):** select the customer's messages with the mouse → **নির্বাচিত লেখা থেকে সাজেশন** (or Alt+Shift+D) → pick one of three suggestions → edit if needed → **কপি** → paste into Meta's reply box → read → press Send yourself.
- **Photo of a product:** type the product name in **পণ্য / নোট** before asking for suggestions (for example `Jaguar Classic Red 100ml`).
- **Dealer prices:** click **ডিলার লিংক** and choose the dealer for this conversation first.
- **Live inbox read & insert** (Settings → Access & privacy): when ON, "কথোপকথন থেকে সাজেশন" reads the open conversation and **বসান** fills the reply box — only when staff click, never sends. Check that this fits your use of Meta Business Suite before turning it on, and watch the first conversations.

## 9. Updating, rollback, removal

- Update: upload the new ZIP with "Replace current with uploaded"; the schema migrates automatically.
- Rollback: deactivate the plugin (data kept) and restore the backup from step 2 if needed.
- Removal: deleting the plugin keeps all data unless Settings → Advanced → "Delete all data on uninstall" is ticked.
- Lost PC: revoke its token on Access tokens immediately.
- Daily maintenance runs on WP-Cron. If the Overview shows it as overdue, add a server cron job (Hostinger: hPanel → Advanced → Cron Jobs, every 15 minutes): `wget -q -O - "https://your-site/wp-cron.php?doing_wp_cron" >/dev/null 2>&1`.
