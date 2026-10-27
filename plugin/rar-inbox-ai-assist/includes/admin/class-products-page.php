<?php
/**
 * A-04 Products & prices: WooCommerce link (rules per tier, sync, scope), linked products, price
 * list history and CSV import.
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

use RarIaa\Capabilities;
use RarIaa\Db;
use RarIaa\Domain\DealerRepository;
use RarIaa\Domain\PriceRepository;
use RarIaa\Domain\WcCatalogue;
use RarIaa\Draft\StockService;
use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class ProductsPage {

	const SLUG = 'rar-iaa-prices';

	public static function render() {
		$action = Ui::get( 'action' );
		if ( 'import' === $action ) {
			self::import();
			return;
		}
		if ( 'view' === $action ) {
			self::view( (int) Ui::get( 'id' ) );
			return;
		}
		$wc   = WcCatalogue::available();
		$tabs = $wc ? array( 'link' => 'WooCommerce link', 'products' => 'Linked products', 'lists' => 'Price lists' ) : array( 'lists' => 'Price lists' );
		$tab  = Ui::get( 'tab', $wc ? 'link' : 'lists' );
		$tab  = isset( $tabs[ $tab ] ) ? $tab : key( $tabs );
		Ui::header( 'Products & prices', Ui::button_link( Ui::url( self::SLUG, array( 'action' => 'import' ) ), 'Import CSV' ), 'The AI quotes a price only from these lists: dealer prices for linked dealers, website prices for everyone else (if allowed).' );
		Ui::tabs( self::SLUG, $tabs, $tab );
		if ( 'link' === $tab ) {
			self::link_tab();
		} elseif ( 'products' === $tab ) {
			self::products_tab();
		} else {
			self::lists_tab();
		}
		Ui::footer();
	}

	/* ------------------------------------------------------------------ WooCommerce link */

	private static function link_tab() {
		$state   = WcCatalogue::state();
		$pending = WcCatalogue::pending_since();
		$pub     = DealerRepository::public_tier();
		$plist   = $pub ? PriceRepository::active_for_tier( (int) $pub->id ) : null;
		$can     = current_user_can( Capabilities::ACTIVATE_PRICES );
		echo '<div class="iaa-stats">';
		Ui::stat( 'Linked products', $plist ? (string) (int) $plist->row_count : '0', 'Published WooCommerce products with a price' );
		Ui::stat( 'Last sync', $state && ! empty( $state['at'] ) ? Db::display( $state['at'] ) : 'Never', $state ? ( empty( $state['error'] ) ? 'Reason: ' . $state['reason'] . ( isset( $state['ms'] ) ? ', ' . (int) $state['ms'] . ' ms' : '' ) : $state['error'] ) : 'Run "Sync now" once to link your products' );
		Ui::stat( 'Changes waiting', $pending ? 'Yes' : 'No', $pending ? 'Since ' . wp_date( 'H:i', $pending ) . ( Settings::get( 'wc_auto_sync' ) ? ' — syncs automatically within a few minutes' : ' — auto sync is off' ) : 'Prices are up to date' );
		Ui::stat( 'Auto sync', Settings::get( 'wc_auto_sync' ) ? 'On' : 'Off', 'After product edits, and daily' );
		echo '</div>';
		if ( $can ) {
			echo '<div class="iaa-actions-row">';
			Ui::button_form( 'rar_iaa_wc_sync', array(), 'Sync now', 'button button-primary' );
			echo '<span class="description">Reads all published products (stock is always read live). A new price snapshot is saved only when something changed; old snapshots stay for rollback.</span></div>';
		}
		if ( $state && ! empty( $state['tiers'] ) ) {
			echo '<p class="iaa-sync-result">Last result: ';
			foreach ( $state['tiers'] as $code => $t ) {
				echo '<span class="iaa-inline">' . esc_html( $code ) . ' ' . Ui::badge( $t['status'] ) . ' <span class="description">' . esc_html( (int) $t['items'] . ' items' ) . '</span></span>'; // phpcs:ignore
			}
			if ( ! empty( $state['skipped'] ) ) {
				echo '<span class="description">' . esc_html( (int) $state['skipped'] . ' products skipped (no price).' ) . '</span>';
			}
			echo '</p>';
		}

		Ui::panel(
			'Price rule per tier',
			static function () use ( $can ) {
				$tiers  = DealerRepository::tiers();
				$counts = DealerRepository::count_by_tier();
				echo '<p class="description">Each dealer tier can follow WooCommerce automatically (for example "current price −8%") or use an uploaded CSV list. The public <strong>Website price</strong> tier is created automatically and is what unverified customers may be told, if "Public prices" is on in Settings.</p>';
				if ( ! $tiers ) {
					echo '<p>No tiers yet. Press "Sync now" to create the Website tier, and add dealer tiers in ' . Ui::button_link( Ui::url( 'rar-iaa-dealers', array( 'tab' => 'tiers' ) ), 'Dealers & tiers', 'button-link' ) . '.</p>'; // phpcs:ignore
					return;
				}
				echo '<div class="iaa-table-wrap"><table class="widefat striped iaa-rules"><thead><tr><th scope="col">Tier</th><th scope="col">Dealers</th><th scope="col">Price source</th><th scope="col">Base price</th><th scope="col">Adjust %</th><th scope="col">Round to</th><th scope="col">Active list</th><th scope="col"><span class="screen-reader-text">Save</span></th></tr></thead><tbody>';
				foreach ( $tiers as $t ) {
					$active = PriceRepository::active_for_tier( (int) $t->id );
					$fid    = 'iaa-rule-' . (int) $t->id;
					$lock   = (int) $t->is_public || ! $can;
					echo '<tr><td><strong>' . esc_html( $t->code ) . '</strong><br><span class="description">' . esc_html( $t->name ) . '</span>' . ( 'active' !== $t->status ? ' ' . Ui::badge( 'inactive' ) : '' ) . '</td>'; // phpcs:ignore
					echo '<td>' . esc_html( (int) $t->is_public ? 'everyone' : ( isset( $counts[ (int) $t->id ] ) ? $counts[ (int) $t->id ] : 0 ) ) . '</td>';
					echo '<td><select name="price_source" form="' . esc_attr( $fid ) . '" aria-label="Price source for ' . esc_attr( $t->code ) . '"' . disabled( $lock, true, false ) . '><option value="woocommerce"' . selected( $t->price_source, 'woocommerce', false ) . '>WooCommerce (auto)</option><option value="csv"' . selected( $t->price_source, 'csv', false ) . '>CSV upload</option></select></td>';
					echo '<td><select name="wc_price_basis" form="' . esc_attr( $fid ) . '" aria-label="Base price for ' . esc_attr( $t->code ) . '"' . disabled( $lock, true, false ) . '><option value="current"' . selected( $t->wc_price_basis, 'current', false ) . '>Current (sale if on sale)</option><option value="regular"' . selected( $t->wc_price_basis, 'regular', false ) . '>Regular price</option></select></td>';
					echo '<td><input type="number" step="0.01" min="-90" max="200" class="small-text" name="wc_adjust_pct" form="' . esc_attr( $fid ) . '" value="' . esc_attr( rtrim( rtrim( (string) $t->wc_adjust_pct, '0' ), '.' ) ) . '" aria-label="Adjustment percent for ' . esc_attr( $t->code ) . '"' . disabled( $lock, true, false ) . '></td>';
					echo '<td><select name="wc_rounding" form="' . esc_attr( $fid ) . '" aria-label="Rounding for ' . esc_attr( $t->code ) . '"' . disabled( $lock, true, false ) . '>';
					foreach ( array( 0 => 'No rounding', 1 => '1 ৳', 5 => '5 ৳', 10 => '10 ৳' ) as $v => $l ) {
						echo '<option value="' . esc_attr( $v ) . '"' . selected( (int) $t->wc_rounding, $v, false ) . '>' . esc_html( $l ) . '</option>';
					}
					echo '</select></td><td>' . ( $active ? '<a href="' . esc_url( Ui::url( ProductsPage::SLUG, array( 'action' => 'view', 'id' => $active->id ) ) ) . '">' . esc_html( (int) $active->row_count . ' items' ) . '</a> <span class="description">' . esc_html( 'woocommerce' === $active->source ? 'synced' : 'CSV' ) . '</span>' : '<span class="description">none</span>' ) . '</td><td>';
					if ( ! $lock ) {
						// The row's inputs belong to this form through their HTML form="" attribute.
						Ui::form_open( 'rar_iaa_tier_rule_save', false, 'iaa-inline-form', $fid );
						echo '<input type="hidden" name="id" value="' . esc_attr( $t->id ) . '"><button class="button">Save &amp; sync</button></form>';
					} elseif ( (int) $t->is_public ) {
						echo '<span class="description">Fixed</span>';
					}
					echo '</td></tr>';
				}
				echo '</tbody></table></div>';
				Ui::field_error( 'wc_adjust_pct' );
				Ui::field_error( 'wc_rounding' );
				Ui::field_error( 'price_source' );
			}
		);

		Ui::panel(
			'Scope & defaults',
			static function () use ( $can ) {
				Ui::form_open( 'rar_iaa_wc_settings_save' );
				$sel  = (array) Settings::get( 'wc_categories' );
				$cats = WcCatalogue::categories();
				echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row">Categories</th><td><fieldset><legend class="screen-reader-text">Categories to link</legend><div class="iaa-checklist">';
				foreach ( $cats as $c ) {
					echo '<label><input type="checkbox" name="wc_categories[]" value="' . esc_attr( $c->term_id ) . '"' . checked( in_array( (int) $c->term_id, array_map( 'intval', $sel ), true ), true, false ) . disabled( ! $can, true, false ) . '> ' . esc_html( $c->name ) . ' <span class="description">(' . esc_html( $c->count ) . ')</span></label>';
				}
				echo '</div><p class="description">None ticked = all published products. Hidden products and products marked "Inbox AI: exclude" are never linked.</p></fieldset></td></tr>';
				echo '<tr><th><label for="iaa-f-wc_default_unit">Default unit</label></th><td><input type="text" id="iaa-f-wc_default_unit" name="wc_default_unit" class="small-text" value="' . esc_attr( Settings::get( 'wc_default_unit' ) ) . '"' . disabled( ! $can, true, false ) . '><p class="description">Unit the WooCommerce price is for (pcs, carton …). Override per product in Product data → General → "Inbox AI: unit".</p>';
				Ui::field_error( 'wc_default_unit' );
				echo '</td></tr><tr><th scope="row">Auto sync</th><td><label><input type="checkbox" name="wc_auto_sync" value="1"' . checked( (int) Settings::get( 'wc_auto_sync' ), 1, false ) . disabled( ! $can, true, false ) . '> Sync automatically after product price/name changes and daily</label></td></tr></tbody></table>';
				if ( $can ) {
					echo '<p class="submit"><button class="button button-primary">Save &amp; sync</button></p>';
				}
				echo '</form>';
				echo '<p class="description"><strong>Tip:</strong> add the names customers really write (জাগুয়ার, jaguar blue, ডিসি …) in each product\'s "Inbox AI: other names" field — product matching becomes much better.</p>';
			}
		);
	}

	/* ------------------------------------------------------------------ Linked products */

	private static function products_tab() {
		$pub   = DealerRepository::public_tier();
		$plist = $pub ? PriceRepository::active_for_tier( (int) $pub->id ) : null;
		if ( ! $plist ) {
			Ui::empty_state( 'products', 'No products linked yet', 'Press "Sync now" on the WooCommerce link tab to read your published products and prices.', Ui::url( self::SLUG, array( 'tab' => 'link' ) ), 'Go to WooCommerce link' );
			return;
		}
		$s      = Ui::get( 's' );
		$items  = PriceRepository::items( (int) $plist->id, $s );
		$tiers  = array_values(
			array_filter(
				DealerRepository::tiers( true ),
				static function ( $t ) {
					return ! (int) $t->is_public;
				}
			)
		);
		$prices = PriceRepository::active_items_by_wc_product();
		$page   = max( 1, (int) Ui::get( 'paged', 1 ) );
		$total  = count( $items );
		$items  = array_slice( $items, ( $page - 1 ) * 50, 50 );
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="products"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="Product, code or other name" aria-label="Search linked products"> <button class="button">Search</button> <span class="description">' . esc_html( $total . ' products · snapshot ' . Db::display( $plist->activated_at ) ) . '</span></form>';
		echo '<div class="iaa-table-wrap"><table class="widefat striped iaa-products"><thead><tr><th scope="col">Product</th><th scope="col">Code</th><th scope="col">Unit</th><th scope="col" class="num">Website ৳</th>';
		foreach ( $tiers as $t ) {
			echo '<th scope="col" class="num">' . esc_html( $t->code ) . ' ৳</th>';
		}
		echo '<th scope="col">Stock now</th><th scope="col">Other names</th></tr></thead><tbody>';
		foreach ( $items as $i ) {
			list( $bucket ) = StockService::for_item( $i );
			$edit           = $i->wc_product_id ? get_edit_post_link( wp_get_post_parent_id( (int) $i->wc_product_id ) ? wp_get_post_parent_id( (int) $i->wc_product_id ) : (int) $i->wc_product_id ) : '';
			echo '<tr><td>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $i->product_name ) . '</a>' : esc_html( $i->product_name ) ) . '</td><td><code>' . esc_html( $i->item_code ) . '</code></td><td>' . esc_html( $i->unit . ( $i->pack_size ? ' · ' . $i->pack_size : '' ) ) . '</td><td class="num">' . esc_html( Util::bd_group( $i->price_bdt ) ) . '</td>';
			foreach ( $tiers as $t ) {
				$p = isset( $prices[ (int) $i->wc_product_id ][ (int) $t->id ] ) ? $prices[ (int) $i->wc_product_id ][ (int) $t->id ] : null;
				echo '<td class="num">' . esc_html( null === $p ? ( 'csv' === $t->price_source ? 'CSV' : '—' ) : Util::bd_group( $p ) ) . '</td>';
			}
			echo '<td>' . Ui::badge( 'in_stock' === $bucket ? 'ok' : ( 'limited' === $bucket ? 'warned' : ( 'out_of_stock' === $bucket ? 'error' : 'draft' ) ), str_replace( '_', ' ', $bucket ) ) . '</td><td class="iaa-muted-cell">' . esc_html( str_replace( '|', ', ', $i->aliases ) ) . '</td></tr>'; // phpcs:ignore
		}
		if ( ! $items ) {
			echo '<tr><td colspan="' . esc_attr( 6 + count( $tiers ) ) . '">No products match.</td></tr>';
		}
		echo '</tbody></table></div>';
		Ui::pagination( $total, 50, self::SLUG, array_filter( array( 'tab' => 'products', 's' => $s ) ) );
	}

	/* ------------------------------------------------------------------ Price lists */

	private static function lists_tab() {
		$lists = PriceRepository::lists( array( 'tier_id' => Ui::get( 'tier_id' ), 'status' => Ui::get( 'status' ) ) );
		if ( ! $lists ) {
			Ui::empty_state( 'media-spreadsheet', 'No price lists yet', WcCatalogue::available() ? 'Sync WooCommerce products, or import a CSV for tiers whose prices are not in WooCommerce.' : 'Import a CSV price list for each dealer tier.', Ui::url( self::SLUG, array( 'action' => 'import' ) ), 'Import CSV' );
			return;
		}
		echo '<div class="iaa-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">Name</th><th scope="col">Tier</th><th scope="col">Source</th><th scope="col">Status</th><th scope="col">Effective from</th><th scope="col">Rows</th><th scope="col">Imported</th><th scope="col">Activated</th></tr></thead><tbody>';
		foreach ( $lists as $l ) {
			echo '<tr><td><a href="' . esc_url( Ui::url( self::SLUG, array( 'action' => 'view', 'id' => $l->id ) ) ) . '"><strong>' . esc_html( $l->name ) . '</strong></a></td><td>' . esc_html( $l->tier_code ) . '</td><td>' . esc_html( 'woocommerce' === $l->source ? 'WooCommerce' : 'CSV' ) . '</td><td>' . Ui::badge( $l->status ) . '</td><td>' . esc_html( $l->effective_from ) . '</td><td>' . esc_html( $l->row_count ) . '</td><td>' . esc_html( Ui::user_name( $l->imported_by ) . ', ' . Db::display( $l->imported_at ) ) . '</td><td>' . esc_html( $l->activated_at ? Db::display( $l->activated_at ) : '—' ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table></div>';
	}

	private static function import() {
		Ui::header( 'Import price list (CSV)', Ui::button_link( Ui::url( self::SLUG, array( 'tab' => 'lists' ) ), '← Back' ), 'For tiers whose prices are not in WooCommerce. The list is saved as a draft; activate it after review.' );
		$report = get_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
			self::import_report( $report );
		}
		$tiers = array_filter(
			DealerRepository::tiers( true ),
			static function ( $t ) {
				return ! (int) $t->is_public;
			}
		);
		if ( ! $tiers ) {
			echo '<div class="notice notice-warning inline"><p>Create an active dealer tier first (Dealers &amp; tiers → Tiers).</p></div>';
		}
		echo '<div class="iaa-panel"><div class="iaa-panel__body">';
		Ui::form_open( 'rar_iaa_price_import', true );
		echo '<table class="form-table" role="presentation"><tbody><tr><th><label for="iaa-f-tier">Tier *</label></th><td><select id="iaa-f-tier" name="tier_id" required>';
		foreach ( $tiers as $t ) {
			echo '<option value="' . esc_attr( $t->id ) . '"' . selected( (int) Notice::old( 'tier_id' ), (int) $t->id, false ) . '>' . esc_html( $t->code . ' — ' . $t->name ) . '</option>';
		}
		echo '</select><p class="description">A CSV list takes effect only for tiers whose price source is "CSV upload"; for WooCommerce tiers the next sync replaces it.</p></td></tr><tr><th><label for="iaa-f-lname">List name *</label></th><td><input type="text" class="regular-text" id="iaa-f-lname" name="name" required maxlength="150" value="' . esc_attr( Notice::old( 'name' ) ) . '" placeholder="Dealer list November 2026"></td></tr>';
		echo '<tr><th><label for="iaa-f-eff">Effective from *</label></th><td><input type="date" id="iaa-f-eff" name="effective_from" required value="' . esc_attr( Notice::old( 'effective_from', Db::dhaka_date() ) ) . '"></td></tr>';
		echo '<tr><th><label for="iaa-f-csv">CSV file *</label></th><td><input type="file" id="iaa-f-csv" name="csv" accept=".csv,text/csv" required><p class="description">UTF-8 CSV, max 2 MB, max 5,000 rows. Required columns: <code>item_code, product_name, unit, price_bdt</code>. Optional: <code>aliases</code> (separate with |), <code>pack_size, mrp_bdt, wc_product_id, stock_mode</code> (wc / manual / hidden), <code>manual_stock_status</code> (in_stock / limited / out_of_stock). Bangla digits and ৳ are accepted. In Excel use "Save As → CSV UTF-8".</p></td></tr>';
		echo '</tbody></table><p class="submit"><button class="button button-primary">Validate &amp; save as draft</button></p></form></div></div>';
		Ui::panel(
			'Example',
			static function () {
				echo '<pre class="iaa-pre">item_code,product_name,unit,price_bdt,aliases,pack_size,stock_mode,manual_stock_status' . "\n" . 'ATZ-JMB,A TO Z Jumbo,carton,1250,জাম্বো|jumbo coil,12 x 10,manual,in_stock</pre>';
			}
		);
		Ui::footer();
	}

	private static function import_report( $report ) {
		if ( ! empty( $report['errors'] ) ) {
			echo '<h2>Errors (nothing saved)</h2><table class="widefat striped"><thead><tr><th scope="col">Row</th><th scope="col">Column</th><th scope="col">Problem</th></tr></thead><tbody>';
			foreach ( $report['errors'] as $e ) {
				echo '<tr><td>' . esc_html( $e[0] ) . '</td><td>' . esc_html( $e[1] ) . '</td><td>' . esc_html( $e[2] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( ! empty( $report['warnings'] ) ) {
			echo '<h2>Warnings</h2><table class="widefat striped"><tbody>';
			foreach ( $report['warnings'] as $e ) {
				echo '<tr><td>' . esc_html( $e[0] ) . '</td><td>' . esc_html( $e[1] ) . '</td><td>' . esc_html( $e[2] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	private static function view( $id ) {
		$list = PriceRepository::get_list( $id );
		if ( ! $list ) {
			wp_die( 'Price list not found.' );
		}
		Ui::header( 'Price list: ' . $list->name, Ui::button_link( Ui::url( self::SLUG, array( 'tab' => 'lists' ) ), '← Back' ) );
		$report = get_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
			self::import_report( $report );
		}
		$active = PriceRepository::active_for_tier( (int) $list->tier_id );
		echo '<p>Tier <strong>' . esc_html( $list->tier_code ) . '</strong> · ' . Ui::badge( $list->status ) . ' · ' . esc_html( 'woocommerce' === $list->source ? 'WooCommerce sync' : 'CSV import' ) . ' · ' . esc_html( $list->row_count ) . ' rows · effective ' . esc_html( $list->effective_from ) . '</p>'; // phpcs:ignore
		echo '<div class="iaa-actions-row">';
		if ( 'draft' === $list->status && current_user_can( Capabilities::ACTIVATE_PRICES ) ) {
			$msg = $active ? sprintf( 'Activate "%s"? The current active list "%s" will be archived.', $list->name, $active->name ) : sprintf( 'Activate "%s" for tier %s?', $list->name, $list->tier_code );
			Ui::button_form( 'rar_iaa_price_activate', array( 'id' => $list->id ), 'Activate', 'button button-primary', $msg );
		}
		if ( 'archived' === $list->status && $active && (int) $active->previous_list_id === (int) $list->id && current_user_can( Capabilities::ACTIVATE_PRICES ) ) {
			Ui::button_form( 'rar_iaa_price_reactivate', array( 'id' => $list->id ), 'Re-activate previous', 'button', sprintf( 'Re-activate "%s"? The current list "%s" will be archived.', $list->name, $active->name ) );
		}
		if ( 'draft' === $list->status ) {
			Ui::button_form( 'rar_iaa_price_archive', array( 'id' => $list->id ), 'Archive draft', 'button iaa-button-danger', 'Archive this draft?' );
		}
		echo '</div>';
		$s = Ui::get( 's' );
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="action" value="view"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="Search items" aria-label="Search items"> <button class="button">Search</button></form>';
		echo '<div class="iaa-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">Code</th><th scope="col">Product</th><th scope="col">Other names</th><th scope="col">Unit</th><th scope="col">Pack</th><th scope="col" class="num">Price (৳)</th><th scope="col" class="num">' . esc_html( 'woocommerce' === $list->source ? 'Website (৳)' : 'MRP (৳)' ) . '</th><th scope="col">Stock</th></tr></thead><tbody>';
		foreach ( PriceRepository::items( $id, $s ) as $i ) {
			$stock = 'wc' === $i->stock_mode ? 'WooCommerce #' . $i->wc_product_id : ( 'manual' === $i->stock_mode ? 'manual: ' . $i->manual_stock_status : 'hidden' );
			echo '<tr><td>' . esc_html( $i->item_code ) . '</td><td>' . esc_html( $i->product_name ) . '</td><td>' . esc_html( str_replace( '|', ', ', $i->aliases ) ) . '</td><td>' . esc_html( $i->unit ) . '</td><td>' . esc_html( $i->pack_size ) . '</td><td class="num">' . esc_html( Util::bd_group( $i->price_bdt ) ) . '</td><td class="num">' . esc_html( null === $i->mrp_bdt ? '—' : Util::bd_group( $i->mrp_bdt ) ) . '</td><td>' . esc_html( $stock ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		Ui::footer();
	}
}
