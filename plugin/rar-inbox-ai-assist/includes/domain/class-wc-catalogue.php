<?php
/**
 * WooCommerce product & price link (v0.2.0).
 *
 * Tiers whose price source is "woocommerce" get their price list built from WooCommerce products:
 * one snapshot per change (auditable, rollback-able), activated automatically because the prices
 * were already approved when they were set in WooCommerce. Stock is always read live.
 *
 * Sync triggers: "Sync now", price/name/status/SKU changes on product save (debounced via a single
 * WP-Cron event), the daily maintenance run, and a fallback at draft time when a change has been
 * pending for more than 5 minutes (sites with unreliable WP-Cron). Stock-only changes (orders) do
 * not trigger a sync, so checkout is never slowed down.
 *
 * Product fields (Product data → General): aliases customers use, unit, pack size, exclude.
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

use RarIaa\Audit;
use RarIaa\Db;
use RarIaa\Lock;
use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class WcCatalogue {

	const MAX_PRODUCTS    = 5000;
	const PENDING_OPTION  = 'rar_iaa_wc_sync_pending';
	const STATE_OPTION    = 'rar_iaa_wc_sync_state';
	const CRON_HOOK       = 'rar_iaa_wc_sync';
	const STALE_AFTER     = 300;
	const DEBOUNCE        = 60;
	const PRICE_KEYS      = array( 'name', 'sku', 'regular_price', 'sale_price', 'price', 'status', 'date_on_sale_from', 'date_on_sale_to', 'catalog_visibility', 'category_ids', 'parent_id' );
	const META_ALIASES    = '_rar_iaa_aliases';
	const META_UNIT       = '_rar_iaa_unit';
	const META_PACK       = '_rar_iaa_pack_size';
	const META_EXCLUDE    = '_rar_iaa_exclude';

	public static function available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
	}

	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron' ) );
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'on_product_save' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'mark_pending' ) );
		add_action( 'woocommerce_delete_product', array( __CLASS__, 'mark_pending' ) );
		add_action( 'woocommerce_trash_product', array( __CLASS__, 'mark_pending' ) );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_fields' ) );
	}

	/* ================================================================== Triggers */

	/** Only price-relevant changes mark the catalogue for sync (stock changes during checkout do not). */
	public static function on_product_save( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_changes' ) ) {
			return;
		}
		if ( ! $product->get_id() || array_intersect( array_keys( (array) $product->get_changes() ), self::PRICE_KEYS ) ) {
			self::mark_pending();
		}
	}

	public static function on_post_change( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'product' === $type || 'product_variation' === $type ) {
			self::mark_pending();
		}
	}

	/** Cheap: one option write and at most one scheduled event. */
	public static function mark_pending() {
		if ( ! get_option( self::PENDING_OPTION ) ) {
			update_option( self::PENDING_OPTION, time(), false );
		}
		if ( Settings::get( 'wc_auto_sync' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + self::DEBOUNCE, self::CRON_HOOK );
		}
	}

	public static function pending_since() {
		return (int) get_option( self::PENDING_OPTION, 0 );
	}

	public static function cron() {
		if ( Settings::get( 'wc_auto_sync' ) ) {
			self::sync( 0, 'auto' );
		}
	}

	/** Draft-time safety net when WP-Cron did not run: sync if a change waited > 5 minutes. */
	public static function maybe_sync_stale() {
		if ( ! self::available() || ! Settings::get( 'wc_auto_sync' ) ) {
			return;
		}
		$since = self::pending_since();
		if ( $since && time() - $since > self::STALE_AFTER ) {
			self::sync( 0, 'stale', 0 );
		}
	}

	/* ================================================================== Product fields */

	public static function product_fields() {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			return;
		}
		echo '<div class="options_group rar-iaa-product-fields">';
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_ALIASES,
				'label'       => 'Inbox AI: other names',
				'description' => 'Names customers use in messages (Bangla, Banglish, short names), separated by |. Example: জাগুয়ার|jaguar blue|জাগুয়ার নীল',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_UNIT,
				'label'       => 'Inbox AI: unit',
				'placeholder' => (string) Settings::get( 'wc_default_unit' ),
				'description' => 'Unit the price is for (pcs, carton, dozen …). Empty = default unit from Inbox AI Assist → Products & prices.',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_PACK,
				'label'       => 'Inbox AI: pack size',
				'description' => 'Optional, e.g. 12 x 10.',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_EXCLUDE,
				'label'       => 'Inbox AI: exclude',
				'description' => 'Do not use this product in AI replies.',
			)
		);
		echo '</div>';
	}

	/** Called inside WooCommerce's own save (capability and nonce already checked by WooCommerce). */
	public static function save_product_fields( $product ) {
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		$aliases = array();
		$raw     = isset( $_POST[ self::META_ALIASES ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_ALIASES ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( preg_split( '/[|;,]/u', $raw ) as $a ) {
			$a = trim( $a );
			if ( '' !== $a && Util::strlen( $a ) <= 60 ) {
				$aliases[] = $a;
			}
		}
		$unit = isset( $_POST[ self::META_UNIT ] ) ? substr( sanitize_text_field( wp_unslash( $_POST[ self::META_UNIT ] ) ), 0, 30 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$pack = isset( $_POST[ self::META_PACK ] ) ? substr( sanitize_text_field( wp_unslash( $_POST[ self::META_PACK ] ) ), 0, 50 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$before = array( (string) $product->get_meta( self::META_ALIASES ), (string) $product->get_meta( self::META_UNIT ), (string) $product->get_meta( self::META_PACK ), (string) $product->get_meta( self::META_EXCLUDE ) );
		$product->update_meta_data( self::META_ALIASES, implode( '|', array_slice( $aliases, 0, 20 ) ) );
		$product->update_meta_data( self::META_UNIT, $unit );
		$product->update_meta_data( self::META_PACK, $pack );
		$product->update_meta_data( self::META_EXCLUDE, empty( $_POST[ self::META_EXCLUDE ] ) ? 'no' : 'yes' ); // phpcs:ignore WordPress.Security.NonceVerification
		$after = array( (string) $product->get_meta( self::META_ALIASES ), (string) $product->get_meta( self::META_UNIT ), (string) $product->get_meta( self::META_PACK ), (string) $product->get_meta( self::META_EXCLUDE ) );
		if ( $before !== $after ) {
			self::mark_pending();
		}
	}

	/* ================================================================== Catalogue */

	/**
	 * Published, in-scope WooCommerce products as base rows (website prices, before tier rules).
	 * Variable products contribute one row per published variation.
	 *
	 * @return array{rows: array, skipped: int, truncated: bool}
	 */
	public static function collect() {
		$out = array( 'rows' => array(), 'skipped' => 0, 'truncated' => false );
		if ( ! self::available() ) {
			return $out;
		}
		$args = array(
			'status'  => 'publish',
			'limit'   => self::MAX_PRODUCTS + 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'return'  => 'objects',
		);
		$cats = (array) Settings::get( 'wc_categories' );
		if ( $cats ) {
			$slugs = array();
			foreach ( $cats as $term_id ) {
				$term = get_term( (int) $term_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			}
			if ( $slugs ) {
				$args['category'] = $slugs;
			}
		}
		$products = wc_get_products( $args );
		if ( count( $products ) > self::MAX_PRODUCTS ) {
			$products         = array_slice( $products, 0, self::MAX_PRODUCTS );
			$out['truncated'] = true;
		}
		$unit_default = (string) Settings::get( 'wc_default_unit' );
		$codes        = array();
		foreach ( $products as $product ) {
			if ( 'yes' === $product->get_meta( self::META_EXCLUDE ) || 'hidden' === $product->get_catalog_visibility() ) {
				continue;
			}
			$parent_meta = array(
				'aliases' => (string) $product->get_meta( self::META_ALIASES ),
				'unit'    => (string) $product->get_meta( self::META_UNIT ),
				'pack'    => (string) $product->get_meta( self::META_PACK ),
				'brands'  => self::brand_names( $product->get_id() ),
			);
			$items = $product->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $product->get_children() ) ) : array( $product );
			foreach ( $items as $item ) {
				if ( $item->get_id() !== $product->get_id() && 'publish' !== $item->get_status() ) {
					continue;
				}
				$row = self::base_row( $item, $parent_meta, $unit_default, $codes );
				if ( null === $row ) {
					$out['skipped']++;
					continue;
				}
				$codes[ $row['item_code'] ] = true;
				$out['rows'][]              = $row;
			}
		}
		return $out;
	}

	private static function brand_names( $product_id ) {
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$names = wp_get_post_terms( $product_id, $tax, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $names ) && $names ) {
					return $names;
				}
			}
		}
		return array();
	}

	/** One product/variation → base row, or null when it has no usable price. */
	private static function base_row( $item, array $parent_meta, $unit_default, array $codes ) {
		$current = (float) $item->get_price();
		$regular = (float) $item->get_regular_price();
		if ( $current <= 0 ) {
			return null;
		}
		if ( $regular <= 0 ) {
			$regular = $current;
		}
		$name = trim( wp_strip_all_tags( html_entity_decode( $item->get_name(), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $name ) {
			return null;
		}
		$sku  = strtoupper( (string) $item->get_sku() );
		$code = preg_replace( '/[^A-Z0-9_\-\/.]/', '-', $sku );
		$code = trim( substr( (string) $code, 0, 30 ), '-' );
		if ( '' === $code ) {
			$code = 'WC-' . $item->get_id();
		} elseif ( isset( $codes[ $code ] ) ) {
			$code = substr( $code, 0, 25 ) . '-' . $item->get_id();
		}
		$aliases = array();
		$own     = $item->get_id() !== $item->get_parent_id() ? (string) $item->get_meta( self::META_ALIASES ) : '';
		foreach ( array_merge( explode( '|', $parent_meta['aliases'] ), explode( '|', $own ), $parent_meta['brands'], array( $sku ) ) as $a ) {
			$a = trim( wp_strip_all_tags( (string) $a ) );
			if ( '' !== $a && ! in_array( $a, $aliases, true ) ) {
				$aliases[] = $a;
			}
		}
		$unit = trim( (string) $item->get_meta( self::META_UNIT ) );
		$unit = '' !== $unit ? $unit : ( '' !== $parent_meta['unit'] ? $parent_meta['unit'] : $unit_default );
		$pack = trim( (string) $item->get_meta( self::META_PACK ) );
		$pack = '' !== $pack ? $pack : $parent_meta['pack'];
		return array(
			'item_code'     => $code,
			'product_name'  => Util::substr( $name, 0, 200 ),
			'aliases'       => substr( implode( '|', $aliases ), 0, 1000 ),
			'unit'          => Util::substr( $unit, 0, 30 ),
			'pack_size'     => '' === $pack ? null : Util::substr( $pack, 0, 50 ),
			'current'       => round( $current, 2 ),
			'regular'       => round( $regular, 2 ),
			'wc_product_id' => (int) $item->get_id(),
			'edit_id'       => (int) ( $item->get_parent_id() ? $item->get_parent_id() : $item->get_id() ),
		);
	}

	/** Tier price for a base row, or null when the rule gives no positive price. */
	public static function tier_price( array $row, $tier ) {
		$base  = 'regular' === $tier->wc_price_basis ? (float) $row['regular'] : (float) $row['current'];
		$price = $base * ( 1 + (float) $tier->wc_adjust_pct / 100 );
		$step  = (int) $tier->wc_rounding;
		$price = $step > 0 ? round( $price / $step ) * $step : round( $price, 2 );
		return $price > 0 ? (float) $price : null;
	}

	/** Parsed-CSV-shaped result for PriceRepository::save_draft(). */
	public static function parsed_for_tier( array $rows, $tier ) {
		$out = array( 'rows' => array(), 'errors' => array(), 'warnings' => array(), 'sha256' => '' );
		foreach ( $rows as $r ) {
			$price = self::tier_price( $r, $tier );
			if ( null === $price ) {
				continue;
			}
			$out['rows'][] = array(
				'item_code'           => $r['item_code'],
				'product_name'        => $r['product_name'],
				'aliases'             => $r['aliases'],
				'unit'                => $r['unit'],
				'pack_size'           => $r['pack_size'],
				'price_bdt'           => $price,
				// The website price is public: it is what anyone sees on the shop.
				'mrp_bdt'             => $r['current'],
				'wc_product_id'       => $r['wc_product_id'],
				'stock_mode'          => 'wc',
				'manual_stock_status' => null,
			);
		}
		$out['sha256'] = hash( 'sha256', wp_json_encode( $out['rows'] ) );
		return $out;
	}

	/* ================================================================== Sync */

	/**
	 * Rebuild the price list of every active WooCommerce-sourced tier. A new snapshot is saved and
	 * activated only when something changed. Returns a summary array or WP_Error.
	 *
	 * @param int    $user_id 0 for automatic runs.
	 * @param string $reason  manual | auto | stale | daily | settings.
	 * @param int    $wait    Seconds to wait for the sync lock.
	 */
	public static function sync( $user_id = 0, $reason = 'manual', $wait = 5 ) {
		if ( ! self::available() ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'WooCommerce is not active.' );
		}
		if ( ! Lock::acquire( 'wc_sync', $wait, 300 ) ) {
			return new \WP_Error( 'BUSY', 'A sync is already running. Try again in a minute.' );
		}
		$started = microtime( true );
		try {
			// Clear first: changes made while this runs mark it pending again.
			delete_option( self::PENDING_OPTION );
			DealerRepository::ensure_public_tier( $user_id );
			$catalogue = self::collect();
			$summary   = array(
				'at'        => Db::now(),
				'reason'    => $reason,
				'products'  => count( $catalogue['rows'] ),
				'skipped'   => $catalogue['skipped'],
				'truncated' => $catalogue['truncated'],
				'tiers'     => array(),
				'error'     => '',
			);
			foreach ( DealerRepository::tiers( true ) as $tier ) {
				if ( 'woocommerce' !== $tier->price_source ) {
					continue;
				}
				$parsed = self::parsed_for_tier( $catalogue['rows'], $tier );
				$active = PriceRepository::active_for_tier( (int) $tier->id );
				if ( ! $parsed['rows'] ) {
					$summary['tiers'][ $tier->code ] = array( 'status' => 'empty', 'items' => 0 );
					continue;
				}
				if ( $active && 'woocommerce' === $active->source && hash_equals( (string) $active->file_sha256, $parsed['sha256'] ) ) {
					$summary['tiers'][ $tier->code ] = array( 'status' => 'unchanged', 'items' => count( $parsed['rows'] ) );
					continue;
				}
				$name = 'WooCommerce sync ' . wp_date( 'Y-m-d H:i', null, new \DateTimeZone( 'Asia/Dhaka' ) );
				$id   = PriceRepository::save_draft( (int) $tier->id, $name, Db::dhaka_date(), $parsed, $user_id, 'woocommerce' );
				if ( is_wp_error( $id ) ) {
					$summary['tiers'][ $tier->code ] = array( 'status' => 'error', 'items' => 0, 'message' => $id->get_error_message() );
					continue;
				}
				$act = PriceRepository::activate( (int) $id, $user_id );
				if ( is_wp_error( $act ) ) {
					$summary['tiers'][ $tier->code ] = array( 'status' => 'error', 'items' => 0, 'message' => $act->get_error_message() );
					continue;
				}
				PriceRepository::prune_synced( (int) $tier->id );
				$summary['tiers'][ $tier->code ] = array( 'status' => 'updated', 'items' => count( $parsed['rows'] ) );
			}
			$summary['ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );
			update_option( self::STATE_OPTION, $summary, false );
			if ( 'manual' === $reason || array_filter( $summary['tiers'], static function ( $t ) { return 'unchanged' !== $t['status']; } ) ) {
				Audit::record( 'wc_sync.run', 'settings', null, null, $summary, $user_id );
			}
			return $summary;
		} catch ( \Throwable $e ) {
			$state = array( 'at' => Db::now(), 'reason' => $reason, 'error' => 'Sync failed: ' . substr( $e->getMessage(), 0, 200 ) );
			update_option( self::STATE_OPTION, $state, false );
			update_option( self::PENDING_OPTION, time(), false );
			return new \WP_Error( 'INTERNAL_ERROR', $state['error'] );
		} finally {
			Lock::release( 'wc_sync' );
		}
	}

	public static function state() {
		$s = get_option( self::STATE_OPTION );
		return is_array( $s ) ? $s : null;
	}

	/** Top-level product categories for the scope picker. */
	public static function categories() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 300 ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}
}
