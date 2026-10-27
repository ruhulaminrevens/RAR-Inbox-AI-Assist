<?php
/**
 * Knowledge base entries and versions with maker-checker approval (FR-15, Schema §4.1–4.2, §8).
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

use RarIaa\Audit;
use RarIaa\Capabilities;
use RarIaa\Db;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class KbRepository {

	const CATEGORIES = array( 'hours', 'contact', 'ordering', 'delivery', 'payment', 'product_info', 'policy', 'greeting', 'other' );

	public static function category_labels() {
		return array(
			'hours'        => 'Office / shop hours',
			'contact'      => 'Contact',
			'ordering'     => 'Ordering',
			'delivery'     => 'Delivery',
			'payment'      => 'Payment',
			'product_info' => 'Product information',
			'policy'       => 'Policy',
			'greeting'     => 'Greeting',
			'other'        => 'Other',
		);
	}

	/** Validate editor input. Returns array( clean, errors ). */
	public static function validate( array $in ) {
		$e = array();
		$c = array();

		$c['title'] = trim( sanitize_text_field( isset( $in['title'] ) ? $in['title'] : '' ) );
		if ( '' === $c['title'] || Util::strlen( $c['title'] ) > 120 ) {
			$e['title'] = 'Title is required (max 120 characters).';
		}
		$c['category'] = isset( $in['category'] ) ? (string) $in['category'] : '';
		if ( ! in_array( $c['category'], self::CATEGORIES, true ) ) {
			$e['category'] = 'Choose a category.';
		}
		$kw = array();
		foreach ( explode( ',', isset( $in['keywords'] ) ? (string) $in['keywords'] : '' ) as $k ) {
			$k = Util::lower( trim( sanitize_text_field( $k ) ) );
			if ( '' !== $k ) {
				$kw[] = $k;
			}
		}
		$kw             = array_values( array_unique( $kw ) );
		$c['keywords']  = implode( ',', $kw );
		if ( count( $kw ) > 30 || strlen( $c['keywords'] ) > 1000 ) {
			$e['keywords'] = 'Maximum 30 keywords (1000 characters).';
		}
		$c['answer_bn'] = trim( sanitize_textarea_field( isset( $in['answer_bn'] ) ? $in['answer_bn'] : '' ) );
		if ( '' === $c['answer_bn'] || Util::strlen( $c['answer_bn'] ) > 2000 ) {
			$e['answer_bn'] = 'Bangla answer is required (max 2000 characters).';
		}
		$c['answer_en'] = trim( sanitize_textarea_field( isset( $in['answer_en'] ) ? $in['answer_en'] : '' ) );
		if ( Util::strlen( $c['answer_en'] ) > 2000 ) {
			$e['answer_en'] = 'Max 2000 characters.';
		}
		$c['answer_en']      = '' === $c['answer_en'] ? null : $c['answer_en'];
		$c['effective_from'] = isset( $in['effective_from'] ) && '' !== $in['effective_from'] ? (string) $in['effective_from'] : Db::dhaka_date();
		$c['effective_to']   = isset( $in['effective_to'] ) && '' !== $in['effective_to'] ? (string) $in['effective_to'] : null;
		foreach ( array( 'effective_from', 'effective_to' ) as $d ) {
			if ( null !== $c[ $d ] && ! self::valid_date( $c[ $d ] ) ) {
				$e[ $d ] = 'Use the format YYYY-MM-DD.';
			}
		}
		if ( empty( $e['effective_from'] ) && empty( $e['effective_to'] ) && null !== $c['effective_to'] && $c['effective_to'] < $c['effective_from'] ) {
			$e['effective_to'] = 'End date must be on or after the start date.';
		}
		$c['note'] = trim( sanitize_textarea_field( isset( $in['note'] ) ? $in['note'] : '' ) );
		if ( Util::strlen( $c['note'] ) > 500 ) {
			$e['note'] = 'Max 500 characters.';
		}
		$c['note'] = '' === $c['note'] ? null : $c['note'];
		return array( $c, $e );
	}

	private static function valid_date( $d ) {
		$dt = \DateTime::createFromFormat( 'Y-m-d', $d );
		return $dt && $dt->format( 'Y-m-d' ) === $d;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'kb_entries' ) . ' WHERE id = %d', $id ) );
	}

	public static function version( $id ) {
		global $wpdb;
		return $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'kb_versions' ) . ' WHERE id = %d', $id ) ) : null;
	}

	public static function versions( $entry_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'kb_versions' ) . ' WHERE entry_id = %d ORDER BY version_no DESC', $entry_id ) );
	}

	/** Version being edited (draft if any, else approved, else latest). */
	public static function current_version( $entry ) {
		if ( $entry->draft_version_id ) {
			return self::version( $entry->draft_version_id );
		}
		if ( $entry->approved_version_id ) {
			return self::version( $entry->approved_version_id );
		}
		$v = self::versions( $entry->id );
		return $v ? $v[0] : null;
	}

	/** Create entry + version 1 (draft). Returns entry id or WP_Error. */
	public static function create( array $in, $user_id ) {
		global $wpdb;
		list( $c, $e ) = self::validate( $in );
		if ( $e ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Please fix the errors.', array( 'fields' => $e ) );
		}
		$now = Db::now();
		return Db::transaction(
			function () use ( $wpdb, $c, $user_id, $now ) {
				$wpdb->insert(
					Db::t( 'kb_entries' ),
					array(
						'category'   => $c['category'],
						'status'     => 'draft',
						'created_by' => (int) $user_id,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);
				$entry_id = (int) $wpdb->insert_id;
				$vid      = self::insert_version( $entry_id, 1, $c, $user_id );
				$wpdb->update( Db::t( 'kb_entries' ), array( 'draft_version_id' => $vid ), array( 'id' => $entry_id ) );
				Audit::record( 'kb.create', 'kb_entry', $entry_id, null, $c, $user_id );
				return $entry_id;
			}
		);
	}

	private static function insert_version( $entry_id, $no, array $c, $user_id ) {
		global $wpdb;
		$wpdb->insert(
			Db::t( 'kb_versions' ),
			array(
				'entry_id'       => (int) $entry_id,
				'version_no'     => (int) $no,
				'state'          => 'draft',
				'title'          => $c['title'],
				'keywords'       => $c['keywords'],
				'answer_bn'      => $c['answer_bn'],
				'answer_en'      => $c['answer_en'],
				'effective_from' => $c['effective_from'],
				'effective_to'   => $c['effective_to'],
				'note'           => $c['note'],
				'edited_by'      => (int) $user_id,
				'edited_at'      => Db::now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/** Edit: updates the draft version or creates a new draft version from an approved entry. */
	public static function update( $entry_id, array $in, $user_id, $row_version ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry ) {
			return new \WP_Error( 'NOT_FOUND', 'Entry not found.' );
		}
		if ( 'archived' === $entry->status ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Restore the entry before editing it.' );
		}
		list( $c, $e ) = self::validate( $in );
		if ( $e ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Please fix the errors.', array( 'fields' => $e ) );
		}
		$before = self::current_version( $entry );
		return Db::transaction(
			function () use ( $wpdb, $entry, $c, $user_id, $row_version, $before ) {
				$t       = Db::t( 'kb_entries' );
				$changed = $wpdb->query(
					$wpdb->prepare( "UPDATE {$t} SET category = %s, row_version = row_version + 1, updated_at = %s WHERE id = %d AND row_version = %d", $c['category'], Db::now(), $entry->id, $row_version )
				);
				if ( 1 !== (int) $changed ) {
					throw new ConflictException( 'Someone else changed this entry. Reload and try again.' );
				}
				if ( $entry->draft_version_id ) {
					$wpdb->update(
						Db::t( 'kb_versions' ),
						array(
							'title'          => $c['title'],
							'keywords'       => $c['keywords'],
							'answer_bn'      => $c['answer_bn'],
							'answer_en'      => $c['answer_en'],
							'effective_from' => $c['effective_from'],
							'effective_to'   => $c['effective_to'],
							'note'           => $c['note'],
							'edited_by'      => (int) $user_id,
							'edited_at'      => Db::now(),
						),
						array( 'id' => (int) $entry->draft_version_id )
					);
				} else {
					$max = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version_no) FROM ' . Db::t( 'kb_versions' ) . ' WHERE entry_id = %d', $entry->id ) );
					$vid = self::insert_version( $entry->id, $max + 1, $c, $user_id );
					$wpdb->update( $t, array( 'draft_version_id' => $vid, 'pending_review' => 0 ), array( 'id' => (int) $entry->id ) );
				}
				Audit::record( 'kb.edit', 'kb_entry', $entry->id, $before, $c, $user_id );
				return (int) $entry->id;
			}
		);
	}

	public static function submit( $entry_id, $user_id ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry || ! $entry->draft_version_id ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'There is no draft to submit.' );
		}
		$wpdb->update( Db::t( 'kb_entries' ), array( 'pending_review' => 1, 'updated_at' => Db::now() ), array( 'id' => (int) $entry_id ) );
		Audit::record( 'kb.submit', 'kb_entry', $entry_id, null, array( 'version_id' => (int) $entry->draft_version_id ), $user_id );
		return true;
	}

	/** Approve the draft version (maker-checker). */
	public static function approve( $entry_id, $approver_id, $row_version ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry || ! $entry->draft_version_id ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'There is no draft version to approve.' );
		}
		$draft = self::version( $entry->draft_version_id );
		if ( (int) $draft->edited_by === (int) $approver_id && Capabilities::admin_count() > 1 ) {
			return new \WP_Error( 'FORBIDDEN', 'You edited this version, so another approver must approve it.' );
		}
		return Db::transaction(
			function () use ( $wpdb, $entry, $draft, $approver_id, $row_version ) {
				$t       = Db::t( 'kb_entries' );
				$changed = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$t} SET status = 'approved', approved_version_id = %d, draft_version_id = NULL, pending_review = 0, row_version = row_version + 1, updated_at = %s WHERE id = %d AND row_version = %d AND draft_version_id = %d",
						$draft->id,
						Db::now(),
						$entry->id,
						$row_version,
						$draft->id
					)
				);
				if ( 1 !== (int) $changed ) {
					throw new ConflictException( 'Someone else changed this entry. Reload and try again.' );
				}
				if ( $entry->approved_version_id ) {
					$wpdb->update( Db::t( 'kb_versions' ), array( 'state' => 'superseded' ), array( 'id' => (int) $entry->approved_version_id ) );
				}
				$wpdb->update(
					Db::t( 'kb_versions' ),
					array( 'state' => 'approved', 'approved_by' => (int) $approver_id, 'approved_at' => Db::now() ),
					array( 'id' => (int) $draft->id )
				);
				Audit::record( 'kb.approve', 'kb_entry', $entry->id, array( 'approved_version_id' => $entry->approved_version_id ), array( 'approved_version_id' => (int) $draft->id ), $approver_id );
				return true;
			}
		);
	}

	public static function archive( $entry_id, $user_id ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry || 'archived' === $entry->status ) {
			return new \WP_Error( 'NOT_FOUND', 'Entry not found.' );
		}
		$wpdb->update( Db::t( 'kb_entries' ), array( 'status' => 'archived', 'pending_review' => 0, 'updated_at' => Db::now() ), array( 'id' => (int) $entry_id ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Db::t( 'kb_entries' ) . ' SET row_version = row_version + 1 WHERE id = %d', $entry_id ) );
		Audit::record( 'kb.archive', 'kb_entry', $entry_id, array( 'status' => $entry->status ), array( 'status' => 'archived' ), $user_id );
		return true;
	}

	/** Restore an archived entry as a new draft copied from its latest version. */
	public static function restore( $entry_id, $user_id ) {
		global $wpdb;
		$entry = self::get( $entry_id );
		if ( ! $entry || 'archived' !== $entry->status ) {
			return new \WP_Error( 'NOT_FOUND', 'Archived entry not found.' );
		}
		$latest = self::versions( $entry_id );
		$src    = $latest[0];
		return Db::transaction(
			function () use ( $wpdb, $entry, $src, $user_id ) {
				if ( $entry->approved_version_id ) {
					$wpdb->update( Db::t( 'kb_versions' ), array( 'state' => 'superseded' ), array( 'id' => (int) $entry->approved_version_id ) );
				}
				if ( $entry->draft_version_id ) {
					$wpdb->update( Db::t( 'kb_versions' ), array( 'state' => 'superseded' ), array( 'id' => (int) $entry->draft_version_id ) );
				}
				$vid = self::insert_version(
					$entry->id,
					(int) $src->version_no + 1,
					array(
						'title'          => $src->title,
						'keywords'       => $src->keywords,
						'answer_bn'      => $src->answer_bn,
						'answer_en'      => $src->answer_en,
						'effective_from' => $src->effective_from,
						'effective_to'   => $src->effective_to,
						'note'           => $src->note,
					),
					$user_id
				);
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE ' . Db::t( 'kb_entries' ) . " SET status = 'draft', approved_version_id = NULL, draft_version_id = %d, pending_review = 0, row_version = row_version + 1, updated_at = %s WHERE id = %d",
						$vid,
						Db::now(),
						$entry->id
					)
				);
				Audit::record( 'kb.restore', 'kb_entry', $entry->id, array( 'status' => 'archived' ), array( 'status' => 'draft', 'version_id' => $vid ), $user_id );
				return true;
			}
		);
	}

	/** Admin list. */
	public static function search_admin( $args ) {
		global $wpdb;
		$e     = Db::t( 'kb_entries' );
		$v     = Db::t( 'kb_versions' );
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'draft', 'approved', 'archived' ), true ) ) {
			$where[] = 'e.status = %s';
			$vals[]  = $args['status'];
		}
		if ( ! empty( $args['category'] ) && in_array( $args['category'], self::CATEGORIES, true ) ) {
			$where[] = 'e.category = %s';
			$vals[]  = $args['category'];
		}
		if ( ! empty( $args['pending'] ) ) {
			$where[] = 'e.pending_review = 1';
		}
		if ( ! empty( $args['s'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['s'] ) . '%';
			$where[] = '(v.title LIKE %s OR v.keywords LIKE %s OR v.answer_bn LIKE %s OR v.answer_en LIKE %s)';
			array_push( $vals, $like, $like, $like, $like );
		}
		$page   = max( 1, (int) ( isset( $args['paged'] ) ? $args['paged'] : 1 ) );
		$order  = ( isset( $args['orderby'] ) && 'title' === $args['orderby'] ) ? 'v.title ASC' : 'e.updated_at DESC';
		$join   = "FROM {$e} e LEFT JOIN {$v} v ON v.id = COALESCE(e.draft_version_id, e.approved_version_id)";
		$w      = implode( ' AND ', $where );
		$count  = (int) $wpdb->get_var( $vals ? $wpdb->prepare( "SELECT COUNT(*) {$join} WHERE {$w}", $vals ) : "SELECT COUNT(*) {$join} WHERE {$w}" ); // phpcs:ignore
		$sql    = "SELECT e.*, v.title, v.effective_from, v.effective_to, v.edited_by, v.approved_by {$join} WHERE {$w} ORDER BY {$order} LIMIT 20 OFFSET " . ( ( $page - 1 ) * 20 );
		$rows   = $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql ); // phpcs:ignore
		return array( $rows, $count );
	}

	/** Approved, in-date versions (the facts that may be used). */
	public static function serving() {
		global $wpdb;
		$today = Db::dhaka_date();
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.id AS entry_id, e.category, v.* FROM ' . Db::t( 'kb_entries' ) . ' e JOIN ' . Db::t( 'kb_versions' ) . " v ON v.id = e.approved_version_id
				 WHERE e.status = 'approved' AND v.state = 'approved' AND v.effective_from <= %s AND (v.effective_to IS NULL OR v.effective_to >= %s)",
				$today,
				$today
			)
		);
	}

	public static function count_serving() {
		return count( self::serving() );
	}

	/** Entries expiring within $days. */
	public static function expiring( $days = 7 ) {
		$limit = Db::dhaka_date( time() + $days * DAY_IN_SECONDS );
		$out   = array();
		foreach ( self::serving() as $row ) {
			if ( $row->effective_to && $row->effective_to <= $limit ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/** Quick-answer search (approved, in-date only). */
	public static function search_serving( $q, $limit = 10 ) {
		$tokens = Util::tokens( $q );
		$scored = array();
		foreach ( self::serving() as $row ) {
			$hay   = Util::normalize( $row->title . ' ' . str_replace( ',', ' ', $row->keywords ) . ' ' . $row->answer_bn . ' ' . $row->answer_en );
			$score = 0;
			foreach ( $tokens as $t ) {
				if ( '' !== $t && false !== strpos( ' ' . $hay . ' ', $t ) ) {
					$score++;
				}
			}
			if ( $score > 0 ) {
				$scored[] = array( $score, $row );
			}
		}
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b[0] - $a[0];
			}
		);
		return array_slice( array_map( static function ( $s ) { return $s[1]; }, $scored ), 0, $limit );
	}

	public static function mark_used( array $entry_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $entry_ids ) ) );
		if ( ! $ids ) {
			return;
		}
		$in = implode( ',', $ids );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Db::t( 'kb_entries' ) . " SET use_count = use_count + 1, last_used_at = %s WHERE id IN ({$in})", Db::now() ) ); // phpcs:ignore
	}
}
