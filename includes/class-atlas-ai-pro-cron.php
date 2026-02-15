<?php
/**
 * Pro cron jobs — aggregate affinities and abandoned checkout detection.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Cron
 */
class AtlasAI_Pro_Cron {

	/**
	 * Add Pro cron schedules to PersonaFlow's schedule list.
	 *
	 * Hooked to `atlas_ai_personaflow_cron_schedules` filter.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Extended schedules.
	 */
	public function add_pro_schedules( $schedules ) {
		$schedules[] = array(
			'hook'     => 'atlas_ai_pro_aggregate_affinities',
			'interval' => 'sixhourly',
			'callback' => array( $this, 'aggregate_affinities' ),
		);

		$schedules[] = array(
			'hook'     => 'atlas_ai_pro_abandoned_checkout',
			'interval' => 'hourly',
			'callback' => array( $this, 'detect_abandoned_checkouts' ),
		);

		return $schedules;
	}

	/**
	 * Schedule Pro cron events.
	 */
	public function schedule_events() {
		// Register custom interval.
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

		// Schedule aggregate affinities (6-hourly).
		if ( ! wp_next_scheduled( 'atlas_ai_pro_aggregate_affinities' ) ) {
			wp_schedule_event( time(), 'sixhourly', 'atlas_ai_pro_aggregate_affinities' );
		}
		add_action( 'atlas_ai_pro_aggregate_affinities', array( $this, 'aggregate_affinities' ) );

		// Schedule abandoned checkout detection (hourly).
		if ( ! wp_next_scheduled( 'atlas_ai_pro_abandoned_checkout' ) ) {
			wp_schedule_event( time(), 'hourly', 'atlas_ai_pro_abandoned_checkout' );
		}
		add_action( 'atlas_ai_pro_abandoned_checkout', array( $this, 'detect_abandoned_checkouts' ) );
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Extended schedules.
	 */
	public function add_cron_intervals( $schedules ) {
		if ( ! isset( $schedules['sixhourly'] ) ) {
			$schedules['sixhourly'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 6 hours', 'smart-local-ai-pro' ),
			);
		}
		return $schedules;
	}

	/**
	 * Aggregate category/author/tag affinity scores per visitor.
	 *
	 * Pre-computes affinity data for faster recommendation filtering.
	 */
	public function aggregate_affinities() {
		global $wpdb;

		// Get visitors with significant activity (10+ events in last 7 days).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$active_visitors = $wpdb->get_col(
			"SELECT DISTINCT visitor_hash
			FROM {$wpdb->prefix}atlasai_events
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			GROUP BY visitor_hash
			HAVING COUNT(*) >= 10
			LIMIT 100"
		);

		if ( empty( $active_visitors ) ) {
			return;
		}

		foreach ( $active_visitors as $visitor_hash ) {
			$this->compute_visitor_affinities( $visitor_hash );
		}
	}

	/**
	 * Compute affinities for a single visitor.
	 *
	 * @param string $visitor_hash Visitor hash.
	 */
	private function compute_visitor_affinities( $visitor_hash ) {
		global $wpdb;

		// Get weighted post interactions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$interactions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, SUM(weight) as total_weight
				FROM {$wpdb->prefix}atlasai_events
				WHERE visitor_hash = %s
				AND post_id > 0
				AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
				GROUP BY post_id
				ORDER BY total_weight DESC
				LIMIT 50",
				$visitor_hash
			)
		);

		if ( empty( $interactions ) ) {
			return;
		}

		// Aggregate by category.
		$category_affinities = array();
		$author_affinities   = array();

		foreach ( $interactions as $row ) {
			$post_id = (int) $row->post_id;
			$weight  = (float) $row->total_weight;

			// Categories.
			$categories = wp_get_post_categories( $post_id );
			foreach ( $categories as $cat_id ) {
				if ( ! isset( $category_affinities[ $cat_id ] ) ) {
					$category_affinities[ $cat_id ] = 0;
				}
				$category_affinities[ $cat_id ] += $weight;
			}

			// Author.
			$author_id = get_post_field( 'post_author', $post_id );
			if ( $author_id ) {
				if ( ! isset( $author_affinities[ $author_id ] ) ) {
					$author_affinities[ $author_id ] = 0;
				}
				$author_affinities[ $author_id ] += $weight;
			}
		}

		// Store as transient (expires in 6 hours).
		$affinities = array(
			'categories' => $category_affinities,
			'authors'    => $author_affinities,
			'computed'   => current_time( 'mysql' ),
		);

		set_transient( 'atlas_ai_pro_affinities_' . $visitor_hash, $affinities, 6 * HOUR_IN_SECONDS );
	}

	/**
	 * Detect abandoned checkouts and store events.
	 *
	 * Requires WooCommerce to be active.
	 */
	public function detect_abandoned_checkouts() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		global $wpdb;

		// Find checkout_start events without a corresponding checkout_complete
		// within 1 hour (considered abandoned).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$abandoned = $wpdb->get_results(
			"SELECT cs.visitor_hash, cs.post_id, cs.session_id
			FROM {$wpdb->prefix}atlasai_events cs
			WHERE cs.event_type = 'checkout_start'
			AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
			AND cs.created_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}atlasai_events cc
				WHERE cc.visitor_hash = cs.visitor_hash
				AND cc.session_id = cs.session_id
				AND cc.event_type IN ('checkout_complete', 'purchase_complete')
				AND cc.created_at >= cs.created_at
			)
			GROUP BY cs.visitor_hash, cs.post_id, cs.session_id
			LIMIT 50"
		);

		if ( empty( $abandoned ) ) {
			return;
		}

		$plugin  = Smart_Local_AI::get_instance();
		$pf      = $plugin->get_module( 'personaflow' );
		$tracker = $pf ? $pf->get_tracker() : null;

		if ( ! $tracker ) {
			return;
		}

		foreach ( $abandoned as $row ) {
			$tracker->store_events(
				array(
					array(
						'visitor_hash' => $row->visitor_hash,
						'session_id'   => $row->session_id,
						'event_type'   => 'checkout_abandon',
						'post_id'      => (int) $row->post_id,
						'event_value'  => 0,
						'weight'       => -5.0,
						'meta'         => null,
					),
				)
			);
		}
	}
}
