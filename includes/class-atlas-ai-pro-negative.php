<?php
/**
 * Negative signal processing and user exclusions.
 *
 * Manages hide_post, mute_author, block_author, dismiss_category
 * exclusions and applies penalties to recommendation scoring.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Negative
 */
class AtlasAI_Pro_Negative {

	/**
	 * Negative signal types that trigger exclusions.
	 *
	 * @var array
	 */
	private $exclusion_signals = array(
		'hide_post'    => 'hide_post',
		'mute_author'  => 'mute_author',
		'block_author' => 'block_author',
		'dismiss'      => 'dismiss_category',
	);

	/**
	 * Cached exclusions for the current request.
	 *
	 * @var array|null
	 */
	private $exclusion_cache = null;

	/**
	 * Process an event after it's stored.
	 *
	 * Hooked to `atlas_ai_personaflow_event_stored` action.
	 * Detects negative signal types and creates exclusion entries.
	 *
	 * @param string $event_type   Signal type.
	 * @param int    $post_id      Post ID.
	 * @param string $visitor_hash Visitor hash.
	 * @param float  $weight       Signal weight.
	 */
	public function process_event( $event_type, $post_id, $visitor_hash, $weight ) {
		if ( ! isset( $this->exclusion_signals[ $event_type ] ) ) {
			return;
		}

		$exclusion_type = $this->exclusion_signals[ $event_type ];

		switch ( $exclusion_type ) {
			case 'hide_post':
				$this->add_exclusion( $visitor_hash, 'hide_post', $post_id );
				break;

			case 'mute_author':
			case 'block_author':
				$author_id = get_post_field( 'post_author', $post_id );
				if ( $author_id ) {
					$this->add_exclusion( $visitor_hash, $exclusion_type, (int) $author_id );
				}
				break;

			case 'dismiss_category':
				$categories = wp_get_post_categories( $post_id );
				if ( ! empty( $categories ) ) {
					// Dismiss the primary category.
					$this->add_exclusion( $visitor_hash, 'dismiss_category', $categories[0] );
				}
				break;
		}

		// Clear cache.
		$this->exclusion_cache = null;
	}

	/**
	 * Apply exclusion penalty to a recommendation score.
	 *
	 * Hooked to `atlas_ai_personaflow_recommendation_query` filter.
	 *
	 * @param float  $score        Hybrid score.
	 * @param int    $pid          Post ID.
	 * @param string $visitor_hash Visitor hash.
	 * @return float Modified score (-999 if excluded).
	 */
	public function apply_exclusion_penalty( $score, $pid, $visitor_hash ) {
		$exclusions = $this->get_exclusions_for_visitor( $visitor_hash );

		if ( empty( $exclusions ) ) {
			return $score;
		}

		// Check if post is directly hidden.
		foreach ( $exclusions as $exclusion ) {
			if ( 'hide_post' === $exclusion['exclusion_type'] && (int) $exclusion['target_id'] === $pid ) {
				return -999.0;
			}
		}

		// Check if post's author is muted or blocked.
		$author_id = get_post_field( 'post_author', $pid );
		if ( $author_id ) {
			foreach ( $exclusions as $exclusion ) {
				if (
					in_array( $exclusion['exclusion_type'], array( 'mute_author', 'block_author' ), true )
					&& (int) $exclusion['target_id'] === (int) $author_id
				) {
					return -999.0;
				}
			}
		}

		// Check if post's category is dismissed.
		$categories = wp_get_post_categories( $pid );
		if ( ! empty( $categories ) ) {
			foreach ( $exclusions as $exclusion ) {
				if ( 'dismiss_category' === $exclusion['exclusion_type'] ) {
					if ( in_array( (int) $exclusion['target_id'], $categories, true ) ) {
						// Apply heavy penalty but don't completely exclude.
						return $score * 0.1;
					}
				}
			}
		}

		return $score;
	}

	/**
	 * Filter final recommendation results.
	 *
	 * Hooked to `atlas_ai_personaflow_recommendation_results` filter.
	 * Removes results with extreme negative scores.
	 *
	 * @param array  $results      Recommendation array.
	 * @param string $visitor_hash Visitor hash.
	 * @return array Filtered results.
	 */
	public function filter_results( $results, $visitor_hash ) {
		return array_values(
			array_filter(
				$results,
				function ( $item ) {
					// Remove items flagged with exclusion penalty.
					return ! isset( $item['score'] ) || $item['score'] > -900;
				}
			)
		);
	}

	/**
	 * Add an exclusion entry.
	 *
	 * @param string $visitor_hash   Visitor hash.
	 * @param string $exclusion_type Exclusion type (hide_post, mute_author, block_author, dismiss_category).
	 * @param int    $target_id      Target ID (post ID, author ID, or category ID).
	 * @return bool Whether the exclusion was added.
	 */
	public function add_exclusion( $visitor_hash, $exclusion_type, $target_id ) {
		global $wpdb;

		$allowed_types = array( 'hide_post', 'mute_author', 'block_author', 'dismiss_category' );
		if ( ! in_array( $exclusion_type, $allowed_types, true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->replace(
			$wpdb->prefix . 'atlasai_user_exclusions',
			array(
				'visitor_hash'   => sanitize_text_field( $visitor_hash ),
				'user_id'        => get_current_user_id(),
				'exclusion_type' => $exclusion_type,
				'target_id'      => absint( $target_id ),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);

		// Clear cache.
		$this->exclusion_cache = null;

		return false !== $result;
	}

	/**
	 * Remove an exclusion by ID.
	 *
	 * @param int    $exclusion_id Exclusion row ID.
	 * @param string $visitor_hash Visitor hash for ownership verification.
	 * @return bool Whether the exclusion was removed.
	 */
	public function remove_exclusion( $exclusion_id, $visitor_hash ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$wpdb->prefix . 'atlasai_user_exclusions',
			array(
				'id'           => absint( $exclusion_id ),
				'visitor_hash' => sanitize_text_field( $visitor_hash ),
			),
			array( '%d', '%s' )
		);

		$this->exclusion_cache = null;

		return false !== $result && $result > 0;
	}

	/**
	 * Get all exclusions for a visitor.
	 *
	 * @param string $visitor_hash Visitor hash.
	 * @return array Exclusion rows.
	 */
	public function get_exclusions( $visitor_hash ) {
		return $this->get_exclusions_for_visitor( $visitor_hash );
	}

	/**
	 * Get exclusion count for a visitor.
	 *
	 * @param string $visitor_hash Visitor hash.
	 * @return int Count.
	 */
	public function get_exclusion_count( $visitor_hash ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}atlasai_user_exclusions WHERE visitor_hash = %s",
				$visitor_hash
			)
		);
	}

	/**
	 * Internal: get exclusions with request-level caching.
	 *
	 * @param string $visitor_hash Visitor hash.
	 * @return array Exclusion rows.
	 */
	private function get_exclusions_for_visitor( $visitor_hash ) {
		if ( null !== $this->exclusion_cache ) {
			return $this->exclusion_cache;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, exclusion_type, target_id, created_at
				FROM {$wpdb->prefix}atlasai_user_exclusions
				WHERE visitor_hash = %s
				ORDER BY created_at DESC",
				$visitor_hash
			),
			ARRAY_A
		);

		$this->exclusion_cache = $results ? $results : array();

		return $this->exclusion_cache;
	}
}
