<?php
/**
 * wp-admin screens A-01 Overview, A-02 Knowledge base, A-03 Dealers & tiers, A-05 Access tokens,
 * A-06 Logs (AppFlow §5). Products & prices and Settings live in their own classes.
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
use RarIaa\Domain\WcCatalogue;
use RarIaa\Installer;
use RarIaa\Limits\Budget;
use RarIaa\Providers\ProviderFactory;
use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class Pages {

	/* ================================================================== A-01 Overview */

	public static function home() {
		if ( ! current_user_can( Capabilities::VIEW_LOGS ) ) {
			self::tokens();
			return;
		}
		Ui::header( 'Overview', '', 'Fact-checked reply suggestions for your Meta Business Suite inbox. Staff always review and press Send themselves.' );
		$steps = self::setup_steps();
		$done  = count( array_filter( array_column( $steps, 'done' ) ) );
		if ( $done < count( $steps ) ) {
			Ui::panel(
				'Get started — ' . $done . ' of ' . count( $steps ) . ' done',
				static function () use ( $steps, $done ) {
					echo '<div class="iaa-progress" role="progressbar" aria-valuemin="0" aria-valuemax="' . esc_attr( count( $steps ) ) . '" aria-valuenow="' . esc_attr( $done ) . '" aria-label="Setup progress"><span style="width:' . esc_attr( (string) round( 100 * $done / count( $steps ) ) ) . '%"></span></div><ol class="iaa-steps">';
					foreach ( $steps as $s ) {
						echo '<li class="iaa-step' . ( $s['done'] ? ' is-done' : '' ) . '"><span class="iaa-step__icon dashicons dashicons-' . ( $s['done'] ? 'yes-alt' : 'marker' ) . '" aria-hidden="true"></span><div class="iaa-step__text"><strong>' . esc_html( $s['title'] ) . '</strong><span>' . esc_html( $s['text'] ) . '</span></div>';
						if ( ! $s['done'] && $s['url'] ) {
							echo Ui::button_link( $s['url'], $s['cta'], 'button button-small' ); // phpcs:ignore -- escaped in button_link.
						}
						echo '<span class="screen-reader-text">' . ( $s['done'] ? 'Done' : 'Not done' ) . '</span></li>';
					}
					echo '</ol>';
				}
			);
		}

		$today                    = Budget::today();
		list( $spent, $reserved ) = Budget::month_totals();
		$k                        = DraftLogRepository::kpis( 7 );
		$total                    = max( 1, (int) $k->total );
		echo '<div class="iaa-stats">';
		Ui::stat( 'Suggestions today', (int) $today->requests . ' / ' . (int) Settings::get( 'daily_limit' ) );
		Ui::stat( 'AI cost this month (estimate)', '$' . number_format( $spent / 1000000, 2 ) . ' / $' . number_format( (float) Settings::get( 'monthly_cap_usd' ), 2 ), $reserved ? 'Reserved now: $' . number_format( $reserved / 1000000, 4 ) : 'Free-tier calls cost $0' );
		Ui::stat( 'Used without edit (7 days)', $k->total ? round( 100 * (int) $k->accepted_unedited / $total ) . '%' : '—', (int) $k->total . ' requests' );
		Ui::stat( 'Handoff (7 days)', $k->total ? round( 100 * (int) $k->handoffs / $total ) . '%' : '—', (int) $k->blocked . ' blocked by guard' );
		echo '</div><div class="iaa-grid">';

		Ui::panel(
			'Service',
			static function () {
				$service = Settings::service_enabled();
				echo '<p>' . ( $service ? Ui::badge( 'active', 'Running' ) : Ui::badge( 'blocked', 'Stopped' ) ) . ' ' . esc_html( $service ? 'Staff can get suggestions.' : 'All suggestions are blocked.' ) . '</p>'; // phpcs:ignore
				echo '<p class="description">Primary AI: <strong>' . esc_html( Settings::PROVIDERS[ (string) Settings::get( 'provider' ) ] ) . '</strong>' . ( Settings::get( 'fallback_provider' ) ? ' · fallback: <strong>' . esc_html( Settings::PROVIDERS[ (string) Settings::get( 'fallback_provider' ) ] ) . '</strong>' : '' ) . '</p>';
				if ( defined( 'RAR_IAA_DISABLE' ) && RAR_IAA_DISABLE ) {
					echo '<p class="description">Stopped by the RAR_IAA_DISABLE constant in wp-config.php.</p>';
				} elseif ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
					Ui::button_form( 'rar_iaa_kill_switch', array( 'state' => $service ? 'off' : 'on' ), $service ? 'Stop service (kill switch)' : 'Start service', $service ? 'button iaa-button-danger' : 'button button-primary', $service ? 'All suggestions will stop immediately. Continue?' : '' );
				}
			}
		);
		Ui::panel(
			'Products & prices',
			static function () {
				$state = WcCatalogue::state();
				$pub   = DealerRepository::public_tier();
				$plist = $pub ? PriceRepository::active_for_tier( (int) $pub->id ) : null;
				if ( WcCatalogue::available() ) {
					echo '<p><strong>' . esc_html( $plist ? (int) $plist->row_count : 0 ) . '</strong> WooCommerce products linked.';
					echo $state && empty( $state['error'] ) ? ' Last sync ' . esc_html( Db::display( $state['at'] ) ) . '.' : ( $state ? ' <span class="iaa-bad">' . esc_html( $state['error'] ) . '</span>' : ' Not synced yet.' ); // phpcs:ignore
					echo WcCatalogue::pending_since() ? ' ' . Ui::badge( 'pending', 'changes waiting' ) : ''; // phpcs:ignore
					echo '</p>';
				}
				$lists = PriceRepository::lists( array( 'status' => 'active' ) );
				if ( ! $lists ) {
					echo '<p>No active price list yet — no prices will be quoted.</p>';
				} else {
					echo '<ul class="iaa-list">';
					foreach ( $lists as $l ) {
						echo '<li>Tier <strong>' . esc_html( $l->tier_code ) . '</strong> — ' . esc_html( $l->name ) . ' <span class="description">(' . esc_html( $l->row_count ) . ' items, ' . esc_html( 'woocommerce' === $l->source ? 'WooCommerce' : 'CSV' ) . ')</span></li>';
					}
					echo '</ul>';
				}
				if ( current_user_can( Capabilities::MANAGE_PRICES ) ) {
					echo '<p>' . Ui::button_link( Ui::url( 'rar-iaa-prices' ), 'Open Products & prices' ) . '</p>'; // phpcs:ignore
				}
			}
		);
		Ui::panel(
			'Knowledge base',
			static function () {
				$expiring = KbRepository::expiring( 7 );
				echo '<p><strong>' . esc_html( KbRepository::count_serving() ) . '</strong> approved answers in use.' . ( $expiring ? ' <span class="iaa-badge iaa-badge--warned">' . esc_html( count( $expiring ) ) . ' expire within 7 days</span>' : '' ) . '</p>';
				if ( current_user_can( Capabilities::MANAGE_KB ) ) {
					echo '<p>' . Ui::button_link( Ui::url( 'rar-iaa-kb', array( 'action' => 'new' ) ), 'Add answer' ) . ' ' . Ui::button_link( Ui::url( 'rar-iaa-kb' ), 'View all', 'button button-link' ) . '</p>'; // phpcs:ignore
				}
			}
		);
		echo '</div>';

		Ui::panel(
			'Health checks',
			static function () {
				echo '<table class="widefat iaa-health"><thead><tr><th scope="col">Check</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col"><span class="screen-reader-text">Action</span></th></tr></thead><tbody>';
				foreach ( self::health() as $row ) {
					echo '<tr><td>' . esc_html( $row[0] ) . '</td><td>' . ( $row[1] ? '<span class="iaa-ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> OK</span>' : '<span class="iaa-bad"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Needs attention</span>' ) . '</td><td>' . esc_html( $row[2] ) . '</td><td>' . ( ! $row[1] && ! empty( $row[3] ) ? Ui::button_link( $row[3], 'Fix', 'button button-small' ) : '' ) . '</td></tr>'; // phpcs:ignore
				}
				echo '</tbody></table>';
			}
		);
		Ui::footer();
	}

	/** Setup checklist for the Overview. */
	public static function setup_steps() {
		$chain    = ProviderFactory::chain();
		$ai_ready = ! is_wp_error( $chain );
		$tests    = (array) get_option( 'rar_iaa_provider_tests', array() );
		$primary  = (string) Settings::get( 'provider' );
		$tested   = isset( $tests[ $primary ] ) && 0 === strpos( (string) $tests[ $primary ]['result'], 'OK' ) || 0 === strpos( (string) Settings::get( 'last_test_result' ), 'OK' );
		$pub      = DealerRepository::public_tier();
		$prices   = (bool) PriceRepository::lists( array( 'status' => 'active' ) );
		return array(
			array( 'title' => 'Connect an AI provider', 'text' => $ai_ready ? 'Ready.' : ( is_wp_error( $chain ) ? $chain->get_error_message() : '' ), 'done' => $ai_ready, 'url' => Ui::url( 'rar-iaa-settings', array( 'tab' => 'ai' ) ), 'cta' => 'Set up AI' ),
			array( 'title' => 'Test the connection', 'text' => 'Sends a tiny "Reply with OK" request.', 'done' => $tested, 'url' => Ui::url( 'rar-iaa-settings', array( 'tab' => 'ai' ) ), 'cta' => 'Test now' ),
			array( 'title' => 'Add approved answers', 'text' => 'Office hours, contact, ordering, delivery, payment.', 'done' => KbRepository::count_serving() > 0, 'url' => Ui::url( 'rar-iaa-kb', array( 'action' => 'new' ) ), 'cta' => 'Add answer' ),
			array( 'title' => 'Link products & prices', 'text' => WcCatalogue::available() ? 'Sync WooCommerce products; set dealer price rules.' : 'Import a price list (CSV).', 'done' => $prices && ( ! WcCatalogue::available() || ( $pub && PriceRepository::active_for_tier( (int) $pub->id ) ) ), 'url' => Ui::url( 'rar-iaa-prices' ), 'cta' => 'Link products' ),
			array( 'title' => 'Add dealers', 'text' => 'Dealers get their tier price once staff link a conversation.', 'done' => DealerRepository::count_active() > 0, 'url' => Ui::url( 'rar-iaa-dealers', array( 'edit' => 'new' ) ), 'cta' => 'Add dealer' ),
			array( 'title' => 'Connect the Chrome extension', 'text' => 'Each staff member creates a token and pastes it into the side panel.', 'done' => TokenService::count_active() > 0, 'url' => Ui::url( 'rar-iaa-tokens' ), 'cta' => 'Create token' ),
		);
	}

	public static function health() {
		global $wp_version;
		$maint    = get_option( 'rar_iaa_maintenance_last_run' );
		$lsc      = defined( 'LSCWP_V' );
		$chain    = ProviderFactory::chain();
		$ai_names = is_wp_error( $chain ) ? array() : array_map(
			static function ( $p ) {
				$id = ProviderFactory::settings_id( $p );
				return ( isset( Settings::PROVIDERS[ $id ] ) ? Settings::PROVIDERS[ $id ] : $id ) . ( Settings::is_free( $id ) ? ' (free tier)' : '' );
			},
			$chain
		);
		$ai_url   = Ui::url( 'rar-iaa-settings', array( 'tab' => 'ai' ) );
		$state    = WcCatalogue::state();
		$rows     = array(
			array( 'AI provider', ! is_wp_error( $chain ), is_wp_error( $chain ) ? $chain->get_error_message() : implode( ' → ', $ai_names ), $ai_url ),
			array( 'Last test connection', 0 === strpos( (string) Settings::get( 'last_test_result' ), 'OK' ), Settings::get( 'last_test_result' ) ? Settings::get( 'last_test_result' ) . ' (' . Db::display( Settings::get( 'last_test_at' ) ) . ')' : 'Never run', $ai_url ),
			array( 'Database schema', ! Installer::missing_tables(), 'Version ' . (int) get_option( 'rar_iaa_schema_version' ) . ( get_option( 'rar_iaa_migration_error' ) ? ' — ' . get_option( 'rar_iaa_migration_error' ) : '' ), '' ),
			array( 'HTTPS', is_ssl() || 0 === strpos( home_url(), 'https://' ), home_url(), '' ),
			array( 'PHP / WordPress', version_compare( PHP_VERSION, '7.4', '>=' ), 'PHP ' . PHP_VERSION . ' · WordPress ' . $wp_version . ( Db::is_sqlite() ? ' · SQLite (development)' : '' ), '' ),
			array( 'WooCommerce link', ! WcCatalogue::available() || ( $state && empty( $state['error'] ) ), WcCatalogue::available() ? ( $state ? ( empty( $state['error'] ) ? 'Last sync ' . Db::display( $state['at'] ) . ', ' . (int) $state['products'] . ' products' : $state['error'] ) : 'Not synced yet' ) : 'WooCommerce not active — CSV price lists only', Ui::url( 'rar-iaa-prices' ) ),
			array( 'Daily maintenance', is_array( $maint ), is_array( $maint ) ? 'Last run ' . Db::display( $maint['at'] ) : 'Waiting for the first WP-Cron run (within 24 hours of activation)', '' ),
			array( 'AI concurrency', 1 === Settings::max_concurrent(), Settings::max_concurrent() . ' simultaneous call(s). Keep 1 until the hosting load test (T0.08) passes.', Ui::url( 'rar-iaa-settings', array( 'tab' => 'limits' ) ) ),
			array( 'Live inbox read/insert', ! Settings::get( 'live_inbox' ), Settings::get( 'live_inbox' ) ? 'ON — make sure Meta permission evidence (T0.09) is recorded' : 'OFF — staff select or paste text and use Copy', Ui::url( 'rar-iaa-settings', array( 'tab' => 'access' ) ) ),
			array( 'LiteSpeed Cache', true, $lsc ? 'Active: add /wp-json/rar-iaa/ to "Do Not Cache URIs" (the plugin also sends no-store headers)' : 'Not detected', '' ),
		);
		return $rows;
	}

	/* ================================================================== A-02 Knowledge base */

	public static function kb() {
		$action = Ui::get( 'action' );
		if ( 'edit' === $action || 'new' === $action ) {
			self::kb_edit( 'new' === $action ? 0 : (int) Ui::get( 'id' ) );
			return;
		}
		Ui::header( 'Knowledge base', Ui::button_link( Ui::url( 'rar-iaa-kb', array( 'action' => 'new' ) ), 'Add entry', 'button button-primary' ), 'Approved answers are the only facts the AI may use (besides product prices).' );
		$args               = array( 'status' => Ui::get( 'status' ), 'category' => Ui::get( 'category' ), 's' => Ui::get( 's' ), 'paged' => Ui::get( 'paged', 1 ), 'orderby' => Ui::get( 'orderby' ), 'pending' => Ui::get( 'pending' ) );
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
		if ( ! $rows && ! array_filter( array( $args['status'], $args['category'], $args['s'], $args['pending'] ) ) ) {
			Ui::empty_state( 'book-alt', 'No answers yet', 'Start with office hours, contact numbers, ordering, delivery and payment terms. The AI will only use what you write here.', Ui::url( 'rar-iaa-kb', array( 'action' => 'new' ) ), 'Add the first answer' );
			Ui::footer();
			return;
		}
		echo '<div class="iaa-table-wrap"><table class="widefat striped"><thead><tr><th scope="col"><a href="' . esc_url( Ui::url( 'rar-iaa-kb', array( 'orderby' => 'title' ) ) ) . '">Title</a></th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Effective</th><th scope="col">Last edited by</th><th scope="col">Approved by</th><th scope="col">Updated</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">No entries match these filters.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$title = $r->title ? $r->title : '(untitled)';
			echo '<tr><td><a href="' . esc_url( Ui::url( 'rar-iaa-kb', array( 'action' => 'edit', 'id' => $r->id ) ) ) . '"><strong>' . esc_html( $title ) . '</strong></a>' . ( $r->pending_review ? ' <span class="iaa-badge iaa-badge--warned">waiting for approval</span>' : '' ) . ( $r->draft_version_id && $r->approved_version_id ? ' <span class="iaa-badge iaa-badge--draft">new draft</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $r->category ) . '</td><td>' . Ui::badge( $r->status ) . '</td>'; // phpcs:ignore
			echo '<td>' . esc_html( $r->effective_from . ( $r->effective_to ? ' → ' . $r->effective_to : '' ) ) . '</td>';
			echo '<td>' . esc_html( Ui::user_name( $r->edited_by ) ) . '</td><td>' . esc_html( $r->approved_by ? Ui::user_name( $r->approved_by ) : '—' ) . '</td><td>' . esc_html( Db::display( $r->updated_at ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		Ui::pagination( $cnt, 20, 'rar-iaa-kb', array_filter( $args ) );
		Ui::footer();
	}

	private static function kb_edit( $id ) {
		$entry = $id ? KbRepository::get( $id ) : null;
		if ( $id && ! $entry ) {
			wp_die( 'Entry not found.' );
		}
		$v   = $entry ? KbRepository::current_version( $entry ) : null;
		$val = static function ( $field, $default = '' ) use ( $v, $entry ) {
			if ( Notice::has_old() ) {
				return Notice::old( $field, $default );
			}
			if ( 'category' === $field && $entry ) {
				return $entry->category;
			}
			return $v && isset( $v->$field ) && null !== $v->$field ? $v->$field : $default;
		};
		Ui::header( $entry ? 'Edit answer' : 'New answer', Ui::button_link( Ui::url( 'rar-iaa-kb' ), '← Back to list' ) );
		if ( $entry ) {
			echo '<p>Status: ' . Ui::badge( $entry->status ) . ( $entry->draft_version_id ? ' — editing draft version ' . esc_html( $v->version_no ) : '' ) . ( $entry->approved_version_id && $entry->draft_version_id ? ' (the approved version keeps serving until this draft is approved)' : '' ) . '</p>'; // phpcs:ignore
		}
		if ( ! $entry || 'archived' !== $entry->status ) {
			echo '<div class="iaa-panel"><div class="iaa-panel__body">';
			Ui::form_open( 'rar_iaa_kb_save' );
			echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="row_version" value="' . esc_attr( $entry ? $entry->row_version : 0 ) . '">';
			echo '<table class="form-table" role="presentation"><tbody>';
			echo '<tr><th><label for="iaa-f-title">Title *</label></th><td><input class="regular-text" id="iaa-f-title" name="title" maxlength="120" required value="' . esc_attr( $val( 'title' ) ) . '">';
			Ui::field_error( 'title' );
			echo '</td></tr><tr><th><label for="iaa-f-category">Category *</label></th><td><select id="iaa-f-category" name="category">';
			foreach ( KbRepository::category_labels() as $c => $l ) {
				echo '<option value="' . esc_attr( $c ) . '"' . selected( $val( 'category', 'other' ), $c, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
			Ui::field_error( 'category' );
			echo '</td></tr><tr><th><label for="iaa-f-keywords">Keywords</label></th><td><input class="large-text" id="iaa-f-keywords" name="keywords" value="' . esc_attr( str_replace( ',', ', ', $val( 'keywords' ) ) ) . '"><p class="description">Comma-separated words customers use (Bangla, Banglish, English), e.g. <code>khola, koyta, সময়, office hours</code>.</p>';
			Ui::field_error( 'keywords' );
			echo '</td></tr><tr><th><label for="iaa-f-answer_bn">Answer (Bangla) *</label></th><td><textarea class="large-text" rows="4" id="iaa-f-answer_bn" name="answer_bn" maxlength="2000" lang="bn" required>' . esc_textarea( $val( 'answer_bn' ) ) . '</textarea><p class="description">Write the exact facts (times, numbers, prices) the AI may use. The AI will not use anything that is not here.</p>';
			Ui::field_error( 'answer_bn' );
			echo '</td></tr><tr><th><label for="iaa-f-answer_en">Answer (English)</label></th><td><textarea class="large-text" rows="3" id="iaa-f-answer_en" name="answer_en" maxlength="2000">' . esc_textarea( $val( 'answer_en' ) ) . '</textarea>';
			Ui::field_error( 'answer_en' );
			echo '</td></tr><tr><th><label for="iaa-f-effective_from">Effective from *</label></th><td><input type="date" id="iaa-f-effective_from" name="effective_from" value="' . esc_attr( $val( 'effective_from', Db::dhaka_date() ) ) . '"> <label for="iaa-f-effective_to">to</label> <input type="date" id="iaa-f-effective_to" name="effective_to" value="' . esc_attr( $val( 'effective_to' ) ) . '"> <span class="description">(leave "to" empty for no end)</span>';
			Ui::field_error( 'effective_from' );
			Ui::field_error( 'effective_to' );
			echo '</td></tr><tr><th><label for="iaa-f-note">Internal note</label></th><td><textarea class="large-text" rows="2" id="iaa-f-note" name="note" maxlength="500">' . esc_textarea( $val( 'note' ) ) . '</textarea><p class="description">Never sent to the AI.</p></td></tr>';
			echo '</tbody></table><p class="submit"><button class="button" name="save" value="1">Save draft</button> <button class="button button-primary" name="submit_review" value="1">Save &amp; submit for approval</button></p></form></div></div>';
		}
		if ( $entry ) {
			echo '<div class="iaa-actions-row">';
			if ( $entry->draft_version_id && current_user_can( Capabilities::APPROVE_KB ) ) {
				$self_edit = (int) $v->edited_by === get_current_user_id() && Capabilities::admin_count() > 1;
				if ( $self_edit ) {
					echo '<p class="description">You edited this version, so another approver must approve it.</p>';
				} else {
					Ui::button_form( 'rar_iaa_kb_approve', array( 'id' => $entry->id, 'row_version' => $entry->row_version ), 'Approve this version', 'button button-primary' );
				}
			}
			if ( 'archived' !== $entry->status ) {
				Ui::button_form( 'rar_iaa_kb_archive', array( 'id' => $entry->id ), 'Archive', 'button iaa-button-danger', 'Archive this entry? It stops being used immediately.' );
			} else {
				Ui::button_form( 'rar_iaa_kb_restore', array( 'id' => $entry->id ), 'Restore as new draft', 'button' );
			}
			echo '</div>';
			Ui::panel(
				'Version history',
				static function () use ( $entry ) {
					echo '<table class="widefat striped"><thead><tr><th scope="col">Version</th><th scope="col">State</th><th scope="col">Title</th><th scope="col">Edited</th><th scope="col">Approved</th></tr></thead><tbody>';
					foreach ( KbRepository::versions( $entry->id ) as $ver ) {
						echo '<tr><td>' . esc_html( $ver->version_no ) . '</td><td>' . Ui::badge( $ver->state ) . '</td><td>' . esc_html( $ver->title ) . '</td><td>' . esc_html( Ui::user_name( $ver->edited_by ) . ', ' . Db::display( $ver->edited_at ) ) . '</td><td>' . esc_html( $ver->approved_by ? Ui::user_name( $ver->approved_by ) . ', ' . Db::display( $ver->approved_at ) : '—' ) . '</td></tr>'; // phpcs:ignore
					}
					echo '</tbody></table>';
				}
			);
		}
		Ui::footer();
	}

	/* ================================================================== A-03 Dealers & tiers */

	public static function dealers() {
		$tab = 'tiers' === Ui::get( 'tab' ) ? 'tiers' : 'dealers';
		Ui::header( 'Dealers & tiers', 'dealers' === $tab ? Ui::button_link( Ui::url( 'rar-iaa-dealers', array( 'edit' => 'new' ) ), 'Add dealer', 'button button-primary' ) : '', 'A dealer gets the price of their tier — only after staff link the conversation to that dealer.' );
		Ui::tabs( 'rar-iaa-dealers', array( 'dealers' => 'Dealers', 'tiers' => 'Tiers' ), $tab );
		$tiers = DealerRepository::tiers();
		if ( 'tiers' === $tab ) {
			$counts = DealerRepository::count_by_tier();
			echo '<p class="description">A tier is a price group. Price rules (WooCommerce price ± % or CSV) are set in ' . Ui::button_link( Ui::url( 'rar-iaa-prices' ), 'Products & prices', 'button-link' ) . '.</p>'; // phpcs:ignore
			echo '<div class="iaa-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">Code</th><th scope="col">Name &amp; status</th><th scope="col">Price source</th><th scope="col">Dealers</th></tr></thead><tbody>';
			foreach ( $tiers as $t ) {
				echo '<tr><td><strong>' . esc_html( $t->code ) . '</strong>' . ( (int) $t->is_public ? ' ' . Ui::badge( 'free', 'public' ) : '' ) . '</td><td>'; // phpcs:ignore
				Ui::form_open( 'rar_iaa_tier_save', false, 'iaa-inline-form' );
				echo '<input type="hidden" name="id" value="' . esc_attr( $t->id ) . '"><input type="hidden" name="row_version" value="' . esc_attr( $t->row_version ) . '"><input name="code" value="' . esc_attr( $t->code ) . '" size="8" aria-label="Code for tier ' . esc_attr( $t->code ) . '"> <input name="name" value="' . esc_attr( $t->name ) . '" aria-label="Name for tier ' . esc_attr( $t->code ) . '"> <select name="status" aria-label="Status for tier ' . esc_attr( $t->code ) . '"><option value="active"' . selected( $t->status, 'active', false ) . '>active</option><option value="inactive"' . selected( $t->status, 'inactive', false ) . '>inactive</option></select> <button class="button">Save</button></form></td>';
				echo '<td>' . esc_html( self::rule_text( $t ) ) . '</td><td>' . esc_html( isset( $counts[ (int) $t->id ] ) ? $counts[ (int) $t->id ] : 0 ) . '</td></tr>';
			}
			if ( ! $tiers ) {
				echo '<tr><td colspan="4">No tiers yet. Add one below (for example A — Distributor, B — Dealer).</td></tr>';
			}
			echo '</tbody></table></div>';
			Ui::panel(
				'Add tier',
				static function () {
					Ui::form_open( 'rar_iaa_tier_save', false, 'iaa-inline-form' );
					echo '<label for="iaa-f-code" class="screen-reader-text">Code</label><input name="code" placeholder="Code e.g. A" size="8" required value="' . esc_attr( Notice::old( 'code' ) ) . '" id="iaa-f-code"> <label for="iaa-f-name" class="screen-reader-text">Name</label><input name="name" placeholder="Name e.g. Distributor" required value="' . esc_attr( Notice::old( 'name' ) ) . '" id="iaa-f-name"> <button class="button button-primary">Add tier</button></form>';
					Ui::field_error( 'code' );
					Ui::field_error( 'name' );
				}
			);
			Ui::footer();
			return;
		}
		$edit = Ui::get( 'edit' );
		if ( '' !== $edit ) {
			$d   = 'new' === $edit ? null : DealerRepository::dealer( (int) $edit );
			$val = static function ( $f, $def = '' ) use ( $d ) {
				if ( Notice::has_old() ) {
					return Notice::old( $f, $def );
				}
				return $d && null !== $d->$f ? $d->$f : $def;
			};
			echo '<div class="iaa-panel"><div class="iaa-panel__head"><h2>' . ( $d ? 'Edit dealer' : 'Add dealer' ) . '</h2></div><div class="iaa-panel__body">';
			Ui::form_open( 'rar_iaa_dealer_save' );
			echo '<input type="hidden" name="id" value="' . esc_attr( $d ? $d->id : 0 ) . '"><input type="hidden" name="row_version" value="' . esc_attr( $d ? $d->row_version : 0 ) . '"><table class="form-table" role="presentation"><tbody>';
			echo '<tr><th><label for="iaa-f-dealer_code">Dealer code *</label></th><td><input id="iaa-f-dealer_code" name="dealer_code" required value="' . esc_attr( $val( 'dealer_code' ) ) . '">';
			Ui::field_error( 'dealer_code' );
			echo '</td></tr><tr><th><label for="iaa-f-name">Name *</label></th><td><input class="regular-text" id="iaa-f-name" name="name" required value="' . esc_attr( $val( 'name' ) ) . '">';
			Ui::field_error( 'name' );
			echo '</td></tr><tr><th><label for="iaa-f-tier_id">Tier *</label></th><td><select id="iaa-f-tier_id" name="tier_id"><option value="">—</option>';
			foreach ( $tiers as $t ) {
				if ( 'active' === $t->status ) {
					echo '<option value="' . esc_attr( $t->id ) . '"' . selected( (int) $val( 'tier_id' ), (int) $t->id, false ) . '>' . esc_html( $t->code . ' — ' . $t->name ) . '</option>';
				}
			}
			echo '</select>';
			if ( ! $tiers ) {
				echo ' ' . Ui::button_link( Ui::url( 'rar-iaa-dealers', array( 'tab' => 'tiers' ) ), 'Create a tier first', 'button-link' ); // phpcs:ignore
			}
			Ui::field_error( 'tier_id' );
			echo '</td></tr><tr><th><label for="iaa-f-district_code">District code</label></th><td><input id="iaa-f-district_code" name="district_code" placeholder="BD-13" value="' . esc_attr( $val( 'district_code' ) ) . '">';
			Ui::field_error( 'district_code' );
			echo '</td></tr><tr><th><label for="iaa-f-status">Status</label></th><td><select id="iaa-f-status" name="status"><option value="active"' . selected( $val( 'status', 'active' ), 'active', false ) . '>active</option><option value="inactive"' . selected( $val( 'status' ), 'inactive', false ) . '>inactive</option></select><p class="description">An inactive dealer is treated as unverified: no dealer prices.</p></td></tr>';
			echo '<tr><th><label for="iaa-f-note">Internal note</label></th><td><textarea id="iaa-f-note" name="note" class="large-text" rows="2" maxlength="500">' . esc_textarea( $val( 'note' ) ) . '</textarea><p class="description">Never sent to the AI. Do not store phone numbers or addresses here.</p></td></tr>';
			echo '</tbody></table><p class="submit"><button class="button button-primary">Save dealer</button> <a class="button" href="' . esc_url( Ui::url( 'rar-iaa-dealers' ) ) . '">Cancel</a></p></form></div></div>';
			Ui::footer();
			return;
		}
		$args               = array( 's' => Ui::get( 's' ), 'tier_id' => Ui::get( 'tier_id' ), 'status' => Ui::get( 'status' ), 'paged' => Ui::get( 'paged', 1 ) );
		list( $rows, $cnt ) = DealerRepository::list_admin( $args );
		if ( ! $tiers ) {
			Ui::empty_state( 'groups', 'Create a tier first', 'Tiers are price groups (for example A — Distributor, B — Dealer). Every dealer belongs to one tier.', Ui::url( 'rar-iaa-dealers', array( 'tab' => 'tiers' ) ), 'Create a tier' );
			Ui::footer();
			return;
		}
		echo '<form method="get" class="iaa-filters"><input type="hidden" name="page" value="rar-iaa-dealers"><select name="tier_id" aria-label="Filter by tier"><option value="">All tiers</option>';
		foreach ( $tiers as $t ) {
			echo '<option value="' . esc_attr( $t->id ) . '"' . selected( $args['tier_id'], (string) $t->id, false ) . '>' . esc_html( $t->code ) . '</option>';
		}
		echo '</select> <select name="status" aria-label="Filter by status"><option value="">All</option><option value="active"' . selected( $args['status'], 'active', false ) . '>active</option><option value="inactive"' . selected( $args['status'], 'inactive', false ) . '>inactive</option></select> <input type="search" name="s" value="' . esc_attr( $args['s'] ) . '" placeholder="Code or name" aria-label="Search dealers"> <button class="button">Filter</button></form>';
		if ( ! $rows && ! array_filter( array( $args['s'], $args['tier_id'], $args['status'] ) ) ) {
			Ui::empty_state( 'businessperson', 'No dealers yet', 'Add your dealers and distributors with their code and tier. Staff then link a Messenger conversation to a dealer to use that tier\'s prices.', Ui::url( 'rar-iaa-dealers', array( 'edit' => 'new' ) ), 'Add dealer' );
			Ui::footer();
			return;
		}
		echo '<div class="iaa-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col">Tier</th><th scope="col">District</th><th scope="col">Status</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No dealers match these filters.</td></tr>';
		}
		foreach ( $rows as $d ) {
			echo '<tr><td><a href="' . esc_url( Ui::url( 'rar-iaa-dealers', array( 'edit' => $d->id ) ) ) . '"><strong>' . esc_html( $d->dealer_code ) . '</strong></a></td><td>' . esc_html( $d->name ) . '</td><td>' . esc_html( $d->tier_code ) . '</td><td>' . esc_html( $d->district_code ) . '</td><td>' . Ui::badge( $d->status ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table></div>';
		Ui::pagination( $cnt, 20, 'rar-iaa-dealers', array_filter( $args ) );
		Ui::footer();
	}

	/** Human description of a tier's price rule. */
	public static function rule_text( $t ) {
		if ( 'woocommerce' !== $t->price_source ) {
			return 'CSV price list';
		}
		$adj = (float) $t->wc_adjust_pct;
		return 'WooCommerce ' . ( 'regular' === $t->wc_price_basis ? 'regular price' : 'current price' ) . ( 0.0 === $adj ? '' : ' ' . ( $adj > 0 ? '+' : '−' ) . rtrim( rtrim( number_format( abs( $adj ), 2 ), '0' ), '.' ) . '%' ) . ( (int) $t->wc_rounding ? ', rounded to ' . (int) $t->wc_rounding . ' ৳' : '' );
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
		Ui::header( 'Access tokens', '', 'Each staff member signs the Chrome extension in with their own token.' );
		if ( $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		if ( $created ) {
			echo '<div class="notice notice-success iaa-token-once"><p><strong>Copy this token now. It will not be shown again.</strong></p><p><label for="iaa-new-token" class="screen-reader-text">New token</label><input type="text" readonly class="large-text code" id="iaa-new-token" value="' . esc_attr( $created ) . '"> <button type="button" class="button" data-iaa-copy="#iaa-new-token">Copy</button></p><p>Paste it into the Chrome extension: side panel → সংযোগ করুন.</p></div>';
		}
		echo '<div class="iaa-grid iaa-grid--2">';
		Ui::panel(
			'Create a token for this computer',
			static function () {
				echo '<form method="post" action="' . esc_url( Ui::url( 'rar-iaa-tokens' ) ) . '" class="iaa-inline-form">';
				wp_nonce_field( 'rar_iaa_token_create' );
				echo '<input type="hidden" name="iaa_create_token" value="1"><label>Label <input name="label" maxlength="60" required placeholder="Office PC 2"></label> <label>Expires in <select name="days"><option value="30">30 days</option><option value="90">90 days</option><option value="180" selected>180 days</option></select></label> <button class="button button-primary">Create token</button></form>';
				echo '<p class="description">Each person needs their own WordPress account and token. Maximum 3 active tokens per person. Revoke a token immediately if a computer is lost.</p>';
			}
		);
		Ui::panel(
			'How to connect the extension',
			static function () {
				echo '<ol class="iaa-howto"><li>Chrome → <code>chrome://extensions</code> → Developer mode → <strong>Load unpacked</strong> → choose the unzipped extension folder.</li><li>Open Meta Business Suite inbox and click the Inbox AI Assist icon to open the side panel.</li><li>Paste the token and press <strong>সংযোগ করুন</strong>. If your site uses <code>www.</code>, the panel detects it automatically.</li></ol>';
			}
		);
		echo '</div>';
		$all  = current_user_can( Capabilities::MANAGE_SETTINGS );
		$rows = TokenService::list_for( $all ? null : $uid );
		Ui::panel(
			$all ? 'All tokens' : 'My tokens',
			static function () use ( $all, $rows ) {
				if ( ! $rows ) {
					echo '<p>No tokens yet.</p>';
					return;
				}
				echo '<table class="widefat striped"><thead><tr>' . ( $all ? '<th scope="col">User</th>' : '' ) . '<th scope="col">Label</th><th scope="col">Prefix</th><th scope="col">Created</th><th scope="col">Last used</th><th scope="col">Expires</th><th scope="col">Status</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
				foreach ( $rows as $t ) {
					$status = TokenService::status_of( $t );
					echo '<tr>' . ( $all ? '<td>' . esc_html( Ui::user_name( $t->user_id ) ) . '</td>' : '' ) . '<td>' . esc_html( $t->label ) . '</td><td><code>iaa_' . esc_html( $t->token_prefix ) . '_…</code></td><td>' . esc_html( Db::display( $t->created_at ) ) . '</td><td>' . esc_html( Db::display( $t->last_used_at ) ) . '</td><td>' . esc_html( Db::display( $t->expires_at ) ) . '</td><td>' . Ui::badge( $status ) . '</td><td>'; // phpcs:ignore
					if ( 'active' === $status ) {
						Ui::button_form( 'rar_iaa_token_revoke', array( 'id' => $t->id ), 'Revoke', 'button button-small iaa-button-danger', 'Revoke this token?' );
					}
					echo '</td></tr>';
				}
				echo '</tbody></table>';
			}
		);
		Ui::footer();
	}

	/* ================================================================== A-06 Logs */

	public static function logs() {
		$tab = 'audit' === Ui::get( 'tab' ) ? 'audit' : 'drafts';
		Ui::header( 'Logs', '', 'Metadata only — conversation text is never stored unless masked logging is on.' );
		Ui::tabs( 'rar-iaa-logs', array( 'drafts' => 'Suggestions', 'audit' => 'Audit' ), $tab );
		if ( 'audit' === $tab ) {
			self::audit_tab();
			Ui::footer();
			return;
		}
		$args = array(
			'from'    => Ui::get( 'from', Db::dhaka_date() ),
			'to'      => Ui::get( 'to', Db::dhaka_date() ),
			'outcome' => Ui::get( 'outcome' ),
			'guard'   => Ui::get( 'guard' ),
			'handoff' => Ui::get( 'handoff' ),
			'paged'   => Ui::get( 'paged', 1 ),
		);
		list( $rows, $cnt ) = DraftLogRepository::list_admin( $args );
		echo '<div class="iaa-actions-row"><form method="get" class="iaa-filters"><input type="hidden" name="page" value="rar-iaa-logs"><label>From <input type="date" name="from" value="' . esc_attr( $args['from'] ) . '"></label> <label>To <input type="date" name="to" value="' . esc_attr( $args['to'] ) . '"></label> <select name="outcome" aria-label="Filter by outcome"><option value="">Any outcome</option>';
		foreach ( array_merge( array( 'pending' ), DraftLogRepository::OUTCOMES ) as $o ) {
			echo '<option' . selected( $args['outcome'], $o, false ) . '>' . esc_html( $o ) . '</option>';
		}
		echo '</select> <select name="guard" aria-label="Filter by guard result"><option value="">Any guard</option>';
		foreach ( array( 'pass', 'warned', 'blocked' ) as $g ) {
			echo '<option' . selected( $args['guard'], $g, false ) . '>' . esc_html( $g ) . '</option>';
		}
		echo '</select> <select name="handoff" aria-label="Filter by handoff"><option value="">Handoff any</option><option value="1"' . selected( $args['handoff'], '1', false ) . '>Handoff yes</option><option value="0"' . selected( $args['handoff'], '0', false ) . '>Handoff no</option></select> <button class="button">Filter</button></form>';
		Ui::form_open( 'rar_iaa_logs_export', false, 'iaa-inline-form' );
		echo '<input type="hidden" name="from" value="' . esc_attr( $args['from'] ) . '"><input type="hidden" name="to" value="' . esc_attr( $args['to'] ) . '"><button class="button"><span class="dashicons dashicons-download" aria-hidden="true"></span> Export CSV</button></form></div>';
		echo '<div class="iaa-table-wrap"><table class="widefat striped iaa-log"><thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Dealer</th><th scope="col">Source</th><th scope="col">AI</th><th scope="col">Lang</th><th scope="col">Status</th><th scope="col">Guard</th><th scope="col">Handoff</th><th scope="col">Selected</th><th scope="col">Outcome</th><th scope="col">ms</th><th scope="col">Cost $</th><th scope="col">Request ID</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="14">No requests in this period.</td></tr>';
		}
		$masked = (bool) Settings::get( 'masked_logging' );
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( Db::display( $r->created_at ) ) . '</td><td>' . esc_html( $r->display_name ) . '</td><td>' . esc_html( $r->dealer_code ? $r->dealer_code : '—' ) . '</td><td>' . esc_html( $r->source . ( $r->rewrite_mode ? ':' . $r->rewrite_mode : '' ) ) . '</td><td>' . esc_html( isset( $r->provider ) && $r->provider ? $r->provider : '—' ) . '</td><td>' . esc_html( $r->language ) . '</td><td>' . Ui::badge( $r->status ) . ( $r->error_code ? ' <code>' . esc_html( $r->error_code ) . '</code>' : '' ) . '</td><td>' . ( $r->guard_result ? Ui::badge( $r->guard_result ) . ' ' . esc_html( $r->warnings_count ) : '—' ) . '</td><td>' . esc_html( $r->handoff_needed ? $r->handoff_reason : '—' ) . '</td><td>' . esc_html( $r->selected_variant_id ? $r->selected_variant_id : '—' ) . '</td><td>' . Ui::badge( $r->outcome ) . ( $r->reject_reason ? ' ' . esc_html( $r->reject_reason ) : '' ) . '</td><td class="num">' . esc_html( $r->latency_ms ) . '</td><td class="num">' . esc_html( number_format( $r->cost_usd_micros / 1000000, 4 ) ) . '</td><td><code>' . esc_html( substr( $r->request_id, 0, 8 ) ) . '</code></td></tr>'; // phpcs:ignore
			if ( $masked && ( $r->input_masked || $r->reply_masked ) ) {
				echo '<tr class="iaa-log-detail"><td colspan="14"><details><summary>Masked text</summary><pre class="iaa-pre">' . esc_html( (string) $r->input_masked ) . "\n\n" . esc_html( (string) $r->reply_masked ) . '</pre></details></td></tr>';
			}
		}
		echo '</tbody></table></div>';
		Ui::pagination( $cnt, 50, 'rar-iaa-logs', array_filter( $args ) );
		Ui::footer();
	}

	private static function audit_tab() {
		global $wpdb;
		$page = max( 1, (int) Ui::get( 'paged', 1 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, u.display_name FROM ' . Db::t( 'audit_log' ) . ' a LEFT JOIN ' . $wpdb->users . ' u ON u.ID = a.user_id ORDER BY a.id DESC LIMIT 50 OFFSET %d', ( $page - 1 ) * 50 ) );
		$cnt  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Db::t( 'audit_log' ) );
		echo '<div class="iaa-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Object</th><th scope="col">Change</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( Db::display( $r->created_at ) ) . '</td><td>' . esc_html( $r->display_name ? $r->display_name : 'System' ) . '</td><td><code>' . esc_html( $r->action ) . '</code></td><td>' . esc_html( $r->object_type . ( $r->object_id ? ' #' . $r->object_id : '' ) ) . '</td><td><details><summary>View</summary><pre class="iaa-pre">' . esc_html( 'Before: ' . (string) $r->before_json . "\nAfter: " . (string) $r->after_json ) . '</pre></details></td></tr>';
		}
		echo '</tbody></table></div>';
		Ui::pagination( $cnt, 50, 'rar-iaa-logs', array( 'tab' => 'audit' ) );
	}
}
