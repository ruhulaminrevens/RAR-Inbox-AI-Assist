<?php
/**
 * Selects the approved facts the model may use (FR-04, FR-05, FR-06, TRD §6 steps 8–9, v0.2 §16).
 *
 * Dealer-tier prices and stock are supplied only for a conversation linked to an active dealer.
 * Unverified conversations get public facts only: approved KB answers, product names, and
 * MRP when the admin allows public MRP.
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Db;
use RarIaa\Domain\KbRepository;
use RarIaa\Domain\PriceRepository;
use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class FactSelector {

	const MAX_KB       = 8;
	const MAX_PRODUCTS = 15;
	const PRICE_WORDS  = array( 'dam', 'daam', 'dām', 'price', 'prices', 'rate', 'rates', 'mrp', 'koto', 'cost', 'দাম', 'দর', 'রেট', 'মূল্য', 'কত', 'টাকা', 'taka', 'tk', 'pricelist', 'list' );

	/**
	 * @param string      $customer_text Masked customer text (latest messages joined).
	 * @param object|null $link          Thread link row (with dealer/tier fields) or null.
	 */
	public static function select( $customer_text, $link ) {
		// WooCommerce changes that waited too long for WP-Cron are synced before facts are read.
		\RarIaa\Domain\WcCatalogue::maybe_sync_stale();
		$tokens = Util::tokens( $customer_text );
		$norm   = ' ' . Util::normalize( $customer_text ) . ' ';
		$facts  = array(
			'business_name' => (string) Settings::get( 'business_name' ),
			'today'         => self::today(),
			'audience'      => 'unverified',
			'kb'            => array(),
			'products'      => array(),
			'public_prices' => array(),
			'notes'         => array(),
		);
		$meta   = array(
			'supplied'      => array(),
			'kb_entry_ids'  => array(),
			'tier_id'       => null,
			'price_list_id' => null,
			'dealer_id'     => null,
			'price_asked'   => self::price_asked( $tokens ),
		);

		// Knowledge base (public facts).
		$scored = array();
		foreach ( KbRepository::serving() as $row ) {
			$score = 0;
			foreach ( array_filter( explode( ',', (string) $row->keywords ) ) as $kw ) {
				$kwn = Util::normalize( $kw );
				if ( '' !== $kwn && false !== strpos( $norm, ' ' . $kwn . ' ' ) ) {
					$score += 3;
				} elseif ( '' !== $kwn && Util::strlen( $kwn ) >= 4 && false !== strpos( $norm, $kwn ) ) {
					$score += 2;
				}
			}
			foreach ( Util::tokens( $row->title ) as $t ) {
				if ( Util::strlen( $t ) >= 3 && in_array( $t, $tokens, true ) ) {
					$score += 1;
				}
			}
			$always = in_array( $row->category, array( 'greeting', 'contact' ), true );
			if ( $score > 0 || $always ) {
				$scored[] = array( $score + ( $always ? 0.5 : 0 ), $row );
			}
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		foreach ( array_slice( $scored, 0, self::MAX_KB ) as $s ) {
			$row                    = $s[1];
			$id                     = 'kb:' . (int) $row->entry_id;
			$facts['kb'][]          = array(
				'id'        => $id,
				'title'     => $row->title,
				'category'  => $row->category,
				'answer_bn' => $row->answer_bn,
				'answer_en' => $row->answer_en,
			);
			$meta['supplied'][]     = $id;
			$meta['kb_entry_ids'][] = (int) $row->entry_id;
		}

		// Dealer tier (only via an explicit link to an active dealer with an active tier).
		$list = null;
		if ( $link && 'active' === $link->dealer_status && 'active' === $link->tier_status ) {
			$facts['audience']   = 'dealer';
			$facts['dealer_tier'] = $link->tier_code;
			$meta['tier_id']     = (int) $link->tier_id;
			$meta['dealer_id']   = (int) $link->dealer_id;
			$list                = PriceRepository::active_for_tier( (int) $link->tier_id );
			if ( $list ) {
				$meta['price_list_id'] = (int) $list->id;
			} else {
				$facts['notes'][] = 'No active price list for this dealer tier: do not state any price.';
			}
		} else {
			$facts['notes'][] = 'Unverified contact (not linked to an active dealer): do not state any dealer price, discount, commission or stock level. Only public facts may be used.';
		}

		// Products.
		if ( $list ) {
			$items = PriceRepository::items( (int) $list->id );
		} else {
			$items = self::public_catalogue();
		}
		$matched = self::match_products( $items, $tokens, $norm );
		foreach ( $matched as $item ) {
			$id = 'price:' . (int) $item->id;
			if ( 'dealer' === $facts['audience'] ) {
				list( $bucket, $qty ) = StockService::for_item( $item );
				$p                    = array(
					'id'            => $id,
					'product'       => $item->product_name,
					'item_code'     => $item->item_code,
					'unit'          => $item->unit,
					'pack_size'     => $item->pack_size,
					'price_bdt'     => (float) $item->price_bdt,
					'price_display' => '৳' . Util::bd_group( $item->price_bdt ),
					'stock'         => $bucket,
				);
				if ( null !== $qty ) {
					$p['stock_quantity'] = $qty;
				}
				$facts['products'][] = $p;
			} else {
				$facts['products'][] = array(
					'id'        => $id,
					'product'   => $item->product_name,
					'unit'      => $item->unit,
					'pack_size' => $item->pack_size,
				);
				if ( Settings::get( 'allow_public_mrp' ) && null !== $item->mrp_bdt ) {
					$facts['public_prices'][] = array(
						'id'          => 'mrp:' . (int) $item->id,
						'product'     => $item->product_name,
						'unit'        => $item->unit,
						'label'       => self::public_label( $item ),
						'mrp_bdt'     => (float) $item->mrp_bdt,
						'mrp_display' => '৳' . Util::bd_group( $item->mrp_bdt ),
					);
					$meta['supplied'][] = 'mrp:' . (int) $item->id;
				}
			}
			$meta['supplied'][] = $id;
		}
		if ( $meta['price_asked'] && ! $matched ) {
			$facts['notes'][] = 'The customer asked about price but no listed product was recognised: ask which product and unit.';
		}
		return array( $facts, $meta );
	}

	/** "website price" for WooCommerce-synced items (the shop price anyone sees), else "MRP". */
	private static function public_label( $item ) {
		return isset( $item->list_source ) && 'woocommerce' === $item->list_source ? 'website price' : 'MRP';
	}

	/** Product names (and public prices) — the public website tier when it exists, else every active list; de-duplicated by item code. */
	private static function public_catalogue() {
		$rows = PriceRepository::public_items();
		$out  = array();
		foreach ( $rows as $r ) {
			if ( ! isset( $out[ $r->item_code ] ) ) {
				$out[ $r->item_code ] = $r;
			}
		}
		return array_values( $out );
	}

	public static function price_asked( array $tokens ) {
		foreach ( $tokens as $t ) {
			if ( in_array( $t, self::PRICE_WORDS, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** Match price items mentioned in the text. */
	public static function match_products( array $items, array $tokens, $norm ) {
		$scored = array();
		$set    = array_flip( $tokens );
		foreach ( $items as $item ) {
			$score = 0;
			$code  = Util::normalize( $item->item_code );
			if ( '' !== $code && false !== strpos( $norm, ' ' . $code . ' ' ) ) {
				$score += 5;
			}
			foreach ( array_filter( explode( '|', (string) $item->aliases ) ) as $alias ) {
				$an = Util::normalize( $alias );
				if ( '' !== $an && false !== strpos( $norm, ' ' . $an . ' ' ) ) {
					$score += 4;
				}
			}
			$name_tokens = array_filter(
				Util::tokens( $item->product_name ),
				static function ( $t ) {
					return Util::strlen( $t ) >= 3;
				}
			);
			$hits = 0;
			foreach ( $name_tokens as $nt ) {
				if ( isset( $set[ $nt ] ) ) {
					$hits++;
					continue;
				}
				if ( strlen( $nt ) >= 4 && preg_match( '/^[a-z0-9]+$/', $nt ) ) {
					foreach ( $tokens as $t ) {
						if ( strlen( $t ) >= 4 && Util::similarity( $nt, $t ) >= 0.8 ) {
							$hits += 0.8;
							break;
						}
					}
				}
			}
			if ( $hits >= 2 || ( $hits >= 1 && count( $name_tokens ) <= 2 ) ) {
				$score += 2 * $hits;
			}
			if ( $score > 0 ) {
				$scored[] = array( $score, $item );
			}
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		return array_map(
			static function ( $s ) {
				return $s[1];
			},
			array_slice( $scored, 0, self::MAX_PRODUCTS )
		);
	}

	private static function today() {
		$dt = new \DateTime( 'now', new \DateTimeZone( 'Asia/Dhaka' ) );
		return $dt->format( 'Y-m-d (l)' );
	}

	/**
	 * Rebuild a fact set from stored fact IDs (rewrite). Returns array( facts, meta, changed ).
	 */
	public static function from_ids( array $ids, $link ) {
		list( $base, $meta ) = self::select( '', $link );
		$base['kb']            = array();
		$base['products']      = array();
		$base['public_prices'] = array();
		$meta['supplied']      = array();
		$meta['kb_entry_ids']  = array();
		$changed               = false;
		$serving               = array();
		foreach ( KbRepository::serving() as $row ) {
			$serving[ (int) $row->entry_id ] = $row;
		}
		$price_ids = array();
		foreach ( $ids as $id ) {
			if ( ! preg_match( '/^(kb|price|mrp):(\d+)$/', (string) $id, $m ) ) {
				continue;
			}
			if ( 'kb' === $m[1] ) {
				if ( isset( $serving[ (int) $m[2] ] ) ) {
					$row                    = $serving[ (int) $m[2] ];
					$base['kb'][]           = array( 'id' => $id, 'title' => $row->title, 'category' => $row->category, 'answer_bn' => $row->answer_bn, 'answer_en' => $row->answer_en );
					$meta['supplied'][]     = $id;
					$meta['kb_entry_ids'][] = (int) $m[2];
				} else {
					$changed = true;
				}
			} else {
				$price_ids[ (int) $m[2] ][] = $m[1];
			}
		}
		foreach ( PriceRepository::items_by_ids( array_keys( $price_ids ) ) as $item ) {
			$kinds = $price_ids[ (int) $item->id ];
			if ( 'dealer' === $base['audience'] && (int) $item->price_list_id === (int) $meta['price_list_id'] && in_array( 'price', $kinds, true ) ) {
				list( $bucket, $qty ) = StockService::for_item( $item );
				$p                    = array(
					'id'            => 'price:' . (int) $item->id,
					'product'       => $item->product_name,
					'item_code'     => $item->item_code,
					'unit'          => $item->unit,
					'pack_size'     => $item->pack_size,
					'price_bdt'     => (float) $item->price_bdt,
					'price_display' => '৳' . Util::bd_group( $item->price_bdt ),
					'stock'         => $bucket,
				);
				if ( null !== $qty ) {
					$p['stock_quantity'] = $qty;
				}
				$base['products'][] = $p;
				$meta['supplied'][] = 'price:' . (int) $item->id;
			} elseif ( 'dealer' !== $base['audience'] ) {
				$base['products'][] = array( 'id' => 'price:' . (int) $item->id, 'product' => $item->product_name, 'unit' => $item->unit, 'pack_size' => $item->pack_size );
				$meta['supplied'][] = 'price:' . (int) $item->id;
				if ( in_array( 'mrp', $kinds, true ) && Settings::get( 'allow_public_mrp' ) && null !== $item->mrp_bdt ) {
					$base['public_prices'][] = array( 'id' => 'mrp:' . (int) $item->id, 'product' => $item->product_name, 'unit' => $item->unit, 'label' => self::public_label( $item ), 'mrp_bdt' => (float) $item->mrp_bdt, 'mrp_display' => '৳' . Util::bd_group( $item->mrp_bdt ) );
					$meta['supplied'][]      = 'mrp:' . (int) $item->id;
				}
			} else {
				$changed = true;
			}
		}
		return array( $base, $meta, $changed );
	}
}
