<?php
/** WooCommerce product & price link: sync, tier rules, triggers, public website prices, dealer prices end-to-end. Skipped without WooCommerce. */
if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
	echo "  (skipped: WooCommerce not active)\n";
	return;
}
use RarIaa\Domain\DealerRepository;
use RarIaa\Domain\PriceRepository;
use RarIaa\Domain\WcCatalogue;
use RarIaa\Draft\FactSelector;
use RarIaa\Settings;

global $wpdb;
t_reset();
$s     = t_seed();
$admin = $s['admin'];
wp_set_current_user( $admin );
$wpdb->query( 'DELETE FROM ' . RarIaa\Db::t( 'tiers' ) . ' WHERE is_public = 1' );
delete_option( WcCatalogue::STATE_OPTION );
delete_option( WcCatalogue::PENDING_OPTION );
// Only the products created here.
$cat    = wp_insert_term( 'IAA Test Perfume ' . wp_rand( 1, 99999 ), 'product_cat' );
$cat_id = (int) $cat['term_id'];
Settings::update( array( 'wc_categories' => array( $cat_id ), 'wc_default_unit' => 'pcs', 'wc_auto_sync' => 1, 'allow_public_mrp' => 1, 'per_minute' => 60 ) );

$simple = function ( $name, $regular, $sale = '', $extra = array() ) use ( $cat_id ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( (string) $regular );
	if ( '' !== $sale ) {
		$p->set_sale_price( (string) $sale );
	}
	$p->set_category_ids( array( $cat_id ) );
	foreach ( $extra as $k => $v ) {
		$p->{"set_{$k}"}( $v );
	}
	return $p;
};
$a = $simple( 'Jaguar Classic Blue 100ml', 1670, 1250, array( 'sku' => 'JAG-BLUE' ) );
$a->update_meta_data( WcCatalogue::META_ALIASES, 'জাগুয়ার|jaguar blue' );
$a_id = $a->save();
$b_id = $simple( 'DC Fluid Intense 100ml', 750 )->save();
$hid  = $simple( 'Hidden tester', 100, '', array( 'catalog_visibility' => 'hidden' ) )->save();
$exc  = $simple( 'Excluded gift', 200 );
$exc->update_meta_data( WcCatalogue::META_EXCLUDE, 'yes' );
$exc_id = $exc->save();
$nop    = new WC_Product_Simple();
$nop->set_name( 'No price yet' );
$nop->set_category_ids( array( $cat_id ) );
$nop_id = $nop->save();
// Variable product with two variations.
$attr = new WC_Product_Attribute();
$attr->set_name( 'Size' );
$attr->set_options( array( '50ml', '100ml' ) );
$attr->set_visible( true );
$attr->set_variation( true );
$var = new WC_Product_Variable();
$var->set_name( 'Cuban Glory EDT' );
$var->set_category_ids( array( $cat_id ) );
$var->set_attributes( array( $attr ) );
$var->update_meta_data( WcCatalogue::META_UNIT, 'bottle' );
$var_id = $var->save();
$vids   = array();
foreach ( array( '50ml' => 400, '100ml' => 700 ) as $size => $price ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $var_id );
	$v->set_attributes( array( 'size' => $size ) );
	$v->set_regular_price( (string) $price );
	$v->set_status( 'publish' );
	$vids[ $size ] = $v->save();
}
WC_Product_Variable::sync( $var_id );
$all_ids = array( $a_id, $b_id, $hid, $exc_id, $nop_id, $var_id );

// Dealer tiers following WooCommerce.
$tb = DealerRepository::save_tier( array( 'code' => 'D', 'name' => 'Dealer (WC −10%)' ), $admin );
t_eq( (int) $tb, DealerRepository::save_tier_rule( $tb, array( 'price_source' => 'woocommerce', 'wc_price_basis' => 'current', 'wc_adjust_pct' => '-10', 'wc_rounding' => 5 ), $admin ), 'rule saved' );
$tr = DealerRepository::save_tier( array( 'code' => 'R', 'name' => 'Regular −5%' ), $admin );
DealerRepository::save_tier_rule( $tr, array( 'price_source' => 'woocommerce', 'wc_price_basis' => 'regular', 'wc_adjust_pct' => '-5', 'wc_rounding' => 0 ), $admin );
t_ok( is_wp_error( DealerRepository::save_tier_rule( $tb, array( 'price_source' => 'woocommerce', 'wc_adjust_pct' => '-95' ), $admin ) ), 'adjustment below −90% refused' );
t_ok( is_wp_error( DealerRepository::save_tier_rule( $tb, array( 'price_source' => 'woocommerce', 'wc_rounding' => 3 ), $admin ) ), 'odd rounding refused' );

