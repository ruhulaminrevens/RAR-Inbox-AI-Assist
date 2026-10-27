<?php
/**
 * A-07 Settings. One form (so unticked checkboxes on hidden tabs are never lost), shown as tabs by
 * assets/admin.js; without JavaScript every section is visible.
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const TABS = array(
		'ai'      => 'AI providers',
		'limits'  => 'Limits & budget',
		'replies' => 'Replies',
		'access'  => 'Access & privacy',
		'advanced' => 'Advanced',
	);

	/** Short provider notes shown on each card (facts the admin needs; verify on the provider site). */
	const NOTES = array(
		'anthropic' => 'Paid API (pay per token). Recommended for best Bangla quality. Keys: console.anthropic.com.',
		'openai'    => 'Paid API (pay per token). Enter the model ID you want to use.',
		'gemini'    => 'Has a free tier with rate limits (Google AI Studio key). On the free tier Google may use and human-review what is sent; only masked text is sent. Paid tier if billing is enabled on the key\'s project.',
		'compat'    => 'Any OpenAI-compatible API: Groq (https://api.groq.com/openai/v1), OpenRouter (https://openrouter.ai/api/v1) and others. Some offer free models with limits; check their data-use terms.',
	);

	public static function render() {
		$tab = Ui::get( 'tab', 'ai' );
		$tab = isset( self::TABS[ $tab ] ) ? $tab : 'ai';
		Ui::header( 'Settings', '', 'Changes apply to the next suggestion. Every change is recorded in the audit log.' );
		$s   = Settings::all();
		$val = static function ( $k ) use ( $s ) {
			return Notice::has_old() ? Notice::old( $k, $s[ $k ] ) : $s[ $k ];
		};
		$err = static function ( $k ) {
			$e = Notice::field_error( $k );
			if ( $e ) {
				echo '<p class="iaa-field-error">' . esc_html( $e ) . '</p>';
			}
		};
		$num = static function ( $k, $label, $step = '1', $help = '' ) use ( $val, $err ) {
			echo '<tr><th><label for="iaa-f-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" step="' . esc_attr( $step ) . '" id="iaa-f-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $val( $k ) ) . '" class="small-text">' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' );
			$err( $k );
			echo '</td></tr>';
		};
		$chk = static function ( $k, $label, $help = '' ) use ( $val ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $val( $k ), 1, false ) . '> ' . esc_html( $help ) . '</label></td></tr>';
		};

		echo '<nav class="iaa-tabs" data-iaa-tabs aria-label="Settings sections">';
		foreach ( self::TABS as $key => $label ) {
			echo '<a class="iaa-tabs__link' . ( $key === $tab ? ' is-active' : '' ) . '" href="' . esc_url( Ui::url( 'rar-iaa-settings', array( 'tab' => $key ) ) ) . '" data-iaa-tab="' . esc_attr( $key ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		Ui::form_open( 'rar_iaa_settings_save', false, 'iaa-settings' );
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" data-iaa-tab-field>';

		/* ---------------- AI providers */
		echo '<div class="iaa-tabpane" data-iaa-pane="ai"' . ( 'ai' === $tab ? ' data-active' : '' ) . '>';
		echo '<div class="iaa-panel"><div class="iaa-panel__head"><h2>Which AI answers</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="iaa-f-provider">Primary</label></th><td><select id="iaa-f-provider" name="provider">';
		foreach ( Settings::PROVIDERS as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $val( 'provider' ), $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">Tried first for every suggestion.</p></td></tr>';
		echo '<tr><th><label for="iaa-f-fallback_provider">Fallback</label></th><td><select id="iaa-f-fallback_provider" name="fallback_provider"><option value="">None</option>';
		foreach ( Settings::PROVIDERS as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $val( 'fallback_provider' ), $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">Used automatically when the primary fails (free-tier limit reached, outage, timeout, unusable answer). Typical: <strong>Gemini (free) → Anthropic (paid)</strong> — free as much as possible, paid only when needed.</p>';
		$err( 'fallback_provider' );
		echo '</td></tr></tbody></table></div></div>';

		echo '<div class="iaa-providers">';
		$prices = (array) $val( 'prices' );
		$free   = (array) $val( 'free_providers' );
		$tests  = (array) get_option( 'rar_iaa_provider_tests', array() );
		foreach ( Settings::PROVIDERS as $p => $label ) {
			$src      = Settings::key_source( $p );
			list( $pin, $pout ) = isset( $prices[ $p ] ) ? array( $prices[ $p ]['in'], $prices[ $p ]['out'] ) : Settings::provider_price( $p );
			$role     = $p === $val( 'provider' ) ? 'Primary' : ( $p === $val( 'fallback_provider' ) ? 'Fallback' : '' );
			echo '<div class="iaa-card iaa-provider' . ( $role ? ' is-used' : '' ) . '"><div class="iaa-provider__head"><h3>' . esc_html( $label ) . '</h3>' . ( $role ? Ui::badge( 'active', $role ) : '' ) . ( Settings::is_free( $p ) ? ' ' . Ui::badge( 'free', 'free tier' ) : '' ) . '</div>'; // phpcs:ignore
			echo '<p class="description">' . esc_html( self::NOTES[ $p ] ) . '</p><table class="form-table iaa-compact" role="presentation"><tbody>';
			echo '<tr><th><label for="iaa-f-key_' . esc_attr( $p ) . '">API key</label></th><td>';
			if ( 'constant' === $src ) {
				echo '<p>Set in wp-config.php (<code>RAR_IAA_' . esc_html( strtoupper( $p ) ) . '_API_KEY</code>): <code>' . esc_html( Settings::masked_key( $p ) ) . '</code></p>';
			} else {
				echo '<input type="password" autocomplete="new-password" id="iaa-f-key_' . esc_attr( $p ) . '" name="key_' . esc_attr( $p ) . '" class="regular-text" placeholder="' . esc_attr( $src ? 'Saved: ' . Settings::masked_key( $p ) . ' — type to replace' : 'Not set' ) . '">';
				if ( $src ) {
					echo ' <label><input type="checkbox" name="clear_key_' . esc_attr( $p ) . '" value="1"> Remove saved key</label>';
				}
				echo '<p class="description">Recommended: <code>define( \'RAR_IAA_' . esc_html( strtoupper( $p ) ) . '_API_KEY\', \'…\' );</code> in wp-config.php. A key typed here is stored encrypted.</p>';
			}
			echo '</td></tr>';
			if ( 'compat' === $p ) {
				echo '<tr><th><label for="iaa-f-compat_base_url">API base URL</label></th><td><input type="url" id="iaa-f-compat_base_url" name="compat_base_url" class="regular-text" list="iaa-compat-bases" value="' . esc_attr( $val( 'compat_base_url' ) ) . '" placeholder="https://api.groq.com/openai/v1"><datalist id="iaa-compat-bases"><option value="https://api.groq.com/openai/v1">Groq</option><option value="https://openrouter.ai/api/v1">OpenRouter</option></datalist>';
				$err( 'compat_base_url' );
				echo '</td></tr>';
			}
			$mk = $p . '_model';
			echo '<tr><th><label for="iaa-f-' . esc_attr( $mk ) . '">Model</label></th><td><input type="text" id="iaa-f-' . esc_attr( $mk ) . '" name="' . esc_attr( $mk ) . '" value="' . esc_attr( $val( $mk ) ) . '" class="regular-text" placeholder="model id">';
			$err( $mk );
			echo '</td></tr>';
			echo '<tr><th scope="row">Price (USD / 1M tokens)</th><td><label>Input <input type="number" step="0.0001" min="0" class="small-text" name="prices[' . esc_attr( $p ) . '][in]" value="' . esc_attr( $pin ) . '"></label> <label>Output <input type="number" step="0.0001" min="0" class="small-text" name="prices[' . esc_attr( $p ) . '][out]" value="' . esc_attr( $pout ) . '"></label><p class="description">From the provider\'s pricing page. Needed for the monthly budget unless this is a free tier.</p>';
			$err( 'prices_' . $p . '_in' );
			$err( 'prices_' . $p . '_out' );
			echo '</td></tr>';
			if ( in_array( $p, Settings::FREE_CAPABLE, true ) ) {
				echo '<tr><th scope="row">Free tier</th><td><label><input type="checkbox" name="free_providers[]" value="' . esc_attr( $p ) . '"' . checked( in_array( $p, $free, true ), true, false ) . '> This key is on a free tier (no token cost; the daily limit still applies)</label></td></tr>';
			}
			$t = isset( $tests[ $p ] ) ? $tests[ $p ] : null;
			echo '<tr><th scope="row">Last test</th><td>' . ( $t ? Ui::badge( 0 === strpos( $t['result'], 'OK' ) ? 'ok' : 'error', 0 === strpos( $t['result'], 'OK' ) ? 'OK' : 'failed' ) . ' <span class="description">' . esc_html( $t['result'] . ' · ' . \RarIaa\Db::display( $t['at'] ) ) . '</span>' : '<span class="description">Not tested — save, then use "Test" below.</span>' ) . '</td></tr>'; // phpcs:ignore
			echo '</tbody></table></div>';
		}
		echo '</div>';

		echo '<div class="iaa-panel iaa-consent"><div class="iaa-panel__body"><h2>Free-tier data use</h2><p>Free tiers (for example the Gemini API free tier) may let the provider use and human-review submitted text to improve their products, and their terms ask not to send personal information. Inbox AI Assist always masks phone numbers, e-mails, addresses, long account numbers and the customer\'s display name before sending; the business conversation itself is still sent. Use a paid tier for anything confidential.</p>';
		echo '<label><input type="checkbox" name="free_tier_consent" value="1"' . checked( (int) $val( 'free_tier_consent' ), 1, false ) . '> I understand and allow masked conversations to be sent to providers marked "free tier".</label>';
		$err( 'free_tier_consent' );
		echo '</div></div>';

		echo '<div class="iaa-panel"><div class="iaa-panel__head"><h2>Output</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		$num( 'max_output_tokens', 'Max output tokens per call', '1', '300–4000. Three Bangla suggestions need about 1,200–1,600.' );
		echo '<tr><th><label for="iaa-f-suggestion_count">Suggestions per click</label></th><td><select id="iaa-f-suggestion_count" name="suggestion_count"><option value="3"' . selected( (int) $val( 'suggestion_count' ), 3, false ) . '>3 (Professional, Friendly, Short)</option><option value="4"' . selected( (int) $val( 'suggestion_count' ), 4, false ) . '>4 (+ Sales-focused)</option></select></td></tr>';
		echo '</tbody></table></div></div></div>';

		/* ---------------- Limits */
		echo '<div class="iaa-tabpane" data-iaa-pane="limits"' . ( 'limits' === $tab ? ' data-active' : '' ) . '><div class="iaa-panel"><div class="iaa-panel__head"><h2>Limits &amp; budget</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		$num( 'per_minute', 'Requests per person per minute' );
		$num( 'daily_limit', 'AI calls per day (site)', '1', 'Counts every provider call, including fallback calls.' );
		$num( 'monthly_cap_usd', 'Monthly AI budget (USD)', '0.01', 'Hard stop for paid providers: worst-case cost is reserved before each call. Free-tier calls cost $0.' );
		$num( 'max_concurrent', 'Simultaneous AI calls', '1', 'Keep 1 on shared hosting until the load test passes.' );
		echo '</tbody></table></div></div></div>';

		/* ---------------- Replies */
		echo '<div class="iaa-tabpane" data-iaa-pane="replies"' . ( 'replies' === $tab ? ' data-active' : '' ) . '><div class="iaa-panel"><div class="iaa-panel__head"><h2>Replies</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		$chk( 'ai_enabled', 'AI suggestions', 'On (when off, staff can still use Quick answers)' );
		$site_title = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		echo '<tr><th><label for="iaa-f-business_name">Business name</label></th><td><input type="text" id="iaa-f-business_name" name="business_name" class="regular-text" value="' . esc_attr( $val( 'business_name' ) ) . '" placeholder="' . esc_attr( $site_title ) . '" aria-describedby="iaa-d-business_name">';
		echo '<p class="description" id="iaa-d-business_name">' . esc_html( 'The name the AI uses for your business in replies. Leave empty to use the site title' . ( '' !== $site_title ? ' ("' . $site_title . '")' : '' ) . '.' ) . '</p>';
		$err( 'business_name' );
		echo '</td></tr>';
		echo '<tr><th scope="row">Banglish messages</th><td><fieldset><legend class="screen-reader-text">Banglish messages</legend><label><input type="radio" name="banglish_reply_script" value="bn"' . checked( $val( 'banglish_reply_script' ), 'bn', false ) . '> Reply in Bangla script</label> <label><input type="radio" name="banglish_reply_script" value="latin"' . checked( $val( 'banglish_reply_script' ), 'latin', false ) . '> Reply in Banglish</label></fieldset></td></tr>';
		echo '<tr><th scope="row">Digits in Bangla replies</th><td><fieldset><legend class="screen-reader-text">Digits in Bangla replies</legend><label><input type="radio" name="reply_digits" value="en"' . checked( $val( 'reply_digits' ), 'en', false ) . '> English digits (0-9)</label> <label><input type="radio" name="reply_digits" value="bn"' . checked( $val( 'reply_digits' ), 'bn', false ) . '> Bangla digits (০-৯)</label></fieldset></td></tr>';
		$chk( 'allow_public_mrp', 'Public prices', 'Customers who are not linked dealers may be told the website price (WooCommerce) or MRP of listed products. Recommended for shops that sell to the public.' );
		$chk( 'exact_stock', 'Exact stock quantity', 'Tell linked dealers the exact WooCommerce quantity (default: in stock / limited / out of stock only)' );
		echo '<tr><th><label for="iaa-f-holding_reply_bn">Holding reply (Bangla)</label></th><td><textarea id="iaa-f-holding_reply_bn" name="holding_reply_bn" class="large-text" rows="2">' . esc_textarea( $val( 'holding_reply_bn' ) ) . '</textarea><p class="description">Used instead of a suggestion that contained an unapproved price.</p>';
		$err( 'holding_reply_bn' );
		echo '</td></tr><tr><th><label for="iaa-f-holding_reply_en">Holding reply (English)</label></th><td><textarea id="iaa-f-holding_reply_en" name="holding_reply_en" class="large-text" rows="2">' . esc_textarea( $val( 'holding_reply_en' ) ) . '</textarea>';
		$err( 'holding_reply_en' );
		echo '</td></tr><tr><th><label for="iaa-f-banned_phrases">Warning phrases</label></th><td><textarea id="iaa-f-banned_phrases" name="banned_phrases" class="large-text" rows="4">' . esc_textarea( $val( 'banned_phrases' ) ) . '</textarea><p class="description">One per line. A suggestion that contains one gets a warning unless an approved fact contains it.</p></td></tr>';
		echo '</tbody></table></div></div></div>';

		/* ---------------- Access & privacy */
		echo '<div class="iaa-tabpane" data-iaa-pane="access"' . ( 'access' === $tab ? ' data-active' : '' ) . '><div class="iaa-panel"><div class="iaa-panel__head"><h2>Inbox page access</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		$chk( 'live_inbox', 'Live inbox read & insert', 'When staff click, the extension reads the open conversation and fills Meta\'s reply box (it never sends). When OFF, staff select or paste the customer text and use Copy. Check that this fits your use of Meta Business Suite before turning it on.' );
		echo '</tbody></table></div></div>';
		echo '<div class="iaa-panel"><div class="iaa-panel__head"><h2>Privacy</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		$chk( 'masked_logging', 'Masked text in logs', 'Store masked conversation and suggestions in the log for quality review (default off)' );
		$num( 'retention_days', 'Keep suggestion logs (days)', '1', '7–90' );
		echo '</tbody></table></div></div>';
		echo '<div class="iaa-panel"><div class="iaa-panel__head"><h2>Roles</h2></div><div class="iaa-panel__body"><p class="description">Administrators and Shop managers are set automatically. Choose other roles (for example your staff-app role) that may use the extension.</p><table class="form-table" role="presentation"><tbody>';
		$roles = wp_roles()->get_names();
		foreach ( array( 'extra_use_roles' => 'May get suggestions', 'extra_link_roles' => 'May link conversations to dealers' ) as $k => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';
			foreach ( $roles as $slug => $name ) {
				if ( in_array( $slug, array( 'administrator', 'shop_manager' ), true ) ) {
					continue;
				}
				echo '<label class="iaa-inline"><input type="checkbox" name="' . esc_attr( $k ) . '[]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, (array) $val( $k ), true ), true, false ) . '> ' . esc_html( translate_user_role( $name ) ) . '</label> ';
			}
			echo '</fieldset></td></tr>';
		}
		echo '</tbody></table></div></div></div>';

		/* ---------------- Advanced */
		echo '<div class="iaa-tabpane" data-iaa-pane="advanced"' . ( 'advanced' === $tab ? ' data-active' : '' ) . '><div class="iaa-panel"><div class="iaa-panel__head"><h2>Uninstall</h2></div><div class="iaa-panel__body"><table class="form-table" role="presentation"><tbody>';
		$chk( 'delete_data_on_uninstall', 'Delete all data on uninstall', 'Knowledge base, dealers, price lists, logs and settings are deleted when the plugin is deleted' );
		echo '</tbody></table></div></div></div>';

		echo '<div class="iaa-savebar"><button type="submit" class="button button-primary button-large">Save settings</button></div></form>';

		Ui::panel(
			'Test connection',
			static function () {
				echo '<p class="description">Sends a tiny request ("Reply with OK") with the saved settings of each provider. Save first.</p><div class="iaa-actions-row">';
				foreach ( Settings::PROVIDERS as $p => $label ) {
					Ui::button_form( 'rar_iaa_provider_test', array( 'provider' => $p ), 'Test ' . preg_replace( '/\s*\(.*\)$/', '', $label ), 'button' );
				}
				echo '</div>';
			},
			'',
			'iaa-test'
		);
		Ui::footer();
	}
}
