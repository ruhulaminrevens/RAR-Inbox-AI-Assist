<?php
/**
 * wp-admin screens A-01 … A-07 (AppFlow §5). Server-rendered with WordPress core markup.
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

use RarIaa\Auth\TokenService;
use RarIaa\Capabilities;
use RarIaa\Db;
use RarIaa\Domain\DealerRepository;
use RarIaa\Domain\DraftLogRepository;
use RarIaa\Domain\KbRepository;
use RarIaa\Domain\PriceRepository;
use RarIaa\Installer;
use RarIaa\Limits\Budget;
use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class Pages {

	/* ================================================================== Helpers */

	private static function get( $k, $default = '' ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	private static function header( $title, $page = '' ) {
		echo '<div class="wrap iaa-wrap"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		if ( $page ) {
			echo $page; // phpcs:ignore -- pre-escaped action links.
		}
		echo '<hr class="wp-header-end">';
		Notice::render();
	}

	private static function footer() {
		echo '</div>';
	}

	private static function form_open( $action, $multipart = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
	}

	private static function button_form( $action, array $hidden, $label, $class = 'button', $confirm = '' ) {
		self::form_open( $action );
		foreach ( $hidden as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-iaa-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button></form>';
	}

	private static function badge( $status ) {
		$map = array(
			'approved' => 'approved',
			'active'   => 'active',
			'draft'    => 'draft',
			'archived' => 'archived',
			'inactive' => 'archived',
			'revoked'  => 'archived',
			'expired'  => 'archived',
			'pass'     => 'approved',
			'warned'   => 'warned',
			'blocked'  => 'blocked',
			'pending'  => 'draft',
			'ok'       => 'approved',
			'error'    => 'blocked',
		);
		$cls = isset( $map[ $status ] ) ? $map[ $status ] : 'draft';
		return '<span class="iaa-badge iaa-badge--' . esc_attr( $cls ) . '">' . esc_html( $status ) . '</span>';
	}

	private static function field_error( $field ) {
		$e = Notice::field_error( $field );
		if ( $e ) {
			echo '<p class="iaa-field-error" id="iaa-e-' . esc_attr( $field ) . '">' . esc_html( $e ) . '</p>';
		}
	}

	private static function pagination( $total, $per, $page_slug, array $args ) {
		$pages = (int) ceil( $total / $per );
		if ( $pages <= 1 ) {
			return;
		}
		$cur = max( 1, (int) self::get( 'paged', 1 ) );
		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( $total ) . ' items</span> ';
		for ( $i = 1; $i <= min( $pages, 50 ); $i++ ) {
			if ( $i === $cur ) {
				echo '<span class="button disabled">' . esc_html( $i ) . '</span> ';
			} else {
				echo '<a class="button" href="' . esc_url( self::url( $page_slug, array_merge( $args, array( 'paged' => $i ) ) ) ) . '">' . esc_html( $i ) . '</a> ';
			}
		}
		echo '</div></div>';
	}

	private static function user_name( $id ) {
		$u = $id ? get_userdata( (int) $id ) : null;
		return $u ? $u->display_name : '—';
	}

	/* ================================================================== A-01 Overview */

	public static function home() {
		if ( ! current_user_can( Capabilities::VIEW_LOGS ) ) {
			self::tokens();
			return;
		}
		self::header( 'Inbox AI Assist' );
		$today             = Budget::today();
		list( $spent, $reserved ) = Budget::month_totals();
		$k                 = DraftLogRepository::kpis( 7 );
		$total             = max( 1, (int) $k->total );
		$service           = Settings::service_enabled();
		echo '<div class="iaa-cards">';
		self::card( 'Suggestions today', (int) $today->requests . ' / ' . (int) Settings::get( 'daily_limit' ) );
		self::card( 'AI cost this month (estimate)', '$' . number_format( $spent / 1000000, 2 ) . ' / $' . number_format( (float) Settings::get( 'monthly_cap_usd' ), 2 ), $reserved ? 'Reserved now: $' . number_format( $reserved / 1000000, 4 ) : '' );
		self::card( 'Used without edit (7 days)', $k->total ? round( 100 * (int) $k->accepted_unedited / $total ) . '%' : '—', (int) $k->total . ' requests' );
		self::card( 'Handoff (7 days)', $k->total ? round( 100 * (int) $k->handoffs / $total ) . '%' : '—', (int) $k->blocked . ' blocked by guard' );
		echo '</div>';

		echo '<h2>Service</h2><p>Status: ' . ( $service ? self::badge( 'active' ) : '<span class="iaa-badge iaa-badge--blocked">stopped</span>' ) . '</p>';
		if ( defined( 'RAR_IAA_DISABLE' ) && RAR_IAA_DISABLE ) {
			echo '<p class="description">Stopped by the RAR_IAA_DISABLE constant in wp-config.php.</p>';
		} elseif ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			self::button_form( 'rar_iaa_kill_switch', array( 'state' => $service ? 'off' : 'on' ), $service ? 'Stop service (kill switch)' : 'Start service', $service ? 'button iaa-button-danger' : 'button button-primary', $service ? 'All suggestions will stop immediately. Continue?' : '' );
		}

		echo '<h2>Active price lists</h2>';
		$lists = PriceRepository::lists( array( 'status' => 'active' ) );
		if ( ! $lists ) {
			echo '<p>No active price list. Dealer prices will not be quoted.</p>';
		} else {
			echo '<ul>';
			foreach ( $lists as $l ) {
				echo '<li>Tier <strong>' . esc_html( $l->tier_code ) . '</strong>: ' . esc_html( $l->name ) . ' (' . esc_html( $l->row_count ) . ' rows, activated ' . esc_html( Db::display( $l->activated_at ) ) . ')</li>';
			}
			echo '</ul>';
		}
		$expiring = KbRepository::expiring( 7 );
		echo '<h2>Knowledge base</h2><p>' . esc_html( KbRepository::count_serving() ) . ' approved entries in use.';
		if ( $expiring ) {
			echo ' <strong>' . esc_html( count( $expiring ) ) . ' expire within 7 days.</strong>';
		}
		echo '</p>';

		echo '<h2>Health checks</h2><table class="widefat striped iaa-health"><tbody>';
		foreach ( self::health() as $row ) {
			echo '<tr><td>' . esc_html( $row[0] ) . '</td><td>' . ( $row[1] ? '<span class="iaa-ok">✔</span>' : '<span class="iaa-bad">✖</span>' ) . '</td><td>' . esc_html( $row[2] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		self::footer();
	}

	private static function card( $label, $value, $sub = '' ) {
		echo '<div class="iaa-card"><div class="iaa-card__label">' . esc_html( $label ) . '</div><div class="iaa-card__value">' . esc_html( $value ) . '</div>' . ( $sub ? '<div class="iaa-card__sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div>';
	}

	public static function health() {
		global $wp_version;
		$provider = (string) Settings::get( 'provider' );
		$source   = Settings::key_source( $provider );
		$maint    = get_option( 'rar_iaa_maintenance_last_run' );
		$lsc      = defined( 'LSCWP_V' );
		$rows     = array(
			array( 'Provider key (' . $provider . ')', '' !== $source, '' === $source ? 'Not set' : ( 'constant' === $source ? 'From wp-config.php constant' : 'Stored encrypted in the database' ) ),
			array( 'Token prices for budget', (float) Settings::get( 'price_in_usd_per_mtok' ) > 0 && (float) Settings::get( 'price_out_usd_per_mtok' ) > 0, 'Input $' . Settings::get( 'price_in_usd_per_mtok' ) . ' / output $' . Settings::get( 'price_out_usd_per_mtok' ) . ' per million tokens' ),
			array( 'Last test connection', 0 === strpos( (string) Settings::get( 'last_test_result' ), 'OK' ), Settings::get( 'last_test_result' ) ? Settings::get( 'last_test_result' ) . ' (' . Db::display( Settings::get( 'last_test_at' ) ) . ')' : 'Never run' ),
			array( 'Database schema', ! Installer::missing_tables(), 'Version ' . (int) get_option( 'rar_iaa_schema_version' ) . ( get_option( 'rar_iaa_migration_error' ) ? ' — ' . get_option( 'rar_iaa_migration_error' ) : '' ) ),
			array( 'HTTPS', is_ssl() || 0 === strpos( home_url(), 'https://' ), home_url() ),
			array( 'PHP / WordPress', version_compare( PHP_VERSION, '7.4', '>=' ), 'PHP ' . PHP_VERSION . ' · WordPress ' . $wp_version . ( Db::is_sqlite() ? ' · SQLite (development)' : '' ) ),
			array( 'Daily maintenance', is_array( $maint ), is_array( $maint ) ? 'Last run ' . Db::display( $maint['at'] ) : 'Not run yet (WP-Cron)' ),
			array( 'AI concurrency', 1 === Settings::max_concurrent(), Settings::max_concurrent() . ' simultaneous call(s). Keep 1 until the hosting load test (T0.08) passes.' ),
			array( 'Live inbox read/insert', ! Settings::get( 'live_inbox' ), Settings::get( 'live_inbox' ) ? 'ON — make sure Meta permission evidence (T0.09) is recorded' : 'OFF — staff use selected/pasted text and Copy' ),
			array( 'LiteSpeed Cache', true, $lsc ? 'Active: add /wp-json/rar-iaa/ to "Do Not Cache URIs"' : 'Not detected' ),
		);
		return $rows;
	}

	/* ================================================================== A-02 Knowledge base */

	public static function kb() {
		$action = self::get( 'action' );
		if ( 'edit' === $action || 'new' === $action ) {
			self::kb_edit( 'new' === $action ? 0 : (int) self::get( 'id' ) );
			return;
		}
		self::header( 'Knowledge base', ' <a class="page-title-action" href="' . esc_url( self::url( 'rar-iaa-kb', array( 'action' => 'new' ) ) ) . '">Add entry</a>' );
		$args              = array( 'status' => self::get( 'status' ), 'category' => self::get( 'category' ), 's' => self::get( 's' ), 'paged' => self::get( 'paged', 1 ), 'orderby' => self::get( 'orderby' ), 'pending' => self::get( 'pending' ) );
		list( $rows, $cnt ) = KbRepository::search_admin( $args );
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="rar-iaa-kb">';
		echo '<select name="status" aria-label="Filter by status"><option value="">All statuses</option>';
		foreach ( array( 'draft', 'approved', 'archived' ) as $s ) {
			echo '<option value="' . esc_attr( $s ) . '"' . selected( $args['status'], $s, false ) . '>' . esc_html( $s ) . '</option>';
		}
		echo '</select> <select name="category" aria-label="Filter by category"><option value="">All categories</option>';
		foreach ( KbRepository::category_labels() as $c => $l ) {
			echo '<option value="' . esc_attr( $c ) . '"' . selected( $args['category'], $c, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <label><input type="checkbox" name="pending" value="1"' . checked( $args['pending'], '1', false ) . '> Waiting for approval</label> ';
		echo '<input type="search" name="s" value="' . esc_attr( $args['s'] ) . '" placeholder="Search" aria-label="Search knowledge base"> <button class="button">Filter</button></form>';
		echo '<table class="widefat striped"><thead><tr><th><a href="' . esc_url( self::url( 'rar-iaa-kb', array( 'orderby' => 'title' ) ) ) . '">Title</a></th><th>Category</th><th>Status</th><th>Effective</th><th>Last edited by</th><th>Approved by</th><th>Updated</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">No entries yet. Add office hours, contact numbers, ordering, delivery and payment terms first.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$title = $r->title ? $r->title : '(untitled)';
			echo '<tr><td><a href="' . esc_url( self::url( 'rar-iaa-kb', array( 'action' => 'edit', 'id' => $r->id ) ) ) . '"><strong>' . esc_html( $title ) . '</strong></a>' . ( $r->pending_review ? ' <span class="iaa-badge iaa-badge--warned">waiting for approval</span>' : '' ) . ( $r->draft_version_id && $r->approved_version_id ? ' <span class="iaa-badge iaa-badge--draft">new draft</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $r->category ) . '</td><td>' . self::badge( $r->status ) . '</td>'; // phpcs:ignore
			echo '<td>' . esc_html( $r->effective_from . ( $r->effective_to ? ' → ' . $r->effective_to : '' ) ) . '</td>';
			echo '<td>' . esc_html( self::user_name( $r->edited_by ) ) . '</td><td>' . esc_html( self::user_name( $r->approved_by ) ) . '</td><td>' . esc_html( Db::display( $r->updated_at ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		self::pagination( $cnt, 20, 'rar-iaa-kb', array_filter( $args ) );
		self::footer();
	}

	private static function kb_edit( $id ) {
		$entry = $id ? KbRepository::get( $id ) : null;
		if ( $id && ! $entry ) {
			wp_die( 'Entry not found.' );
		}
		$v      = $entry ? KbRepository::current_version( $entry ) : null;
		$val    = static function ( $field, $default = '' ) use ( $v, $entry ) {
			if ( Notice::has_old() ) {
				return Notice::old( $field, $default );
			}
			if ( 'category' === $field && $entry ) {
				return $entry->category;
			}
			return $v && isset( $v->$field ) && null !== $v->$field ? $v->$field : $default;
		};
		self::header( $entry ? 'Edit knowledge base entry' : 'New knowledge base entry', ' <a class="page-title-action" href="' . esc_url( self::url( 'rar-iaa-kb' ) ) . '">Back to list</a>' );
		if ( $entry ) {
			echo '<p>Status: ' . self::badge( $entry->status ) . ( $entry->draft_version_id ? ' — editing draft version ' . esc_html( $v->version_no ) : '' ) . ( $entry->approved_version_id && $entry->draft_version_id ? ' (the approved version keeps serving until this draft is approved)' : '' ) . '</p>'; // phpcs:ignore
		}
		if ( ! $entry || 'archived' !== $entry->status ) {
			self::form_open( 'rar_iaa_kb_save' );
			echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="row_version" value="' . esc_attr( $entry ? $entry->row_version : 0 ) . '">';
			echo '<table class="form-table"><tbody>';
			echo '<tr><th><label for="iaa-f-title">Title *</label></th><td><input class="regular-text" id="iaa-f-title" name="title" maxlength="120" required value="' . esc_attr( $val( 'title' ) ) . '">';
			self::field_error( 'title' );
			echo '</td></tr><tr><th><label for="iaa-f-category">Category *</label></th><td><select id="iaa-f-category" name="category">';
			foreach ( KbRepository::category_labels() as $c => $l ) {
				echo '<option value="' . esc_attr( $c ) . '"' . selected( $val( 'category', 'other' ), $c, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
			self::field_error( 'category' );
			echo '</td></tr><tr><th><label for="iaa-f-keywords">Keywords</label></th><td><input class="large-text" id="iaa-f-keywords" name="keywords" value="' . esc_attr( str_replace( ',', ', ', $val( 'keywords' ) ) ) . '"><p class="description">Comma-separated words customers use (Bangla, Banglish, English), e.g. <code>khola, koyta, সময়, office hours</code>.</p>';
			self::field_error( 'keywords' );
			echo '</td></tr><tr><th><label for="iaa-f-answer_bn">Answer (Bangla) *</label></th><td><textarea class="large-text" rows="4" id="iaa-f-answer_bn" name="answer_bn" maxlength="2000" lang="bn" required>' . esc_textarea( $val( 'answer_bn' ) ) . '</textarea><p class="description">Write the exact facts (times, numbers, prices) the AI may use. The AI will not use anything that is not here.</p>';
			self::field_error( 'answer_bn' );
			echo '</td></tr><tr><th><label for="iaa-f-answer_en">Answer (English)</label></th><td><textarea class="large-text" rows="3" id="iaa-f-answer_en" name="answer_en" maxlength="2000">' . esc_textarea( $val( 'answer_en' ) ) . '</textarea>';
			self::field_error( 'answer_en' );
			echo '</td></tr><tr><th><label for="iaa-f-effective_from">Effective from *</label></th><td><input type="date" id="iaa-f-effective_from" name="effective_from" value="' . esc_attr( $val( 'effective_from', Db::dhaka_date() ) ) . '"> to <input type="date" id="iaa-f-effective_to" name="effective_to" value="' . esc_attr( $val( 'effective_to' ) ) . '"> <span class="description">(leave "to" empty for no end)</span>';
			self::field_error( 'effective_from' );
			self::field_error( 'effective_to' );
			echo '</td></tr><tr><th><label for="iaa-f-note">Internal note</label></th><td><textarea class="large-text" rows="2" id="iaa-f-note" name="note" maxlength="500">' . esc_textarea( $val( 'note' ) ) . '</textarea><p class="description">Never sent to the AI.</p></td></tr>';
			echo '</tbody></table><p class="submit"><button class="button" name="save" value="1">Save draft</button> <button class="button button-primary" name="submit_review" value="1">Save &amp; submit for approval</button></p></form>';
		}
		if ( $entry ) {
			echo '<div class="iaa-actions-row">';
			if ( $entry->draft_version_id && current_user_can( Capabilities::APPROVE_KB ) ) {
				$self_edit = (int) $v->edited_by === get_current_user_id() && Capabilities::admin_count() > 1;
				if ( $self_edit ) {
					echo '<p class="description">You edited this version, so another approver must approve it.</p>';
				} else {
					self::button_form( 'rar_iaa_kb_approve', array( 'id' => $entry->id, 'row_version' => $entry->row_version ), 'Approve this version', 'button button-primary' );
				}
			}
			if ( 'archived' !== $entry->status ) {
				self::button_form( 'rar_iaa_kb_archive', array( 'id' => $entry->id ), 'Archive', 'button iaa-button-danger', 'Archive this entry? It stops being used immediately.' );
			} else {
				self::button_form( 'rar_iaa_kb_restore', array( 'id' => $entry->id ), 'Restore as new draft', 'button' );
			}
			echo '</div><h2>Version history</h2><table class="widefat striped"><thead><tr><th>Version</th><th>State</th><th>Title</th><th>Edited</th><th>Approved</th></tr></thead><tbody>';
			foreach ( KbRepository::versions( $entry->id ) as $ver ) {
				echo '<tr><td>' . esc_html( $ver->version_no ) . '</td><td>' . self::badge( $ver->state ) . '</td><td>' . esc_html( $ver->title ) . '</td><td>' . esc_html( self::user_name( $ver->edited_by ) . ', ' . Db::display( $ver->edited_at ) ) . '</td><td>' . esc_html( $ver->approved_by ? self::user_name( $ver->approved_by ) . ', ' . Db::display( $ver->approved_at ) : '—' ) . '</td></tr>'; // phpcs:ignore
			}
			echo '</tbody></table>';
		}
		self::footer();
	}

	/* ================================================================== A-03 Dealers & tiers */

	public static function dealers() {
		$tab = 'tiers' === self::get( 'tab' ) ? 'tiers' : 'dealers';
		self::header( 'Dealers & tiers' );
		echo '<nav class="nav-tab-wrapper"><a class="nav-tab' . ( 'dealers' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( 'rar-iaa-dealers' ) ) . '">Dealers</a><a class="nav-tab' . ( 'tiers' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( 'rar-iaa-dealers', array( 'tab' => 'tiers' ) ) ) . '">Tiers</a></nav>';
		$tiers = DealerRepository::tiers();
		if ( 'tiers' === $tab ) {
			echo '<p>A tier is a price group. Each dealer belongs to one tier; each tier has one active price list.</p>';
			echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Edit</th></tr></thead><tbody>';
			foreach ( $tiers as $t ) {
				echo '<tr><td><strong>' . esc_html( $t->code ) . '</strong></td><td colspan="3">';
				self::form_open( 'rar_iaa_tier_save' );
				echo '<input type="hidden" name="id" value="' . esc_attr( $t->id ) . '"><input type="hidden" name="row_version" value="' . esc_attr( $t->row_version ) . '"><input name="code" value="' . esc_attr( $t->code ) . '" size="8" aria-label="Code"> <input name="name" value="' . esc_attr( $t->name ) . '" aria-label="Name"> <select name="status" aria-label="Status"><option value="active"' . selected( $t->status, 'active', false ) . '>active</option><option value="inactive"' . selected( $t->status, 'inactive', false ) . '>inactive</option></select> <button class="button">Save</button></form></td></tr>';
			}
			if ( ! $tiers ) {
				echo '<tr><td colspan="4">No tiers yet.</td></tr>';
			}
			echo '</tbody></table><h2>Add tier</h2>';
			self::form_open( 'rar_iaa_tier_save' );
			echo '<input name="code" placeholder="Code e.g. A" size="8" required value="' . esc_attr( Notice::old( 'code' ) ) . '" id="iaa-f-code"> <input name="name" placeholder="Name e.g. Distributor" required value="' . esc_attr( Notice::old( 'name' ) ) . '" id="iaa-f-name"> <button class="button button-primary">Add tier</button></form>';
			self::footer();
			return;
		}
		$edit = self::get( 'edit' );
		if ( '' !== $edit ) {
			$d   = 'new' === $edit ? null : DealerRepository::dealer( (int) $edit );
			$val = static function ( $f, $def = '' ) use ( $d ) {
				if ( Notice::has_old() ) {
					return Notice::old( $f, $def );
				}
				return $d && null !== $d->$f ? $d->$f : $def;
			};
			echo '<h2>' . ( $d ? 'Edit dealer' : 'Add dealer' ) . '</h2>';
			self::form_open( 'rar_iaa_dealer_save' );
			echo '<input type="hidden" name="id" value="' . esc_attr( $d ? $d->id : 0 ) . '"><input type="hidden" name="row_version" value="' . esc_attr( $d ? $d->row_version : 0 ) . '"><table class="form-table"><tbody>';
			echo '<tr><th><label for="iaa-f-dealer_code">Dealer code *</label></th><td><input id="iaa-f-dealer_code" name="dealer_code" required value="' . esc_attr( $val( 'dealer_code' ) ) . '">';
			self::field_error( 'dealer_code' );
			echo '</td></tr><tr><th><label for="iaa-f-name">Name *</label></th><td><input class="regular-text" id="iaa-f-name" name="name" required value="' . esc_attr( $val( 'name' ) ) . '">';
			self::field_error( 'name' );
			echo '</td></tr><tr><th><label for="iaa-f-tier_id">Tier *</label></th><td><select id="iaa-f-tier_id" name="tier_id"><option value="">—</option>';
			foreach ( $tiers as $t ) {
				if ( 'active' === $t->status ) {
					echo '<option value="' . esc_attr( $t->id ) . '"' . selected( (int) $val( 'tier_id' ), (int) $t->id, false ) . '>' . esc_html( $t->code . ' — ' . $t->name ) . '</option>';
				}
			}
			echo '</select>';
			self::field_error( 'tier_id' );
			echo '</td></tr><tr><th><label for="iaa-f-district_code">District code</label></th><td><input id="iaa-f-district_code" name="district_code" placeholder="BD-13" value="' . esc_attr( $val( 'district_code' ) ) . '">';
			self::field_error( 'district_code' );
			echo '</td></tr><tr><th><label for="iaa-f-status">Status</label></th><td><select id="iaa-f-status" name="status"><option value="active"' . selected( $val( 'status', 'active' ), 'active', false ) . '>active</option><option value="inactive"' . selected( $val( 'status' ), 'inactive', false ) . '>inactive</option></select><p class="description">An inactive dealer is treated as unverified: no dealer prices.</p></td></tr>';
			echo '<tr><th><label for="iaa-f-note">Internal note</label></th><td><textarea id="iaa-f-note" name="note" class="large-text" rows="2" maxlength="500">' . esc_textarea( $val( 'note' ) ) . '</textarea><p class="description">Never sent to the AI. Do not store phone numbers or addresses here.</p></td></tr>';
			echo '</tbody></table><p class="submit"><button class="button button-primary">Save dealer</button> <a class="button" href="' . esc_url( self::url( 'rar-iaa-dealers' ) ) . '">Cancel</a></p></form>';
			self::footer();
			return;
		}
		$args               = array( 's' => self::get( 's' ), 'tier_id' => self::get( 'tier_id' ), 'status' => self::get( 'status' ), 'paged' => self::get( 'paged', 1 ) );
		list( $rows, $cnt ) = DealerRepository::list_admin( $args );
		echo '<p><a class="button button-primary" href="' . esc_url( self::url( 'rar-iaa-dealers', array( 'edit' => 'new' ) ) ) . '">Add dealer</a></p>';
		if ( ! $tiers ) {
			echo '<div class="notice notice-info inline"><p>Create a tier first (Tiers tab).</p></div>';
		}
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="rar-iaa-dealers"><select name="tier_id" aria-label="Filter by tier"><option value="">All tiers</option>';
		foreach ( $tiers as $t ) {
			echo '<option value="' . esc_attr( $t->id ) . '"' . selected( $args['tier_id'], (string) $t->id, false ) . '>' . esc_html( $t->code ) . '</option>';
		}
		echo '</select> <select name="status" aria-label="Filter by status"><option value="">All</option><option value="active"' . selected( $args['status'], 'active', false ) . '>active</option><option value="inactive"' . selected( $args['status'], 'inactive', false ) . '>inactive</option></select> <input type="search" name="s" value="' . esc_attr( $args['s'] ) . '" placeholder="Code or name" aria-label="Search dealers"> <button class="button">Filter</button></form>';
		echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Name</th><th>Tier</th><th>District</th><th>Status</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No dealers.</td></tr>';
		}
		foreach ( $rows as $d ) {
			echo '<tr><td><a href="' . esc_url( self::url( 'rar-iaa-dealers', array( 'edit' => $d->id ) ) ) . '"><strong>' . esc_html( $d->dealer_code ) . '</strong></a></td><td>' . esc_html( $d->name ) . '</td><td>' . esc_html( $d->tier_code ) . '</td><td>' . esc_html( $d->district_code ) . '</td><td>' . self::badge( $d->status ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
		self::pagination( $cnt, 20, 'rar-iaa-dealers', array_filter( $args ) );
		self::footer();
	}

	/* ================================================================== A-04 Price lists */

	public static function prices() {
		$action = self::get( 'action' );
		if ( 'import' === $action ) {
			self::price_import();
			return;
		}
		if ( 'view' === $action ) {
			self::price_view( (int) self::get( 'id' ) );
			return;
		}
		self::header( 'Price lists', ' <a class="page-title-action" href="' . esc_url( self::url( 'rar-iaa-prices', array( 'action' => 'import' ) ) ) . '">Import CSV</a>' );
		echo '<p>Dealer prices are quoted only for conversations linked to an active dealer, from the active list of the dealer\'s tier.</p>';
		$lists = PriceRepository::lists( array( 'tier_id' => self::get( 'tier_id' ), 'status' => self::get( 'status' ) ) );
		echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Tier</th><th>Status</th><th>Effective from</th><th>Rows</th><th>Imported</th><th>Activated</th></tr></thead><tbody>';
		if ( ! $lists ) {
			echo '<tr><td colspan="7">No price lists yet.</td></tr>';
		}
		foreach ( $lists as $l ) {
			echo '<tr><td><a href="' . esc_url( self::url( 'rar-iaa-prices', array( 'action' => 'view', 'id' => $l->id ) ) ) . '"><strong>' . esc_html( $l->name ) . '</strong></a></td><td>' . esc_html( $l->tier_code ) . '</td><td>' . self::badge( $l->status ) . '</td><td>' . esc_html( $l->effective_from ) . '</td><td>' . esc_html( $l->row_count ) . '</td><td>' . esc_html( self::user_name( $l->imported_by ) . ', ' . Db::display( $l->imported_at ) ) . '</td><td>' . esc_html( $l->activated_at ? Db::display( $l->activated_at ) : '—' ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
		self::footer();
	}

	private static function price_import() {
		self::header( 'Import price list', ' <a class="page-title-action" href="' . esc_url( self::url( 'rar-iaa-prices' ) ) . '">Back</a>' );
		$report = get_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
			self::import_report( $report );
		}
		$tiers = DealerRepository::tiers( true );
		if ( ! $tiers ) {
			echo '<div class="notice notice-warning inline"><p>Create an active tier first.</p></div>';
		}
		self::form_open( 'rar_iaa_price_import', true );
		echo '<table class="form-table"><tbody><tr><th><label for="iaa-f-tier">Tier *</label></th><td><select id="iaa-f-tier" name="tier_id" required>';
		foreach ( $tiers as $t ) {
			echo '<option value="' . esc_attr( $t->id ) . '"' . selected( (int) Notice::old( 'tier_id' ), (int) $t->id, false ) . '>' . esc_html( $t->code . ' — ' . $t->name ) . '</option>';
		}
		echo '</select></td></tr><tr><th><label for="iaa-f-lname">List name *</label></th><td><input class="regular-text" id="iaa-f-lname" name="name" required maxlength="150" value="' . esc_attr( Notice::old( 'name' ) ) . '" placeholder="Dealer list November 2026"></td></tr>';
		echo '<tr><th><label for="iaa-f-eff">Effective from *</label></th><td><input type="date" id="iaa-f-eff" name="effective_from" required value="' . esc_attr( Notice::old( 'effective_from', Db::dhaka_date() ) ) . '"></td></tr>';
		echo '<tr><th><label for="iaa-f-csv">CSV file *</label></th><td><input type="file" id="iaa-f-csv" name="csv" accept=".csv,text/csv" required><p class="description">UTF-8 CSV, max 2 MB, max 5,000 rows. Required columns: <code>item_code, product_name, unit, price_bdt</code>. Optional: <code>aliases</code> (separate with |), <code>pack_size, mrp_bdt, wc_product_id, stock_mode</code> (wc / manual / hidden), <code>manual_stock_status</code> (in_stock / limited / out_of_stock). Bangla digits and ৳ are accepted. In Excel use "Save As → CSV UTF-8".</p></td></tr>';
		echo '</tbody></table><p class="submit"><button class="button button-primary">Validate &amp; save as draft</button></p></form>';
		echo '<h2>Example</h2><pre class="iaa-pre">item_code,product_name,unit,price_bdt,aliases,pack_size,stock_mode,manual_stock_status' . "\n" . 'ATZ-JMB,A TO Z Jumbo,carton,1250,জাম্বো|jumbo coil,12 x 10,manual,in_stock</pre>';
		self::footer();
	}

	private static function import_report( $report ) {
		if ( ! empty( $report['errors'] ) ) {
			echo '<h2>Errors (nothing saved)</h2><table class="widefat striped"><thead><tr><th>Row</th><th>Column</th><th>Problem</th></tr></thead><tbody>';
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

	private static function price_view( $id ) {
		$list = PriceRepository::get_list( $id );
		if ( ! $list ) {
			wp_die( 'Price list not found.' );
		}
		self::header( 'Price list: ' . $list->name, ' <a class="page-title-action" href="' . esc_url( self::url( 'rar-iaa-prices' ) ) . '">Back</a>' );
		$report = get_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'rar_iaa_import_errors_' . get_current_user_id() );
			self::import_report( $report );
		}
		$active = PriceRepository::active_for_tier( (int) $list->tier_id );
		echo '<p>Tier <strong>' . esc_html( $list->tier_code ) . '</strong> · ' . self::badge( $list->status ) . ' · ' . esc_html( $list->row_count ) . ' rows · effective ' . esc_html( $list->effective_from ) . '</p>'; // phpcs:ignore
		echo '<div class="iaa-actions-row">';
		if ( 'draft' === $list->status && current_user_can( Capabilities::ACTIVATE_PRICES ) ) {
			$msg = $active ? sprintf( 'Activate "%s"? The current active list "%s" will be archived.', $list->name, $active->name ) : sprintf( 'Activate "%s" for tier %s?', $list->name, $list->tier_code );
			self::button_form( 'rar_iaa_price_activate', array( 'id' => $list->id ), 'Activate', 'button button-primary', $msg );
		}
		if ( 'archived' === $list->status && $active && (int) $active->previous_list_id === (int) $list->id && current_user_can( Capabilities::ACTIVATE_PRICES ) ) {
			self::button_form( 'rar_iaa_price_reactivate', array( 'id' => $list->id ), 'Re-activate previous', 'button', sprintf( 'Re-activate "%s"? The current list "%s" will be archived.', $list->name, $active->name ) );
		}
		if ( 'draft' === $list->status ) {
			self::button_form( 'rar_iaa_price_archive', array( 'id' => $list->id ), 'Archive draft', 'button iaa-button-danger', 'Archive this draft?' );
		}
		echo '</div>';
		$s = self::get( 's' );
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="rar-iaa-prices"><input type="hidden" name="action" value="view"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="Search items" aria-label="Search items"> <button class="button">Search</button></form>';
		echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Product</th><th>Aliases</th><th>Unit</th><th>Pack</th><th>Price (৳)</th><th>MRP (৳)</th><th>Stock</th></tr></thead><tbody>';
		foreach ( PriceRepository::items( $id, $s ) as $i ) {
			$stock = 'wc' === $i->stock_mode ? 'WooCommerce #' . $i->wc_product_id : ( 'manual' === $i->stock_mode ? 'manual: ' . $i->manual_stock_status : 'hidden' );
			echo '<tr><td>' . esc_html( $i->item_code ) . '</td><td>' . esc_html( $i->product_name ) . '</td><td>' . esc_html( str_replace( '|', ', ', $i->aliases ) ) . '</td><td>' . esc_html( $i->unit ) . '</td><td>' . esc_html( $i->pack_size ) . '</td><td class="num">' . esc_html( Util::bd_group( $i->price_bdt ) ) . '</td><td class="num">' . esc_html( null === $i->mrp_bdt ? '—' : Util::bd_group( $i->mrp_bdt ) ) . '</td><td>' . esc_html( $stock ) . '</td></tr>';
		}
		echo '</tbody></table>';
		self::footer();
	}

	/* ================================================================== A-05 Tokens */

	public static function tokens() {
		$uid     = get_current_user_id();
		$created = null;
		$error   = null;
		// Created in this request and shown once; never stored in plain text.
		if ( isset( $_POST['iaa_create_token'] ) ) { // phpcs:ignore
			check_admin_referer( 'rar_iaa_token_create' );
			if ( current_user_can( Capabilities::USE ) ) {
				$res = TokenService::create( $uid, isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '', isset( $_POST['days'] ) ? (int) $_POST['days'] : 180 );
				if ( is_wp_error( $res ) ) {
					$error = $res->get_error_message();
				} else {
					$created = $res['token'];
				}
			}
		}
		self::header( 'Access tokens' );
		if ( $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		if ( $created ) {
			echo '<div class="notice notice-success iaa-token-once"><p><strong>Copy this token now. It will not be shown again.</strong></p><p><input type="text" readonly class="large-text code" id="iaa-new-token" value="' . esc_attr( $created ) . '"> <button type="button" class="button" data-iaa-copy="#iaa-new-token">Copy</button></p><p>Paste it into the Chrome extension: side panel → সংযোগ করুন.</p></div>';
		}
		echo '<h2>Create a token for this computer</h2><form method="post" action="' . esc_url( self::url( 'rar-iaa-tokens' ) ) . '">';
		wp_nonce_field( 'rar_iaa_token_create' );
		echo '<input type="hidden" name="iaa_create_token" value="1"><label>Label <input name="label" maxlength="60" required placeholder="Office PC 2"></label> <label>Expires in <select name="days"><option value="30">30 days</option><option value="90">90 days</option><option value="180" selected>180 days</option></select></label> <button class="button button-primary">Create token</button></form>';
		echo '<p class="description">Each person needs their own WordPress account and token. Maximum 3 active tokens per person. Revoke a token immediately if a computer is lost.</p>';
		$all  = current_user_can( Capabilities::MANAGE_SETTINGS );
		$rows = TokenService::list_for( $all ? null : $uid );
		echo '<h2>' . ( $all ? 'All tokens' : 'My tokens' ) . '</h2><table class="widefat striped"><thead><tr>' . ( $all ? '<th>User</th>' : '' ) . '<th>Label</th><th>Prefix</th><th>Created</th><th>Last used</th><th>Expires</th><th>Status</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">No tokens yet.</td></tr>';
		}
		foreach ( $rows as $t ) {
			$status = TokenService::status_of( $t );
			echo '<tr>' . ( $all ? '<td>' . esc_html( self::user_name( $t->user_id ) ) . '</td>' : '' ) . '<td>' . esc_html( $t->label ) . '</td><td><code>iaa_' . esc_html( $t->token_prefix ) . '_…</code></td><td>' . esc_html( Db::display( $t->created_at ) ) . '</td><td>' . esc_html( Db::display( $t->last_used_at ) ) . '</td><td>' . esc_html( Db::display( $t->expires_at ) ) . '</td><td>' . self::badge( $status ) . '</td><td>'; // phpcs:ignore
			if ( 'active' === $status ) {
				self::button_form( 'rar_iaa_token_revoke', array( 'id' => $t->id ), 'Revoke', 'button button-small iaa-button-danger', 'Revoke this token?' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		self::footer();
	}

	/* ================================================================== A-06 Logs */

	public static function logs() {
		$tab = 'audit' === self::get( 'tab' ) ? 'audit' : 'drafts';
		self::header( 'Logs' );
		echo '<nav class="nav-tab-wrapper"><a class="nav-tab' . ( 'drafts' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( 'rar-iaa-logs' ) ) . '">Suggestions</a><a class="nav-tab' . ( 'audit' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( 'rar-iaa-logs', array( 'tab' => 'audit' ) ) ) . '">Audit</a></nav>';
		if ( 'audit' === $tab ) {
			self::audit_tab();
			self::footer();
			return;
		}
		$args = array(
			'from'    => self::get( 'from', Db::dhaka_date() ),
			'to'      => self::get( 'to', Db::dhaka_date() ),
			'outcome' => self::get( 'outcome' ),
			'guard'   => self::get( 'guard' ),
			'handoff' => self::get( 'handoff' ),
			'paged'   => self::get( 'paged', 1 ),
		);
		list( $rows, $cnt ) = DraftLogRepository::list_admin( $args );
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="rar-iaa-logs"><label>From <input type="date" name="from" value="' . esc_attr( $args['from'] ) . '"></label> <label>To <input type="date" name="to" value="' . esc_attr( $args['to'] ) . '"></label> <select name="outcome" aria-label="Filter by outcome"><option value="">Any outcome</option>';
		foreach ( array_merge( array( 'pending' ), DraftLogRepository::OUTCOMES ) as $o ) {
			echo '<option' . selected( $args['outcome'], $o, false ) . '>' . esc_html( $o ) . '</option>';
		}
		echo '</select> <select name="guard" aria-label="Filter by guard result"><option value="">Any guard</option>';
		foreach ( array( 'pass', 'warned', 'blocked' ) as $g ) {
			echo '<option' . selected( $args['guard'], $g, false ) . '>' . esc_html( $g ) . '</option>';
		}
		echo '</select> <select name="handoff" aria-label="Filter by handoff"><option value="">Handoff any</option><option value="1"' . selected( $args['handoff'], '1', false ) . '>Handoff yes</option><option value="0"' . selected( $args['handoff'], '0', false ) . '>Handoff no</option></select> <button class="button">Filter</button></form>';
		self::form_open( 'rar_iaa_logs_export' );
		echo '<input type="hidden" name="from" value="' . esc_attr( $args['from'] ) . '"><input type="hidden" name="to" value="' . esc_attr( $args['to'] ) . '"><button class="button">Export CSV</button></form>';
		echo '<table class="widefat striped iaa-log"><thead><tr><th>Time</th><th>User</th><th>Dealer</th><th>Source</th><th>Lang</th><th>Status</th><th>Guard</th><th>Handoff</th><th>Selected</th><th>Outcome</th><th>ms</th><th>Cost $</th><th>Request ID</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="13">No requests in this period.</td></tr>';
		}
		$masked = (bool) Settings::get( 'masked_logging' );
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( Db::display( $r->created_at ) ) . '</td><td>' . esc_html( $r->display_name ) . '</td><td>' . esc_html( $r->dealer_code ? $r->dealer_code : '—' ) . '</td><td>' . esc_html( $r->source . ( $r->rewrite_mode ? ':' . $r->rewrite_mode : '' ) ) . '</td><td>' . esc_html( $r->language ) . '</td><td>' . self::badge( $r->status ) . ( $r->error_code ? ' <code>' . esc_html( $r->error_code ) . '</code>' : '' ) . '</td><td>' . ( $r->guard_result ? self::badge( $r->guard_result ) . ' ' . esc_html( $r->warnings_count ) : '—' ) . '</td><td>' . esc_html( $r->handoff_needed ? $r->handoff_reason : '—' ) . '</td><td>' . esc_html( $r->selected_variant_id ? $r->selected_variant_id : '—' ) . '</td><td>' . self::badge( $r->outcome ) . ( $r->reject_reason ? ' ' . esc_html( $r->reject_reason ) : '' ) . '</td><td class="num">' . esc_html( $r->latency_ms ) . '</td><td class="num">' . esc_html( number_format( $r->cost_usd_micros / 1000000, 4 ) ) . '</td><td><code>' . esc_html( substr( $r->request_id, 0, 8 ) ) . '</code></td></tr>'; // phpcs:ignore
			if ( $masked && ( $r->input_masked || $r->reply_masked ) ) {
				echo '<tr class="iaa-log-detail"><td colspan="13"><details><summary>Masked text</summary><pre class="iaa-pre">' . esc_html( (string) $r->input_masked ) . "\n\n" . esc_html( (string) $r->reply_masked ) . '</pre></details></td></tr>';
			}
		}
		echo '</tbody></table>';
		self::pagination( $cnt, 50, 'rar-iaa-logs', array_filter( $args ) );
	}

	private static function audit_tab() {
		global $wpdb;
		$page = max( 1, (int) self::get( 'paged', 1 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, u.display_name FROM ' . Db::t( 'audit_log' ) . ' a LEFT JOIN ' . $wpdb->users . ' u ON u.ID = a.user_id ORDER BY a.id DESC LIMIT 50 OFFSET %d', ( $page - 1 ) * 50 ) );
		$cnt  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Db::t( 'audit_log' ) );
		echo '<table class="widefat striped"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Object</th><th>Change</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( Db::display( $r->created_at ) ) . '</td><td>' . esc_html( $r->display_name ) . '</td><td><code>' . esc_html( $r->action ) . '</code></td><td>' . esc_html( $r->object_type . ( $r->object_id ? ' #' . $r->object_id : '' ) ) . '</td><td><details><summary>View</summary><pre class="iaa-pre">' . esc_html( 'Before: ' . (string) $r->before_json . "\nAfter: " . (string) $r->after_json ) . '</pre></details></td></tr>';
		}
		echo '</tbody></table>';
		self::pagination( $cnt, 50, 'rar-iaa-logs', array( 'tab' => 'audit' ) );
	}

	/* ================================================================== A-07 Settings */

	public static function settings() {
		self::header( 'Settings' );
		$s   = Settings::all();
		$val = static function ( $k ) use ( $s ) {
			return Notice::has_old() ? Notice::old( $k, $s[ $k ] ) : $s[ $k ];
		};
		$num = static function ( $k, $label, $step = '1', $help = '' ) use ( $val ) {
			echo '<tr><th><label for="iaa-f-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" step="' . esc_attr( $step ) . '" id="iaa-f-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $val( $k ) ) . '" class="small-text">' . ( $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' );
			$e = Notice::field_error( $k );
			if ( $e ) {
				echo '<p class="iaa-field-error">' . esc_html( $e ) . '</p>';
			}
			echo '</td></tr>';
		};
		$chk = static function ( $k, $label, $help = '' ) use ( $val ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $val( $k ), 1, false ) . '> ' . esc_html( $help ) . '</label></td></tr>';
		};
		self::form_open( 'rar_iaa_settings_save' );
		echo '<h2>AI provider</h2><table class="form-table"><tbody>';
		echo '<tr><th><label for="iaa-f-provider">Provider</label></th><td><select id="iaa-f-provider" name="provider"><option value="anthropic"' . selected( $val( 'provider' ), 'anthropic', false ) . '>Anthropic (Claude)</option><option value="openai"' . selected( $val( 'provider' ), 'openai', false ) . '>OpenAI</option></select></td></tr>';
		echo '<tr><th><label for="iaa-f-anthropic_model">Anthropic model</label></th><td><input id="iaa-f-anthropic_model" name="anthropic_model" value="' . esc_attr( $val( 'anthropic_model' ) ) . '" class="regular-text"></td></tr>';
		echo '<tr><th><label for="iaa-f-openai_model">OpenAI model</label></th><td><input id="iaa-f-openai_model" name="openai_model" value="' . esc_attr( $val( 'openai_model' ) ) . '" class="regular-text" placeholder="model id"></td></tr>';
		foreach ( array( 'anthropic' => 'Anthropic API key', 'openai' => 'OpenAI API key' ) as $p => $label ) {
			$src = Settings::key_source( $p );
			echo '<tr><th><label for="iaa-f-key_' . esc_attr( $p ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'constant' === $src ) {
				echo '<p>Set in wp-config.php (<code>RAR_IAA_' . esc_html( strtoupper( $p ) ) . '_API_KEY</code>): <code>' . esc_html( Settings::masked_key( $p ) ) . '</code></p>';
			} else {
				echo '<input type="password" autocomplete="new-password" id="iaa-f-key_' . esc_attr( $p ) . '" name="key_' . esc_attr( $p ) . '" class="regular-text" placeholder="' . esc_attr( $src ? 'Saved: ' . Settings::masked_key( $p ) . ' — type to replace' : 'Not set' ) . '">';
				if ( $src ) {
					echo ' <label><input type="checkbox" name="clear_key_' . esc_attr( $p ) . '" value="1"> Remove saved key</label>';
				}
				echo '<p class="description">Recommended: put <code>define( \'RAR_IAA_' . esc_html( strtoupper( $p ) ) . '_API_KEY\', \'…\' );</code> in wp-config.php instead. A key typed here is stored encrypted.</p>';
			}
			echo '</td></tr>';
		}
		$num( 'price_in_usd_per_mtok', 'Input price (USD per million tokens)', '0.0001', 'From the provider\'s pricing page for the chosen model. Needed for the budget limit.' );
		$num( 'price_out_usd_per_mtok', 'Output price (USD per million tokens)', '0.0001' );
		$num( 'max_output_tokens', 'Max output tokens per call', '1', '300–4000. Three Bangla suggestions need about 1,200–1,600.' );
		echo '<tr><th><label for="iaa-f-suggestion_count">Suggestions per click</label></th><td><select id="iaa-f-suggestion_count" name="suggestion_count"><option value="3"' . selected( (int) $val( 'suggestion_count' ), 3, false ) . '>3 (Professional, Friendly, Short)</option><option value="4"' . selected( (int) $val( 'suggestion_count' ), 4, false ) . '>4 (+ Sales-focused)</option></select></td></tr>';
		echo '</tbody></table>';

		echo '<h2>Limits</h2><table class="form-table"><tbody>';
		$num( 'per_minute', 'Requests per person per minute' );
		$num( 'daily_limit', 'Requests per day (site)' );
		$num( 'monthly_cap_usd', 'Monthly AI budget (USD)', '0.01', 'Hard stop: worst-case cost is reserved before each call.' );
		$num( 'max_concurrent', 'Simultaneous AI calls', '1', 'Keep 1 on shared hosting until the load test passes.' );
		echo '</tbody></table>';

		echo '<h2>Replies</h2><table class="form-table"><tbody>';
		$chk( 'ai_enabled', 'AI suggestions', 'On (when off, staff can still use Quick answers)' );
		echo '<tr><th><label for="iaa-f-business_name">Business name</label></th><td><input id="iaa-f-business_name" name="business_name" class="regular-text" value="' . esc_attr( $val( 'business_name' ) ) . '"></td></tr>';
		echo '<tr><th>Banglish messages</th><td><label><input type="radio" name="banglish_reply_script" value="bn"' . checked( $val( 'banglish_reply_script' ), 'bn', false ) . '> Reply in Bangla script</label> <label><input type="radio" name="banglish_reply_script" value="latin"' . checked( $val( 'banglish_reply_script' ), 'latin', false ) . '> Reply in Banglish</label></td></tr>';
		echo '<tr><th>Digits in Bangla replies</th><td><label><input type="radio" name="reply_digits" value="en"' . checked( $val( 'reply_digits' ), 'en', false ) . '> English digits (0-9)</label> <label><input type="radio" name="reply_digits" value="bn"' . checked( $val( 'reply_digits' ), 'bn', false ) . '> Bangla digits (০-৯)</label></td></tr>';
		$chk( 'allow_public_mrp', 'Public MRP', 'Unverified contacts may be told the MRP of listed products (only items with mrp_bdt)' );
		$chk( 'exact_stock', 'Exact stock quantity', 'Tell linked dealers the exact WooCommerce quantity (default: in stock / limited / out of stock only)' );
		echo '<tr><th><label for="iaa-f-holding_reply_bn">Holding reply (Bangla)</label></th><td><textarea id="iaa-f-holding_reply_bn" name="holding_reply_bn" class="large-text" rows="2">' . esc_textarea( $val( 'holding_reply_bn' ) ) . '</textarea><p class="description">Used instead of a suggestion that contained an unapproved price.</p></td></tr>';
		echo '<tr><th><label for="iaa-f-holding_reply_en">Holding reply (English)</label></th><td><textarea id="iaa-f-holding_reply_en" name="holding_reply_en" class="large-text" rows="2">' . esc_textarea( $val( 'holding_reply_en' ) ) . '</textarea></td></tr>';
		echo '<tr><th><label for="iaa-f-banned_phrases">Warning phrases</label></th><td><textarea id="iaa-f-banned_phrases" name="banned_phrases" class="large-text" rows="4">' . esc_textarea( $val( 'banned_phrases' ) ) . '</textarea><p class="description">One per line. A suggestion that contains one gets a warning unless an approved fact contains it.</p></td></tr>';
		echo '</tbody></table>';

		echo '<h2>Inbox page access</h2><table class="form-table"><tbody>';
		$chk( 'live_inbox', 'Live inbox read & insert', 'Let the extension read the open conversation and insert text into Meta\'s reply box. Keep OFF until Meta permission evidence is recorded (T0.09). When OFF, staff select or paste text and use Copy.' );
		echo '</tbody></table>';

		echo '<h2>Privacy</h2><table class="form-table"><tbody>';
		$chk( 'masked_logging', 'Masked text in logs', 'Store masked conversation and suggestions in the log for quality review (default off)' );
		$num( 'retention_days', 'Keep suggestion logs (days)', '1', '7–90' );
		echo '</tbody></table>';

		echo '<h2>Roles</h2><p>Administrators and Shop managers are set automatically. Choose other roles (for example your staff-app role) that may use the extension.</p><table class="form-table"><tbody>';
		$roles = wp_roles()->get_names();
		foreach ( array( 'extra_use_roles' => 'May get suggestions', 'extra_link_roles' => 'May link conversations to dealers' ) as $k => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>';
			foreach ( $roles as $slug => $name ) {
				if ( in_array( $slug, array( 'administrator', 'shop_manager' ), true ) ) {
					continue;
				}
				echo '<label class="iaa-inline"><input type="checkbox" name="' . esc_attr( $k ) . '[]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, (array) $val( $k ), true ), true, false ) . '> ' . esc_html( translate_user_role( $name ) ) . '</label> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Uninstall</h2><table class="form-table"><tbody>';
		$chk( 'delete_data_on_uninstall', 'Delete all data on uninstall', 'Knowledge base, dealers, price lists, logs and settings are deleted when the plugin is deleted' );
		echo '</tbody></table>';
		submit_button( 'Save settings' );
		echo '</form>';

		echo '<h2>Test connection</h2><p>Sends a tiny request ("Reply with OK") with the saved provider settings. Last result: <strong>' . esc_html( $s['last_test_result'] ? $s['last_test_result'] : 'never run' ) . '</strong></p>';
		self::button_form( 'rar_iaa_provider_test', array(), 'Test connection', 'button' );
		self::footer();
	}
}