// First sync.
$sum = WcCatalogue::sync( $admin, 'manual' );
t_ok( is_array( $sum ), 'sync ran' );
$pub = DealerRepository::public_tier();
t_ok( $pub && 'woocommerce' === $pub->price_source && (int) $pub->is_public, 'public website tier created' );
t_ok( is_wp_error( DealerRepository::save_tier_rule( (int) $pub->id, array( 'price_source' => 'woocommerce', 'wc_adjust_pct' => '-10' ), $admin ) ), 'public tier cannot be discounted' );
t_eq( 4, (int) $sum['products'], 'linked: 2 simple + 2 variations (hidden, excluded, no-price left out)' );
t_eq( 1, (int) $sum['skipped'], 'product without price skipped' );
t_eq( 'updated', $sum['tiers'][ $pub->code ]['status'], 'website list created' );
t_eq( 'updated', $sum['tiers']['D']['status'], 'dealer list created' );
t_ok( ! isset( $sum['tiers']['B'] ), 'CSV tier untouched' );
$by_code = function ( $tier_id ) {
	$l   = PriceRepository::active_for_tier( (int) $tier_id );
	$out = array();
	foreach ( PriceRepository::items( (int) $l->id ) as $i ) {
		$out[ $i->wc_product_id ] = $i;
	}
	return array( $l, $out );
};
list( $wl, $web ) = $by_code( $pub->id );
t_eq( 'woocommerce', $wl->source, 'website list source' );
t_eq( 1250.0, (float) $web[ $a_id ]->price_bdt, 'website price = current (sale) price' );
t_eq( 'JAG-BLUE', $web[ $a_id ]->item_code, 'SKU becomes item code' );
t_contains( 'জাগুয়ার', $web[ $a_id ]->aliases, 'Bangla alias from product field' );
t_eq( 'wc', $web[ $a_id ]->stock_mode, 'stock read live from WooCommerce' );
t_eq( 'WC-' . $b_id, $web[ $b_id ]->item_code, 'no SKU → WC-id code' );
t_eq( 700.0, (float) $web[ $vids['100ml'] ]->price_bdt, 'variation price' );
t_contains( '100ml', $web[ $vids['100ml'] ]->product_name, 'variation name includes size' );
t_eq( 'bottle', $web[ $vids['50ml'] ]->unit, 'unit inherited from parent field' );
t_eq( 'pcs', $web[ $a_id ]->unit, 'default unit' );
t_ok( ! isset( $web[ $hid ] ) && ! isset( $web[ $exc_id ] ), 'hidden and excluded products not linked' );
list( , $dl ) = $by_code( $tb );
t_eq( 1125.0, (float) $dl[ $a_id ]->price_bdt, 'dealer: 1250 −10% = 1125' );
t_eq( 675.0, (float) $dl[ $b_id ]->price_bdt, 'dealer: 750 −10% = 675' );
t_eq( 360.0, (float) $dl[ $vids['50ml'] ]->price_bdt, 'dealer: 400 −10% = 360' );
t_eq( 1250.0, (float) $dl[ $a_id ]->mrp_bdt, 'dealer list keeps the website price as public price' );
list( , $rl ) = $by_code( $tr );
t_eq( 1586.5, (float) $rl[ $a_id ]->price_bdt, 'regular basis: 1670 −5% = 1586.50 (no rounding)' );

// Second sync without changes keeps the same list.
$sum2 = WcCatalogue::sync( $admin, 'manual' );
t_eq( 'unchanged', $sum2['tiers']['D']['status'], 'no change → unchanged' );
t_eq( (int) $wl->id, (int) PriceRepository::active_for_tier( $pub->id )->id, 'no new snapshot' );

