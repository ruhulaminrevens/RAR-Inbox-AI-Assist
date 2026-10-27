<?php
/**
 * Schema creation and migrations (Schema §4, §7, §13).
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Installer {

	public static function activate() {
		self::migrate( true );
		Capabilities::sync();
		if ( ! wp_next_scheduled( 'rar_iaa_daily_maintenance' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'rar_iaa_daily_maintenance' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'rar_iaa_daily_maintenance' );
	}

	/** Called on every load; cheap when up to date. */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'rar_iaa_schema_version', 0 ) < RAR_IAA_SCHEMA_VERSION ) {
			self::migrate( false );
		}
	}

	public static function migrate( $force ) {
		// Bootstrap: the SQLite lock fallback needs the rate_buckets table itself.
		if ( Db::is_sqlite() && ! self::table_exists( 'rate_buckets' ) ) {
			self::create_tables();
		}
		if ( ! Lock::acquire( 'migrate', 10, 120 ) ) {
			return false;
		}
		try {
			if ( ! $force && (int) get_option( 'rar_iaa_schema_version', 0 ) >= RAR_IAA_SCHEMA_VERSION ) {
				return true;
			}
			self::create_tables();
			if ( ! get_option( 'rar_iaa_thread_secret' ) ) {
				add_option( 'rar_iaa_thread_secret', base64_encode( random_bytes( 32 ) ), '', 'no' );
			}
			if ( false === get_option( Settings::OPTION, false ) ) {
				add_option( Settings::OPTION, Settings::initial(), '', 'no' );
			}
			$missing = self::missing_tables();
			if ( $missing ) {
				update_option( 'rar_iaa_migration_error', 'Missing tables: ' . implode( ', ', $missing ), false );
				return false;
			}
			delete_option( 'rar_iaa_migration_error' );
			update_option( 'rar_iaa_schema_version', RAR_IAA_SCHEMA_VERSION, true );
			if ( function_exists( 'error_log' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[rar-iaa] migration to schema ' . RAR_IAA_SCHEMA_VERSION . ' done' ); // phpcs:ignore
			}
			return true;
		} finally {
			Lock::release( 'migrate' );
		}
	}

	public static function table_exists( $short ) {
		global $wpdb;
		$name = Db::t( $short );
		return $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );
	}

	public static function missing_tables() {
		$missing = array();
		foreach ( Db::TABLES as $short ) {
			if ( ! self::table_exists( $short ) ) {
				$missing[] = $short;
			}
		}
		return $missing;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'rar_iaa_';

		$sql = array();

		$sql[] = "CREATE TABLE {$p}kb_entries (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  category varchar(30) NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'draft',
  approved_version_id bigint(20) unsigned NULL,
  draft_version_id bigint(20) unsigned NULL,
  pending_review tinyint(1) NOT NULL DEFAULT 0,
  use_count int(10) unsigned NOT NULL DEFAULT 0,
  last_used_at datetime NULL,
  row_version int(10) unsigned NOT NULL DEFAULT 1,
  created_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_category (status,category)
) $c;";

		$sql[] = "CREATE TABLE {$p}kb_versions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  entry_id bigint(20) unsigned NOT NULL,
  version_no int(10) unsigned NOT NULL,
  state varchar(12) NOT NULL DEFAULT 'draft',
  title varchar(120) NOT NULL,
  keywords varchar(1000) NOT NULL DEFAULT '',
  answer_bn text NOT NULL,
  answer_en text NULL,
  effective_from date NOT NULL,
  effective_to date NULL,
  note varchar(500) NULL,
  edited_by bigint(20) unsigned NOT NULL,
  edited_at datetime NOT NULL,
  approved_by bigint(20) unsigned NULL,
  approved_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY entry_version (entry_id,version_no),
  KEY state_dates (state,effective_from,effective_to)
) $c;";

		$sql[] = "CREATE TABLE {$p}tiers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(20) NOT NULL,
  name varchar(100) NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'active',
  price_source varchar(12) NOT NULL DEFAULT 'csv',
  wc_price_basis varchar(10) NOT NULL DEFAULT 'current',
  wc_adjust_pct decimal(6,2) NOT NULL DEFAULT 0,
  wc_rounding smallint(5) unsigned NOT NULL DEFAULT 0,
  is_public tinyint(1) NOT NULL DEFAULT 0,
  row_version int(10) unsigned NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code)
) $c;";

		$sql[] = "CREATE TABLE {$p}dealers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  dealer_code varchar(30) NOT NULL,
  name varchar(150) NOT NULL,
  tier_id bigint(20) unsigned NOT NULL,
  district_code varchar(8) NULL,
  status varchar(10) NOT NULL DEFAULT 'active',
  note varchar(500) NULL,
  row_version int(10) unsigned NOT NULL DEFAULT 1,
  created_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY dealer_code (dealer_code),
  KEY tier_status (tier_id,status),
  KEY name (name(50))
) $c;";

		$sql[] = "CREATE TABLE {$p}price_lists (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tier_id bigint(20) unsigned NOT NULL,
  name varchar(150) NOT NULL,
  source varchar(12) NOT NULL DEFAULT 'csv',
  status varchar(10) NOT NULL DEFAULT 'draft',
  active_flag tinyint(1) NULL,
  effective_from date NOT NULL,
  file_sha256 char(64) NOT NULL,
  row_count int(10) unsigned NOT NULL,
  previous_list_id bigint(20) unsigned NULL,
  imported_by bigint(20) unsigned NOT NULL,
  imported_at datetime NOT NULL,
  activated_by bigint(20) unsigned NULL,
  activated_at datetime NULL,
  archived_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY one_active_per_tier (tier_id,active_flag),
  KEY status (status)
) $c;";

		$sql[] = "CREATE TABLE {$p}price_items (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  price_list_id bigint(20) unsigned NOT NULL,
  item_code varchar(40) NOT NULL,
  product_name varchar(200) NOT NULL,
  aliases varchar(1000) NOT NULL DEFAULT '',
  unit varchar(30) NOT NULL,
  pack_size varchar(50) NULL,
  price_bdt decimal(12,2) NOT NULL,
  mrp_bdt decimal(12,2) NULL,
  wc_product_id bigint(20) unsigned NULL,
  stock_mode varchar(8) NOT NULL DEFAULT 'hidden',
  manual_stock_status varchar(14) NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY list_code (price_list_id,item_code),
  KEY wc_product (wc_product_id)
) $c;";

		$sql[] = "CREATE TABLE {$p}thread_links (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  thread_hash char(64) NOT NULL,
  dealer_id bigint(20) unsigned NOT NULL,
  linked_by bigint(20) unsigned NOT NULL,
  linked_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY thread (thread_hash),
  KEY dealer (dealer_id)
) $c;";

		$sql[] = "CREATE TABLE {$p}tokens (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  token_prefix char(8) NOT NULL,
  token_hash char(64) NOT NULL,
  label varchar(60) NOT NULL,
  created_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  last_used_at datetime NULL,
  revoked_at datetime NULL,
  revoked_by bigint(20) unsigned NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY prefix (token_prefix),
  KEY user_active (user_id,revoked_at)
) $c;";

		$sql[] = "CREATE TABLE {$p}draft_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  draft_uuid char(36) NOT NULL,
  parent_draft_id bigint(20) unsigned NULL,
  request_id char(36) NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  token_id bigint(20) unsigned NULL,
  thread_hash char(64) NULL,
  dealer_id bigint(20) unsigned NULL,
  tier_id bigint(20) unsigned NULL,
  price_list_id bigint(20) unsigned NULL,
  source varchar(10) NOT NULL,
  rewrite_mode varchar(10) NULL,
  language varchar(2) NULL,
  provider varchar(20) NULL,
  model varchar(60) NULL,
  prompt_version varchar(10) NULL,
  status varchar(5) NOT NULL,
  error_code varchar(40) NULL,
  guard_result varchar(8) NULL,
  warnings_count smallint(5) unsigned NOT NULL DEFAULT 0,
  handoff_needed tinyint(1) NOT NULL DEFAULT 0,
  handoff_reason varchar(30) NULL,
  facts_used text NULL,
  suggestion_count tinyint(3) unsigned NOT NULL DEFAULT 3,
  selected_variant_id varchar(20) NULL,
  variant_guard_summary text NULL,
  input_masked mediumtext NULL,
  reply_masked text NULL,
  tokens_in int(10) unsigned NOT NULL DEFAULT 0,
  tokens_out int(10) unsigned NOT NULL DEFAULT 0,
  cost_usd_micros bigint(20) unsigned NOT NULL DEFAULT 0,
  latency_ms int(10) unsigned NOT NULL DEFAULT 0,
  provider_latency_ms int(10) unsigned NOT NULL DEFAULT 0,
  adapter_version varchar(30) NULL,
  adapter_strategy varchar(20) NULL,
  outcome varchar(16) NOT NULL DEFAULT 'pending',
  reject_reason varchar(12) NULL,
  insert_failed tinyint(1) NOT NULL DEFAULT 0,
  edit_distance smallint(5) unsigned NULL,
  outcome_at datetime NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY draft_uuid (draft_uuid),
  KEY created (created_at),
  KEY user_created (user_id,created_at),
  KEY outcome_created (outcome,created_at),
  KEY thread (thread_hash)
) $c;";

		$sql[] = "CREATE TABLE {$p}usage_daily (
  day date NOT NULL,
  requests int(10) unsigned NOT NULL DEFAULT 0,
  errors int(10) unsigned NOT NULL DEFAULT 0,
  tokens_in bigint(20) unsigned NOT NULL DEFAULT 0,
  tokens_out bigint(20) unsigned NOT NULL DEFAULT 0,
  cost_usd_micros bigint(20) unsigned NOT NULL DEFAULT 0,
  reserved_cost_usd_micros bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (day)
) $c;";

		$sql[] = "CREATE TABLE {$p}rate_buckets (
  bucket_key varchar(80) NOT NULL,
  count int(10) unsigned NOT NULL DEFAULT 0,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (bucket_key),
  KEY expires (expires_at)
) $c;";

		$sql[] = "CREATE TABLE {$p}audit_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  action varchar(50) NOT NULL,
  object_type varchar(30) NOT NULL,
  object_id bigint(20) unsigned NULL,
  before_json mediumtext NULL,
  after_json mediumtext NULL,
  ip_hash char(64) NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY object (object_type,object_id),
  KEY created (created_at),
  KEY user (user_id)
) $c;";

		$sql[] = "CREATE TABLE {$p}cost_reservations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_id char(36) NOT NULL,
  day date NOT NULL,
  amount_usd_micros bigint(20) unsigned NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'reserved',
  expires_at datetime NOT NULL,
  created_at datetime NOT NULL,
  settled_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY request (request_id),
  KEY status_expires (status,expires_at)
) $c;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/** Drop everything (uninstall with "delete data" enabled). */
	public static function drop_all() {
		global $wpdb;
		foreach ( Db::TABLES as $short ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . Db::t( $short ) ); // phpcs:ignore
		}
		foreach ( array( Settings::OPTION, 'rar_iaa_schema_version', 'rar_iaa_thread_secret', 'rar_iaa_secret_anthropic', 'rar_iaa_secret_openai', 'rar_iaa_maintenance_last_run', 'rar_iaa_migration_error', 'rar_iaa_stub_next' ) as $opt ) {
			delete_option( $opt );
		}
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_rar\\_iaa\\_%' OR option_name LIKE '\\_transient\\_timeout\\_rar\\_iaa\\_%'" );
	}
}
