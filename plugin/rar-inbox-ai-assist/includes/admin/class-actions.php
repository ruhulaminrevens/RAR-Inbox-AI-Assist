<?php
/**
 * admin-post.php handlers. Every handler checks capability AND nonce (TRD §5.2).
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

use RarIaa\Audit;
use RarIaa\Auth\TokenService;
use RarIaa\Capabilities;
use RarIaa\Domain\DealerRepository;
use RarIaa\Domain\DraftLogRepository;
use RarIaa\Domain\KbRepository;
use RarIaa\Domain\PriceRepository;
use RarIaa\Providers\ProviderFactory;
use RarIaa\Settings;
use RarIaa\Db;

defined( 'ABSPATH' ) || exit;

class Actions {

	const MAP = array(
		'rar_iaa_kb_save'          => Capabilities::MANAGE_KB,
		'rar_iaa_kb_archive'       => Capabilities::MANAGE_KB,
		'rar_iaa_kb_restore'       => Capabilities::MANAGE_KB,
		'rar_iaa_kb_approve'       => Capabilities::APPROVE_KB,
		'rar_iaa_tier_save'        => Capabilities::MANAGE_PRICES,
		'rar_iaa_dealer_save'      => Capabilities::MANAGE_PRICES,
		'rar_iaa_price_import'     => Capabilities::MANAGE_PRICES,
		'rar_iaa_price_archive'    => Capabilities::MANAGE_PRICES,
		'rar_iaa_price_activate'   => Capabilities::ACTIVATE_PRICES,
		'rar_iaa_price_reactivate' => Capabilities::ACTIVATE_PRICES,
		'rar_iaa_token_revoke'     => Capabilities::USE,
		'rar_iaa_settings_save'    => Capabilities::MANAGE_SETTINGS,
		'rar_iaa_provider_test'    => Capabilities::MANAGE_SETTINGS,
		'rar_iaa_kill_switch'      => Capabilities::MANAGE_SETTINGS,
		'rar_iaa_logs_export'      => Capabilities::VIEW_LOGS,
	);

	public static function register() {
		foreach ( array_keys( self::MAP ) as $action ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, 'handle' ) );
		}
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function back( $page, array $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore
		if ( ! isset( self::MAP[ $action ] ) || ! current_user_can( self::MAP[ $action ] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.' ), 403 );
		}
		check_admin_referer( $action );
		$uid    = get_current_user_id();
		$method = substr( $action, strlen( 'rar_iaa_' ) );
		call_user_func( array( __CLASS__, $method ), $uid );
	}

	private static function result( $res, $page, $ok_message, array $args = array(), array $old = array() ) {
		if ( is_wp_error( $res ) ) {
			$data = $res->get_error_data();
			Notice::set( 'error', $res->get_error_message(), is_array( $data ) && isset( $data['fields'] ) ? $data['fields'] : array(), $old );
		} else {
			Notice::set( 'success', $ok_message );
		}
		self::back( $page, $args );
	}

	/* ------------------------------------------------------------------ KB */

	private static function kb_save( $uid ) {
		$id  = (int) self::post( 'id', 0 );
		$in  = array();
		foreach ( array( 'title', 'category', 'keywords', 'answer_bn', 'answer_en', 'effective_from', 'effective_to', 'note' ) as $k ) {
			$in[ $k ] = (string) self::post( $k );
		}
		$res = $id ? KbRepository::update( $id, $in, $uid, (int) self::post( 'row_version', 0 ) ) : KbRepository::create( $in, $uid );
		if ( ! is_wp_error( $res ) && self::post( 'submit_review' ) ) {
			KbRepository::submit( (int) $res, $uid );
		}
		$eid = is_wp_error( $res ) ? $id : (int) $res;
		self::result( $res, 'rar-iaa-kb', self::post( 'submit_review' ) ? 'Saved and submitted for approval.' : 'Saved as draft.', array( 'action' => $eid ? 'edit' : 'new', 'id' => $eid ), $in );
	}

	private static function kb_approve( $uid ) {
		$id = (int) self::post( 'id' );
		self::result( KbRepository::approve( $id, $uid, (int) self::post( 'row_version', 0 ) ), 'rar-iaa-kb', 'Approved. This version is now used for suggestions.', array( 'action' => 'edit', 'id' => $id ) );
	}

	private static function kb_archive( $uid ) {
		self::result( KbRepository::archive( (int) self::post( 'id' ), $uid ), 'rar-iaa-kb', 'Archived. It is no longer used.' );
	}

	private static function kb_restore( $uid ) {
		$id = (int) self::post( 'id' );
		self::result( KbRepository::restore( $id, $uid ), 'rar-iaa-kb', 'Restored as a new draft.', array( 'action' => 'edit', 'id' => $id ) );
	}

	/* ------------------------------------------------------------------ Dealers */

	private static function tier_save( $uid ) {
		$in = array( 'id' => self::post( 'id' ), 'code' => self::post( 'code' ), 'name' => self::post( 'name' ), 'status' => self::post( 'status' ), 'row_version' => self::post( 'row_version' ) );
		self::result( DealerRepository::save_tier( $in, $uid ), 'rar-iaa-dealers', 'Tier saved.', array( 'tab' => 'tiers' ), $in );
	}

	private static function dealer_save( $uid ) {
		$in = array();
		foreach ( array( 'id', 'dealer_code', 'name', 'tier_id', 'district_code', 'status', 'note', 'row_version' ) as $k ) {
			$in[ $k ] = self::post( $k );
		}
		$res  = DealerRepository::save_dealer( $in, $uid );
		$args = is_wp_error( $res ) ? array( 'edit' => (int) $in['id'] ? (int) $in['id'] : 'new' ) : array();
		self::result( $res, 'rar-iaa-dealers', 'Dealer saved.', $args, $in );
	}

	/* ------------------------------------------------------------------ Prices */

	private static function price_import( $uid ) {
		$tier = (int) self::post( 'tier_id' );
		$name = (string) self::post( 'name' );
		$from = (string) self::post( 'effective_from' );
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore
			Notice::set( 'error', 'Choose a CSV file.', array(), array( 'tier_id' => $tier, 'name' => $name, 'effective_from' => $from ) );
			self::back( 'rar-iaa-prices', array( 'action' => 'import' ) );
		}
		$parsed = PriceRepository::parse_csv( $_FILES['csv']['tmp_name'] ); // phpcs:ignore
		if ( $parsed['errors'] ) {
			set_transient( 'rar_iaa_import_errors_' . $uid, array( 'errors' => array_slice( $parsed['errors'], 0, 200 ), 'warnings' => array_slice( $parsed['warnings'], 0, 200 ), 'rows' => count( $parsed['rows'] ) ), 300 );
			Notice::set( 'error', sprintf( 'The file has %d error(s). Nothing was saved. Fix the CSV and upload again.', count( $parsed['errors'] ) ), array(), array( 'tier_id' => $tier, 'name' => $name, 'effective_from' => $from ) );
			self::back( 'rar-iaa-prices', array( 'action' => 'import' ) );
		}
		$res = PriceRepository::save_draft( $tier, $name, $from, $parsed, $uid );
		if ( is_wp_error( $res ) ) {
			Notice::set( 'error', $res->get_error_message(), array(), array( 'tier_id' => $tier, 'name' => $name, 'effective_from' => $from ) );
			self::back( 'rar-iaa-prices', array( 'action' => 'import' ) );
		}
		if ( $parsed['warnings'] ) {
			set_transient( 'rar_iaa_import_errors_' . $uid, array( 'errors' => array(), 'warnings' => array_slice( $parsed['warnings'], 0, 200 ), 'rows' => count( $parsed['rows'] ) ), 300 );
		}
		Notice::set( 'success', sprintf( 'Draft saved with %d rows — review, then Activate.', count( $parsed['rows'] ) ) );
		self::back( 'rar-iaa-prices', array( 'action' => 'view', 'id' => (int) $res ) );
	}

	private static function price_activate( $uid ) {
		$id = (int) self::post( 'id' );
		self::result( PriceRepository::activate( $id, $uid ), 'rar-iaa-prices', 'Activated. New suggestions use this price list.', array( 'action' => 'view', 'id' => $id ) );
	}

	private static function price_reactivate( $uid ) {
		$id = (int) self::post( 'id' );
		self::result( PriceRepository::activate( $id, $uid, true ), 'rar-iaa-prices', 'Previous price list re-activated.', array( 'action' => 'view', 'id' => $id ) );
	}

	private static function price_archive( $uid ) {
		self::result( PriceRepository::archive_draft( (int) self::post( 'id' ), $uid ), 'rar-iaa-prices', 'Draft archived.' );
	}

	/* ------------------------------------------------------------------ Tokens */

	private static function token_revoke( $uid ) {
		$id    = (int) self::post( 'id' );
		$owner = current_user_can( Capabilities::MANAGE_SETTINGS ) ? null : $uid;
		self::result( TokenService::revoke( $id, $uid, $owner ), 'rar-iaa-tokens', 'Token revoked. The extension using it is signed out on its next request.' );
	}

	/* ------------------------------------------------------------------ Settings */

	private static function settings_save( $uid ) {
		$before = Settings::all();
		$in     = array();
		$keys   = array( 'provider', 'anthropic_model', 'openai_model', 'price_in_usd_per_mtok', 'price_out_usd_per_mtok', 'max_output_tokens', 'suggestion_count', 'per_minute', 'daily_limit', 'monthly_cap_usd', 'max_concurrent', 'retention_days', 'banglish_reply_script', 'reply_digits', 'business_name', 'holding_reply_bn', 'holding_reply_en', 'banned_phrases' );
		foreach ( $keys as $k ) {
			if ( isset( $_POST[ $k ] ) ) { // phpcs:ignore
				$in[ $k ] = self::post( $k );
			}
		}
		foreach ( array( 'ai_enabled', 'allow_public_mrp', 'exact_stock', 'masked_logging', 'live_inbox', 'delete_data_on_uninstall' ) as $k ) {
			$in[ $k ] = self::post( $k ) ? 1 : 0;
		}
		$in['extra_use_roles']  = (array) self::post( 'extra_use_roles', array() );
		$in['extra_link_roles'] = (array) self::post( 'extra_link_roles', array() );
		$errors                 = Settings::update( $in );
		if ( $errors ) {
			Notice::set( 'error', 'Settings were not saved. Please fix the errors.', $errors, $in );
			self::back( 'rar-iaa-settings' );
		}
		foreach ( array( 'anthropic', 'openai' ) as $p ) {
			$key = trim( (string) self::post( 'key_' . $p ) );
			if ( '' !== $key && 'constant' !== Settings::key_source( $p ) ) {
				Settings::store_api_key( $p, $key );
				Audit::record( 'settings.key_set', 'settings', null, null, array( 'provider' => $p ), $uid );
			}
			if ( self::post( 'clear_key_' . $p ) ) {
				Settings::store_api_key( $p, '' );
				Audit::record( 'settings.key_clear', 'settings', null, null, array( 'provider' => $p ), $uid );
			}
		}
		Capabilities::sync();
		Audit::record( 'settings.save', 'settings', null, $before, Settings::all(), $uid );
		Notice::set( 'success', 'Settings saved.' );
		self::back( 'rar-iaa-settings' );
	}

	private static function provider_test( $uid ) {
		$p = ProviderFactory::make();
		if ( is_wp_error( $p ) ) {
			$msg = $p->get_error_message();
		} else {
			$r = $p->complete(
				array( 'system' => 'Reply with the single word OK.', 'user' => 'Test connection' ),
				array( 'max_tokens' => 20, 'timeout' => 20 )
			);
			$msg = is_wp_error( $r ) ? 'Failed: ' . $r->get_error_message() : 'OK — ' . $r['model'] . ', ' . $r['latency_ms'] . ' ms';
		}
		Settings::update( array( 'last_test_result' => $msg, 'last_test_at' => Db::now() ) );
		Audit::record( 'settings.provider_test', 'settings', null, null, array( 'result' => $msg ), $uid );
		Notice::set( 0 === strpos( $msg, 'OK' ) ? 'success' : 'error', 'Test connection: ' . $msg );
		self::back( (string) self::post( 'return', 'rar-iaa-settings' ) === 'rar-iaa' ? 'rar-iaa' : 'rar-iaa-settings' );
	}

	private static function kill_switch( $uid ) {
		$on = 'off' === self::post( 'state' );
		Settings::update( array( 'service_enabled' => $on ? 0 : 1 ) );
		Audit::record( $on ? 'kill_switch.on' : 'kill_switch.off', 'settings', null, null, array( 'service_enabled' => $on ? 0 : 1 ), $uid );
		Notice::set( $on ? 'warning' : 'success', $on ? 'Service stopped. All suggestions are blocked.' : 'Service started.' );
		self::back( 'rar-iaa' );
	}

	/* ------------------------------------------------------------------ Logs export */

	private static function cell( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$v = "'" . $v;
		}
		return $v;
	}

	private static function logs_export( $uid ) {
		$args             = array(
			'from'     => (string) self::post( 'from' ),
			'to'       => (string) self::post( 'to' ),
			'per_page' => 5000,
		);
		list( $rows ) = DraftLogRepository::list_admin( $args );
		Audit::record( 'logs.export', 'settings', null, null, array( 'from' => $args['from'], 'to' => $args['to'], 'rows' => count( $rows ) ), $uid );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="inbox-ai-assist-log-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore
		fputcsv( $out, array( 'time_dhaka', 'request_id', 'user', 'dealer', 'source', 'language', 'status', 'error', 'guard', 'warnings', 'handoff', 'handoff_reason', 'selected_variant', 'outcome', 'reject_reason', 'latency_ms', 'tokens_in', 'tokens_out', 'cost_usd' ), ',', '"', '\\' );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'cell' ),
					array( Db::display( $r->created_at ), $r->request_id, $r->display_name, $r->dealer_code, $r->source, $r->language, $r->status, $r->error_code, $r->guard_result, $r->warnings_count, $r->handoff_needed, $r->handoff_reason, $r->selected_variant_id, $r->outcome, $r->reject_reason, $r->latency_ms, $r->tokens_in, $r->tokens_out, number_format( $r->cost_usd_micros / 1000000, 6, '.', '' ) )
				),
				',',
				'"',
				'\\'
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
