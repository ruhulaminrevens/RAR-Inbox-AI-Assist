# Installation guide — RAR Inbox AI Assist 0.1.0

Pre-pilot build. Install on a staging copy first if Hostinger offers one; on nabiad.com only after a backup (T5.02). Nothing here sends a Messenger message: staff always press Send in Meta themselves.

## 1. What you need

| Item | Requirement | Where to check |
|---|---|---|
| WordPress | 6.4 or newer (tested on 7.1.3) | Dashboard → Updates |
| PHP | 7.4 – 8.4 (tested on 8.3); `sodium` extension only if the API key is saved in Settings instead of wp-config | Tools → Site Health → Info → Server |
| Database | MariaDB 10.6+ / MySQL 8 (tested on MariaDB 10.11) | Site Health → Database |
| WooCommerce | Optional, 8.0+ (tested on 11.1.2, HPOS on and off); only needed for live stock buckets | Plugins |
| HTTPS | Required in production (tokens travel in headers) | browser padlock |
| AI provider | Anthropic API key with billing (default model `claude-haiku-5-5`), or OpenAI key + model name | console.anthropic.com / platform.openai.com |
| Token prices | USD per million input and output tokens for the chosen model, from the provider's pricing page. **Drafts are refused until both are entered** (the budget cap depends on them) | provider pricing page |
| Staff PCs | Google Chrome 116 or newer on Windows/macOS (desktop) | chrome://settings/help |
| Staff accounts | One WordPress account per person (Shop manager is mapped automatically) | Users |

## 2. Backup (mandatory)

hPanel → Websites → nabiad.com → **Backups**: create a files + database backup and note its date. Keep the downloaded ZIP until the pilot ends.

## 3. Install the WordPress plugin

1. wp-admin → Plugins → Add New → **Upload Plugin** → choose `rar-inbox-ai-assist-0.1.0.zip` → Install → **Activate**.
2. Put the API key in `wp-config.php` (hPanel → File Manager → `public_html/wp-config.php`), above the line `/* That's all, stop editing! */`:

   ```php
   define( 'RAR_IAA_ANTHROPIC_API_KEY', 'paste-your-key-here' );
   ```

   (OpenAI: `RAR_IAA_OPENAI_API_KEY`.) Never paste the key into chat, e-mail or Git.
3. **Inbox AI Assist → Settings**: enter the input/output token prices, check the monthly budget (USD 10 default), business name, holding replies and warning phrases → **Save settings** → **Test connection** (must say OK).
4. **LiteSpeed Cache** (if active): LiteSpeed Cache → Cache → Excludes → *Do Not Cache URIs* → add `/wp-json/rar-iaa/` → Save → Purge All. The plugin also sends `no-store` headers.
5. **Inbox AI Assist → Overview**: every health check should be ✔ except "Daily maintenance" (turns ✔ after the first WP-Cron run) and "Live inbox" (OFF is correct).

Emergency stop at any time: Overview → **Stop service (kill switch)**, or add `define( 'RAR_IAA_DISABLE', true );` to wp-config.

## 4. Load facts (nothing is answered without them)

1. **Knowledge base** → add entries (office hours, contact, ordering, delivery, payment, policies) in Bangla and English → Submit → a second admin approves (a sole administrator may approve their own).
2. **Dealers & tiers** → create tiers (e.g. A, B) and dealers (code, name, tier).
3. **Price lists** → Import CSV for each tier → review → **Activate**. Columns:

   ```csv
   item_code,product_name,unit,price_bdt,aliases,pack_size,mrp_bdt,wc_product_id,stock_mode,manual_stock_status
   ATZ-JMB,A TO Z Jumbo,carton,1250,জাম্বো|jumbo,12 x 10,1400,,manual,in_stock
   ```

   Required: `item_code, product_name, unit, price_bdt`. `stock_mode`: `wc` (needs `wc_product_id`), `manual` (`in_stock`/`limited`/`out_of_stock`) or `hidden`. UTF-8, Bangla digits and ৳ accepted, max 5,000 rows / 2 MB.

Dealer prices are only ever used for a conversation that a staff member has linked to an active dealer.

## 5. Install the Chrome extension (each staff PC)

1. Unzip `rar-inbox-ai-assist-extension-0.1.0.zip` into a permanent folder (e.g. `C:\NabiadTools\inbox-ai-assist`). Do not delete the folder afterwards.
2. Chrome → `chrome://extensions` → turn on **Developer mode** → **Load unpacked** → select that folder. Pin "Inbox AI Assist".
3. The staff member logs into nabiad.com wp-admin with **their own** account → Inbox AI Assist → **Access tokens** → label (e.g. "Office PC 2") → **Create token** → copy it (shown once).
4. Open `business.facebook.com/latest/inbox` → click the extension icon → side panel → paste the token → **সংযোগ করুন**. If the site's address is `https://www.nabiad.com` (Settings → General → Site Address), enter it under *উন্নত (Advanced) → সাইট* first; a redirect between `nabiad.com` and `www` breaks requests.

## 6. Daily use (default safe mode)

Select the customer's message with the mouse → **নির্বাচিত লেখা থেকে সাজেশন** (or Alt+Shift+D) → pick one of three suggestions → edit if needed → **কপি** → paste into Meta's reply box → read → press Send yourself. For dealer prices, first click **ডিলার লিংক** and choose the dealer for this conversation.

"Live inbox read & insert" (reads the open conversation and fills the reply box) stays **OFF** until Meta permission evidence is recorded (T0.09) and the live adapter is checked on real conversations (T4.09).

## 7. Updating, rollback, removal

- Update: deactivate is not required — upload the new ZIP and choose "Replace current with uploaded"; the schema migrates automatically. Extension: replace the folder's files and click ↻ on chrome://extensions.
- Rollback: deactivate the plugin (data kept) and restore the backup from step 2 if needed.
- Removal: deleting the plugin keeps all data unless Settings → Uninstall → "Delete all data on uninstall" is ticked.
- Lost PC: revoke its token on Access tokens immediately.