// Stock-only change (an order) does not mark pending; a price change does.
delete_option( WcCatalogue::PENDING_OPTION );
wp_clear_scheduled_hook( WcCatalogue::CRON_HOOK );
$p = wc_get_product( $b_id );
$p->set_manage_stock( true );
$p->save();
delete_option( WcCatalogue::PENDING_OPTION );
$p = wc_get_product( $b_id );
$p->set_stock_quantity( 7 );
$p->save();
t_eq( 0, WcCatalogue::pending_since(), 'stock change does not trigger a sync' );
$p = wc_get_product( $b_id );
$p->set_regular_price( '800' );
$p->save();
t_ok( WcCatalogue::pending_since() > 0, 'price change marks pending' );
t_ok( (bool) wp_next_scheduled( WcCatalogue::CRON_HOOK ), 'debounced sync scheduled' );
WcCatalogue::cron();
t_eq( 0, WcCatalogue::pending_since(), 'cron sync clears pending' );
list( $dl2, $dl ) = $by_code( $tb );
t_eq( 720.0, (float) $dl[ $b_id ]->price_bdt, 'dealer price follows: 800 −10% = 720' );
t_eq( (int) $dl2->previous_list_id > 0, true, 'previous snapshot kept for rollback' );

// Draft-time safety net when WP-Cron did not run.
update_option( WcCatalogue::PENDING_OPTION, time() - 400, false );
FactSelector::select( 'dam koto', null );
t_eq( 'stale', WcCatalogue::state()['reason'], 'stale pending change synced at draft time' );

// Facts: unverified contact gets the public website price (setting on), never the dealer price.
list( $facts ) = FactSelector::select( 'jaguar blue er dam koto?', null );
t_eq( 'unverified', $facts['audience'], 'unverified audience' );
t_eq( 'website price', $facts['public_prices'][0]['label'] ?? '', 'public price labelled website price' );
t_eq( 1250.0, $facts['public_prices'][0]['mrp_bdt'] ?? null, 'public price is the shop price' );
t_ok( ! isset( $facts['products'][0]['price_bdt'] ), 'no dealer price for unverified contact' );

// Dealer link → tier D price through the REST pipeline (stub answers from supplied facts).
$dealer = DealerRepository::save_dealer( array( 'dealer_code' => 'WC-D1', 'name' => 'Test Dealer', 'tier_id' => $tb ), $admin );
$shop   = t_user( 'shop_manager', 'iaa_shop_wcsync' );
$tok    = t_token( $shop );
$thread = '111:wcsync' . wp_rand( 1, 99999 );
$r      = t_http( 'PUT', '/threads/current/link', $tok, array( 'dealer_id' => $dealer, 'expected_dealer_id' => null ), array( 'X-IAA-Thread' => $thread ) );
t_eq( 200, $r['status'], 'conversation linked to dealer' );
$r = t_http( 'POST', '/drafts', $tok, array( 'source' => 'selection', 'messages' => array( array( 'side' => 'customer', 'text' => 'Jaguar blue er dam koto?' ) ) ), array( 'Idempotency-Key' => t_uuid(), 'X-IAA-Thread' => $thread ) );
t_eq( 200, $r['status'], 'draft 200' );
t_eq( 'dealer', $r['json']['audience'], 'dealer audience' );
if ( ! t_contains( '1,125', $r['json']['suggestions'][0]['reply'], 'dealer price quoted' ) ) {
	echo '  debug reply: ' . $r['json']['suggestions'][0]['reply'] . ' | ' . wp_json_encode( $r['json']['suggestions'][0]['guard'], JSON_UNESCAPED_UNICODE ) . "\n";
}
t_eq( 'pass', $r['json']['suggestions'][0]['guard']['result'], 'guard pass on synced price' );
t_ok( false === strpos( $r['json']['suggestions'][0]['reply'], '1,250' ), 'website price not used for the dealer' );

// Old snapshots are pruned (newest 10 archived kept).
for ( $i = 0; $i < 12; $i++ ) {
	$p = wc_get_product( $b_id );
	$p->set_regular_price( (string) ( 900 + $i ) );
	$p->save();
	WcCatalogue::sync( $admin, 'manual' );
}
$archived = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'price_lists' ) . " WHERE tier_id = %d AND source = 'woocommerce' AND status = 'archived'", $tb ) );
t_ok( $archived <= 11 && $archived >= 10, 'archived snapshots pruned to about 10 (got ' . $archived . ')' );
$orphans = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'price_items' ) . ' i LEFT JOIN ' . RarIaa\Db::t( 'price_lists' ) . ' l ON l.id = i.price_list_id WHERE l.id IS NULL' );
t_eq( 0, $orphans, 'no orphaned price items' );

// Clean up.
foreach ( array_merge( $all_ids, array_values( $vids ) ) as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_term( $cat_id, 'product_cat' );
Settings::update( array( 'wc_categories' => array(), 'allow_public_mrp' => 0 ) );
