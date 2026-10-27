<?php
/** 1.0.0 — generic business (no built-in business name), public prices for shops, no dealer-detail requests, health states. */
global $wpdb;
t_reset();
$s = t_seed();

// Business name: setting, else the site title; never a hard-coded company.
t_eq( '', RarIaa\Settings::defaults()['business_name'], 'no built-in business name' );
$title = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
t_eq( '' !== trim( $title ) ? trim( $title ) : 'our business', RarIaa\Settings::business_name(), 'empty setting → site title' );
t_eq( array(), RarIaa\Settings::update( array( 'business_name' => '' ) ), 'empty business name is valid' );
RarIaa\Settings::update( array( 'business_name' => 'Demo Traders' ) );
t_eq( 'Demo Traders', RarIaa\Settings::business_name(), 'setting wins' );
list( $facts ) = RarIaa\Draft\FactSelector::select( 'hello', null );
t_eq( 'Demo Traders', $facts['business_name'], 'facts carry the business name' );
RarIaa\Settings::update( array( 'business_name' => '' ) );

// Fresh-install settings: public website prices on when WooCommerce is active.
t_eq( class_exists( 'WooCommerce' ) ? 1 : 0, RarIaa\Settings::initial()['allow_public_mrp'], 'public prices default follows WooCommerce' );
t_eq( 0, RarIaa\Settings::defaults()['allow_public_mrp'], 'plain defaults unchanged (existing installs keep their choice)' );

// Prompt p5: generic business, never ask a customer for dealer details.
$p = RarIaa\Draft\PromptBuilder::suggestions( array( 'business_name' => 'Demo Traders', 'kb' => array() ), array( array( 'side' => 'customer', 'text' => 'Price koto?' ) ), 'bn', false, 'en', 3 );
t_eq( 'p5', RarIaa\Draft\PromptBuilder::PROMPT_VERSION, 'prompt version p5' );
t_contains( 'staff of Demo Traders, a business in Bangladesh', $p['system'], 'generic business line' );
t_contains( 'never ask them for dealer details', $p['system'], 'no dealer-detail requests to customers' );
t_ok( false === stripos( $p['system'] . $p['user'], 'nabiad' ), 'no company name hard-coded in the prompt' );

// /me tells the panel whether public prices are on.
$shop = t_user( 'shop_manager', 'iaa_shop_v100' );
$tok  = t_token( $shop );
RarIaa\Settings::update( array( 'allow_public_mrp' => 1 ) );
$r = t_http( 'GET', '/me', $tok );
t_eq( true, $r['json']['settings']['public_prices'], '/me public_prices on' );
RarIaa\Settings::update( array( 'allow_public_mrp' => 0 ) );
$r = t_http( 'GET', '/me', $tok );
t_eq( false, $r['json']['settings']['public_prices'], '/me public_prices off' );

// Admin header shows the version only; health rows use OK / Info / attention without task IDs.
wp_set_current_user( $s['admin'] );
ob_start();
RarIaa\Admin\Ui::header( 'Test' );
$html = ob_get_clean();
t_contains( '<div class="iaa-brand__meta">v' . RAR_IAA_VERSION . '</div>', $html, 'header shows only the version' );
$health = RarIaa\Admin\Pages::health();
$names  = array_column( $health, 0 );
$joined = wp_json_encode( $health );
t_ok( ! preg_match( '/\bT\d\.\d\d\b/', $joined ), 'no internal task IDs in health texts' );
$live = $health[ array_search( 'Live inbox read/insert', $names, true ) ];
t_eq( 'info', $live[1], 'live inbox is information, not a problem' );
delete_option( 'rar_iaa_maintenance_last_run' );
wp_clear_scheduled_hook( 'rar_iaa_daily_maintenance' );
wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rar_iaa_daily_maintenance' );
$m = RarIaa\Admin\Pages::health()[ array_search( 'Daily maintenance', $names, true ) ];
t_eq( 'info', $m[1], 'first maintenance run scheduled → info, not attention' );
wp_clear_scheduled_hook( 'rar_iaa_daily_maintenance' );
$m = RarIaa\Admin\Pages::health()[ array_search( 'Daily maintenance', $names, true ) ];
t_eq( false, $m[1], 'not scheduled → attention' );
update_option( 'rar_iaa_maintenance_last_run', array( 'at' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ), 'report' => array() ), false );
$m = RarIaa\Admin\Pages::health()[ array_search( 'Daily maintenance', $names, true ) ];
t_eq( false, $m[1], 'last run 3 days ago → overdue' );
update_option( 'rar_iaa_maintenance_last_run', array( 'at' => gmdate( 'Y-m-d H:i:s' ), 'report' => array() ), false );
$m = RarIaa\Admin\Pages::health()[ array_search( 'Daily maintenance', $names, true ) ];
t_eq( true, $m[1], 'recent run → OK' );
wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rar_iaa_daily_maintenance' );

// Product matching optimisation keeps the same results.
$items = array();
foreach ( array( 'Jaguar Classic Red Eau de Toilette 100ml', 'Jaguar Classic Blue 100ml', 'Signal Neem Coil' ) as $i => $n ) {
	$o               = new stdClass();
	$o->id           = $i;
	$o->item_code    = 'X' . $i;
	$o->aliases      = '';
	$o->product_name = $n;
	$items[]         = $o;
}
$text = 'Jagaur clasic red 100ml price?';
$m    = RarIaa\Draft\FactSelector::match_products( $items, RarIaa\Util::tokens( $text ), ' ' . RarIaa\Util::normalize( $text ) . ' ' );
t_eq( 'Jaguar Classic Red Eau de Toilette 100ml', $m[0]->product_name, 'misspelt name still matches best product first' );
