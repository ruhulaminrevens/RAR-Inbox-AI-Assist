<?php
/**
 * Shared admin building blocks (UI Spec §10): brand header, section navigation, cards, badges,
 * empty states and nonce'd forms. All output is escaped here or by the caller.
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

use RarIaa\Capabilities;
use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class Ui {

	/** Section navigation: slug => array( label, capability ). */
	const NAV = array(
		'rar-iaa'          => array( 'Overview', Capabilities::USE ),
		'rar-iaa-kb'       => array( 'Knowledge base', Capabilities::MANAGE_KB ),
		'rar-iaa-dealers'  => array( 'Dealers & tiers', Capabilities::MANAGE_PRICES ),
		'rar-iaa-prices'   => array( 'Products & prices', Capabilities::MANAGE_PRICES ),
		'rar-iaa-tokens'   => array( 'Access tokens', Capabilities::USE ),
		'rar-iaa-logs'     => array( 'Logs', Capabilities::VIEW_LOGS ),
		'rar-iaa-settings' => array( 'Settings', Capabilities::MANAGE_SETTINGS ),
	);

	public static function get( $k, $default = '' ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Page start: brand bar, section nav, title row, notices.
	 *
	 * @param string $title    Page title (h1).
	 * @param string $actions  Pre-escaped HTML for title-row buttons.
	 * @param string $subtitle Plain text under the title.
	 */
	public static function header( $title, $actions = '', $subtitle = '' ) {
		$current = self::get( 'page', 'rar-iaa' );
		$service = Settings::service_enabled();
		echo '<div class="wrap iaa-wrap">';
		echo '<div class="iaa-top"><div class="iaa-brand"><span class="iaa-logo dashicons dashicons-format-chat" aria-hidden="true"></span><div><div class="iaa-brand__name">Inbox AI Assist</div><div class="iaa-brand__meta">v' . esc_html( RAR_IAA_VERSION ) . ' · ' . esc_html( (string) Settings::get( 'business_name' ) ) . '</div></div></div>';
		echo '<div class="iaa-top__right">' . self::ai_pill() . '<span class="iaa-pill ' . ( $service ? 'iaa-pill--ok' : 'iaa-pill--bad' ) . '"><span class="iaa-dot" aria-hidden="true"></span>' . ( $service ? 'Service active' : 'Service stopped' ) . '</span></div></div>'; // phpcs:ignore -- built from escaped parts.
		echo '<nav class="iaa-nav" aria-label="Inbox AI Assist sections">';
		foreach ( self::NAV as $slug => $item ) {
			if ( ! current_user_can( $item[1] ) ) {
				continue;
			}
			$active = $slug === $current;
			echo '<a class="iaa-nav__link' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( self::url( $slug ) ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $item[0] ) . '</a>';
		}
		echo '</nav>';
		echo '<div class="iaa-head"><div><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>' . ( $subtitle ? '<p class="iaa-sub">' . esc_html( $subtitle ) . '</p>' : '' ) . '</div>';
		if ( $actions ) {
			echo '<div class="iaa-head__actions">' . $actions . '</div>'; // phpcs:ignore -- pre-escaped action links.
		}
		echo '</div><hr class="wp-header-end">';
		Notice::render();
	}

	public static function footer() {
		echo '</div>';
	}

	/** "AI: Gemini (free) → Anthropic" summary pill. */
	private static function ai_pill() {
		$names = array();
		foreach ( array( (string) Settings::get( 'provider' ), (string) Settings::get( 'fallback_provider' ) ) as $i => $p ) {
			if ( '' === $p || ( 1 === $i && $p === $names[0][0] ) ) {
				continue;
			}
			$label   = isset( Settings::PROVIDERS[ $p ] ) ? preg_replace( '/\s*\(.*\)$/', '', Settings::PROVIDERS[ $p ] ) : $p;
			$ready   = ! is_wp_error( \RarIaa\Providers\ProviderFactory::make( $p ) );
			$names[] = array( $p, $label . ( ! $ready ? ' (not set)' : ( Settings::is_free( $p ) ? ' (free)' : '' ) ) );
		}
		$text = 'AI: ' . implode( ' → ', array_column( $names, 1 ) );
		return '<span class="iaa-pill" title="Primary → fallback AI provider">' . esc_html( $text ) . '</span>';
	}

	public static function button_link( $url, $label, $class = 'button' ) {
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	public static function form_open( $action, $multipart = false, $class = '', $id = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
	}

	public static function button_form( $action, array $hidden, $label, $class = 'button', $confirm = '' ) {
		self::form_open( $action, false, 'iaa-inline-form' );
		foreach ( $hidden as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-iaa-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button></form>';
	}

	public static function badge( $status, $text = null ) {
		$map = array(
			'approved'  => 'approved',
			'active'    => 'active',
			'updated'   => 'approved',
			'unchanged' => 'archived',
			'draft'     => 'draft',
			'archived'  => 'archived',
			'inactive'  => 'archived',
			'revoked'   => 'archived',
			'expired'   => 'archived',
			'pass'      => 'approved',
			'warned'    => 'warned',
			'blocked'   => 'blocked',
			'pending'   => 'draft',
			'ok'        => 'approved',
			'error'     => 'blocked',
			'empty'     => 'warned',
			'free'      => 'approved',
			'paid'      => 'draft',
		);
		$cls = isset( $map[ $status ] ) ? $map[ $status ] : 'draft';
		return '<span class="iaa-badge iaa-badge--' . esc_attr( $cls ) . '">' . esc_html( null === $text ? $status : $text ) . '</span>';
	}

	/** KPI card. */
	public static function stat( $label, $value, $sub = '' ) {
		echo '<div class="iaa-card iaa-stat"><div class="iaa-stat__label">' . esc_html( $label ) . '</div><div class="iaa-stat__value">' . esc_html( $value ) . '</div>' . ( $sub ? '<div class="iaa-stat__sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div>';
	}

	/** Panel with a heading; pass a callable that prints the body. */
	public static function panel( $title, $body, $actions = '', $id = '' ) {
		echo '<section class="iaa-panel"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
		if ( $title ) {
			echo '<div class="iaa-panel__head"><h2>' . esc_html( $title ) . '</h2>' . ( $actions ? '<div class="iaa-panel__actions">' . $actions . '</div>' : '' ) . '</div>'; // phpcs:ignore -- pre-escaped actions.
		}
		echo '<div class="iaa-panel__body">';
		call_user_func( $body );
		echo '</div></section>';
	}

	/** Friendly empty state with an optional call to action. */
	public static function empty_state( $icon, $title, $text, $cta_url = '', $cta_label = '' ) {
		echo '<div class="iaa-empty"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span><p class="iaa-empty__title">' . esc_html( $title ) . '</p><p>' . esc_html( $text ) . '</p>';
		if ( $cta_url ) {
			echo '<p>' . self::button_link( $cta_url, $cta_label, 'button button-primary' ) . '</p>'; // phpcs:ignore -- escaped in button_link.
		}
		echo '</div>';
	}

	public static function field_error( $field ) {
		$e = Notice::field_error( $field );
		if ( $e ) {
			echo '<p class="iaa-field-error" id="iaa-e-' . esc_attr( $field ) . '">' . esc_html( $e ) . '</p>';
		}
	}

	public static function pagination( $total, $per, $page_slug, array $args ) {
		$pages = (int) ceil( $total / $per );
		if ( $pages <= 1 ) {
			return;
		}
		$cur = max( 1, (int) self::get( 'paged', 1 ) );
		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( $total ) . ' items</span> ';
		for ( $i = 1; $i <= min( $pages, 50 ); $i++ ) {
			if ( $i === $cur ) {
				echo '<span class="button disabled" aria-current="page">' . esc_html( $i ) . '</span> ';
			} else {
				echo '<a class="button" href="' . esc_url( self::url( $page_slug, array_merge( $args, array( 'paged' => $i ) ) ) ) . '">' . esc_html( $i ) . '</a> ';
			}
		}
		echo '</div></div>';
	}

	public static function user_name( $id ) {
		$u = $id ? get_userdata( (int) $id ) : null;
		return $u ? $u->display_name : ( 0 === (int) $id ? 'System' : '—' );
	}

	/** Sub-tabs inside a page. $tabs: key => label. */
	public static function tabs( $page, array $tabs, $current, array $extra = array() ) {
		echo '<nav class="iaa-tabs" aria-label="Page sections">';
		foreach ( $tabs as $key => $label ) {
			$active = $key === $current;
			echo '<a class="iaa-tabs__link' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( self::url( $page, array_merge( $extra, array( 'tab' => $key ) ) ) ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}
}
