<?php
/**
 * One-shot admin notices and form input carried across the post/redirect/get cycle.
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

defined( 'ABSPATH' ) || exit;

class Notice {

	/** @var array|null */
	private static $current = null;

	private static function key() {
		return 'rar_iaa_notice_' . get_current_user_id();
	}

	public static function set( $type, $message, array $fields = array(), array $old = array() ) {
		set_transient( self::key(), array( 'type' => $type, 'message' => $message, 'fields' => $fields, 'old' => $old ), 120 );
	}

	private static function load() {
		if ( null === self::$current ) {
			$v             = get_transient( self::key() );
			self::$current = is_array( $v ) ? $v : array();
			if ( $v ) {
				delete_transient( self::key() );
			}
		}
		return self::$current;
	}

	public static function render() {
		$n = self::load();
		if ( empty( $n['message'] ) ) {
			return;
		}
		$class = 'success' === $n['type'] ? 'notice-success' : ( 'warning' === $n['type'] ? 'notice-warning' : 'notice-error' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $n['message'] ) . '</p>';
		if ( ! empty( $n['fields'] ) ) {
			echo '<ul class="iaa-error-list">';
			foreach ( $n['fields'] as $field => $msg ) {
				echo '<li><a href="#iaa-f-' . esc_attr( $field ) . '">' . esc_html( $field ) . '</a>: ' . esc_html( $msg ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	public static function field_error( $field ) {
		$n = self::load();
		return isset( $n['fields'][ $field ] ) ? $n['fields'][ $field ] : '';
	}

	public static function old( $field, $default = '' ) {
		$n = self::load();
		return isset( $n['old'][ $field ] ) ? $n['old'][ $field ] : $default;
	}

	public static function has_old() {
		$n = self::load();
		return ! empty( $n['old'] );
	}
}
