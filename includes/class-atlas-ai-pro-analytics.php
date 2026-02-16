<?php
/**
 * Pro-only analytics REST API endpoints.
 *
 * Provides engagement funnel, WooCommerce analytics, session insights,
 * extended content analytics, and extended timeline endpoints.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Analytics
 *
 * Registers and handles Pro-only analytics REST API endpoints for PersonaFlow.
 * No period clamping — full date range support for Pro users.
 */
class AtlasAI_Pro_Analytics {

	/**
	 * Maximum content items for extended content endpoint.
	 *
	 * @var int
	 */
	const CONTENT_LIMIT = 25;

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		$date_args = array(
			'period' => array(
				'type'              => 'string',
				'default'           => '30d',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'start'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end'    => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		// Engagement funnel.
		register_rest_route(
			AtlasAI_REST_API::NAMESPACE,
			'/personaflow/analytics/funnel',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_funnel' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'admin_permission_check' ),
				'args'                => $date_args,
			)
		);

		// WooCommerce analytics.
		register_rest_route(
			AtlasAI_REST_API::NAMESPACE,
			'/personaflow/analytics/woocommerce',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_woocommerce' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'admin_permission_check' ),
				'args'                => $date_args,
			)
		);

		// Session insights.
		register_rest_route(
			AtlasAI_REST_API::NAMESPACE,
			'/personaflow/analytics/sessions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sessions' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'admin_permission_check' ),
				'args'                => $date_args,
			)
		);

		// Extended content analytics.
		register_rest_route(
			AtlasAI_REST_API::NAMESPACE,
			'/personaflow/analytics/content-extended',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_content_extended' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'admin_permission_check' ),
				'args'                => array_merge(
					$date_args,
					array(
						'sort' => array(
							'type'              => 'string',
							'default'           => 'signals',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return in_array( $value, array( 'signals', 'visitors', 'dwell', 'read_rate' ), true );
							},
						),
					)
				),
			)
		);

		// Extended timeline.
		register_rest_route(
			AtlasAI_REST_API::NAMESPACE,
			'/personaflow/analytics/timeline-extended',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_timeline_extended' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'admin_permission_check' ),
				'args'                => $date_args,
			)
		);
	}

	/**
	 * Parse date range from request parameters.
	 *
	 * Supports preset periods (7d, 10d, 30d, 60d, 90d) and custom date ranges.
	 * Pro tier: no period clamping.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array [ $start_date, $end_date ] as Y-m-d strings.
	 */
	private function get_date_range( $request ) {
		$start_param  = $request->get_param( 'start' );
		$end_param    = $request->get_param( 'end' );
		$period_param = $request->get_param( 'period' );

		$now = current_time( 'timestamp' );

		if ( ! empty( $start_param ) && ! empty( $end_param ) ) {
			// Custom date range.
			$start_ts = strtotime( $start_param );
			$end_ts   = strtotime( $end_param );

			if ( false === $start_ts || false === $end_ts || $start_ts > $end_ts ) {
				// Fallback to 30 days if invalid.
				$end_date   = gmdate( 'Y-m-d', $now );
				$start_date = gmdate( 'Y-m-d', strtotime( '-29 days', $now ) );
			} else {
				$start_date = gmdate( 'Y-m-d', $start_ts );
				$end_date   = gmdate( 'Y-m-d', $end_ts );
			}
		} else {
			// Preset period.
			$days = 30;
			if ( preg_match( '/^(\d+)d$/', $period_param, $matches ) ) {
				$days = (int) $matches[1];
			}

			$end_date   = gmdate( 'Y-m-d', $now );
			$start_date = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', $now ) );
		}

		// No period clamping for Pro.
		return array( $start_date, $end_date );
	}

	/**
	 * GET /personaflow/analytics/funnel
	 *
	 * Returns engagement funnel steps with counts for each stage:
	 * Page Views -> 15s+ Dwell Time -> 50%+ Scroll Depth -> Read Completion -> Rec Click / Action.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_funnel( $request ) {
		global $wpdb;

		list( $start_date, $end_date ) = $this->get_date_range( $request );

		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = $end_date . ' 23:59:59';

		$table = $wpdb->prefix . 'atlasai_events';

		// Step 1: Page Views.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$page_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'page_view' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Step 2: 15s+ Dwell Time.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$dwell_15s = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'dwell_time' AND event_value >= 15 AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Step 3: 50%+ Scroll Depth.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$scroll_50 = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'scroll_depth' AND event_value >= 50 AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Step 4: Read Completion.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$read_completion = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'read_completion' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Step 5: Rec Click / Action.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rec_action = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type IN ('related_post_click', 'like', 'bookmark_save') AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		$steps = array(
			array(
				'label' => 'Page Views',
				'count' => $page_views,
			),
			array(
				'label' => '15s+ Dwell Time',
				'count' => $dwell_15s,
			),
			array(
				'label' => '50%+ Scroll Depth',
				'count' => $scroll_50,
			),
			array(
				'label' => 'Read Completion',
				'count' => $read_completion,
			),
			array(
				'label' => 'Rec Click / Action',
				'count' => $rec_action,
			),
		);

		return AtlasAI_REST_API::success(
			array(
				'steps' => $steps,
			)
		);
	}

	/**
	 * GET /personaflow/analytics/woocommerce
	 *
	 * Returns WooCommerce funnel, cart abandonment rate, average order signals,
	 * and top products by signal count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_woocommerce( $request ) {
		global $wpdb;

		list( $start_date, $end_date ) = $this->get_date_range( $request );

		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = $end_date . ' 23:59:59';

		$table = $wpdb->prefix . 'atlasai_events';

		// Funnel Step 1: Product Views.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$product_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'product_view' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Funnel Step 2: Add to Cart.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$add_to_cart = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'add_to_cart' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Funnel Step 3: Checkout Start.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$checkout_start = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = 'checkout_start' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// Funnel Step 4: Purchase Complete.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$purchase_complete = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type IN ('purchase_complete', 'checkout_complete') AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		$funnel = array(
			array(
				'label' => 'Product Views',
				'count' => $product_views,
			),
			array(
				'label' => 'Add to Cart',
				'count' => $add_to_cart,
			),
			array(
				'label' => 'Checkout Start',
				'count' => $checkout_start,
			),
			array(
				'label' => 'Purchase Complete',
				'count' => $purchase_complete,
			),
		);

		// Cart abandonment rate: 1 - (purchases / add_to_cart), clamped 0-1.
		$cart_abandonment_rate = 0.0;
		if ( $add_to_cart > 0 ) {
			$cart_abandonment_rate = 1.0 - ( $purchase_complete / $add_to_cart );
			$cart_abandonment_rate = max( 0.0, min( 1.0, round( $cart_abandonment_rate, 4 ) ) );
		}

		// Average order signals: AVG(total signals per purchasing visitor).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$avg_order_signals_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(signal_count) FROM (
					SELECT COUNT(*) AS signal_count
					FROM {$table}
					WHERE visitor_hash IN (
						SELECT DISTINCT visitor_hash
						FROM {$table}
						WHERE event_type IN ('purchase_complete', 'checkout_complete')
							AND created_at BETWEEN %s AND %s
					)
					AND created_at BETWEEN %s AND %s
					GROUP BY visitor_hash
				) AS purchasing_visitors",
				$start_datetime,
				$end_datetime,
				$start_datetime,
				$end_datetime
			)
		);
		$avg_order_signals = null !== $avg_order_signals_raw ? round( (float) $avg_order_signals_raw, 2 ) : 0.0;

		// Top products by signal count (top 10).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$top_products_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.post_id,
					p.post_title AS title,
					COUNT(*) AS signals
				FROM {$table} AS e
				INNER JOIN {$wpdb->posts} AS p ON e.post_id = p.ID
				WHERE e.post_id > 0
					AND e.created_at BETWEEN %s AND %s
					AND e.event_type IN (
						'product_view', 'add_to_cart', 'add_to_wishlist',
						'purchase_complete', 'checkout_complete', 'product_review',
						'product_rating', 'cross_sell_click', 'product_tab_switch'
					)
				GROUP BY e.post_id, p.post_title
				ORDER BY signals DESC
				LIMIT 10",
				$start_datetime,
				$end_datetime
			)
		);

		$top_products = array();

		if ( $top_products_raw ) {
			// Collect post IDs for batch purchase count query.
			$product_ids    = array();
			$product_map    = array();

			foreach ( $top_products_raw as $row ) {
				$pid              = (int) $row->post_id;
				$product_ids[]    = $pid;
				$product_map[ $pid ] = array(
					'post_id'   => $pid,
					'title'     => $row->title,
					'signals'   => (int) $row->signals,
					'purchases' => 0,
				);
			}

			// Batch query: purchase counts per product.
			$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$purchase_rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT post_id, COUNT(*) AS purchase_count
					FROM {$table}
					WHERE event_type IN ('purchase_complete', 'checkout_complete')
						AND post_id IN ({$placeholders})
						AND created_at BETWEEN %s AND %s
					GROUP BY post_id",
					array_merge( $product_ids, array( $start_datetime, $end_datetime ) )
				)
			);

			if ( $purchase_rows ) {
				foreach ( $purchase_rows as $row ) {
					$pid = (int) $row->post_id;
					if ( isset( $product_map[ $pid ] ) ) {
						$product_map[ $pid ]['purchases'] = (int) $row->purchase_count;
					}
				}
			}

			$top_products = array_values( $product_map );
		}

		return AtlasAI_REST_API::success(
			array(
				'funnel'                => $funnel,
				'cart_abandonment_rate' => $cart_abandonment_rate,
				'avg_order_signals'     => $avg_order_signals,
				'top_products'          => $top_products,
			)
		);
	}

	/**
	 * GET /personaflow/analytics/sessions
	 *
	 * Returns session insights: device breakdown, referral sources,
	 * average session depth, return visitor percentage, average signals per session,
	 * and total session count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_sessions( $request ) {
		global $wpdb;

		list( $start_date, $end_date ) = $this->get_date_range( $request );

		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = $end_date . ' 23:59:59';

		$table = $wpdb->prefix . 'atlasai_events';

		// Devices: from event_type='device_type', group by JSON meta 'device' field.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$device_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT JSON_UNQUOTE(JSON_EXTRACT(meta, '$.device')) AS device, COUNT(*) AS count
				FROM {$table}
				WHERE event_type = 'device_type'
					AND created_at BETWEEN %s AND %s
				GROUP BY device",
				$start_datetime,
				$end_datetime
			)
		);

		$devices = array(
			'desktop' => 0,
			'mobile'  => 0,
			'tablet'  => 0,
		);

		if ( $device_rows ) {
			foreach ( $device_rows as $row ) {
				$device_key = strtolower( $row->device );
				if ( isset( $devices[ $device_key ] ) ) {
					$devices[ $device_key ] = (int) $row->count;
				}
			}
		}

		// Referrals: from event_type='referral_source', group by JSON meta 'source' field.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$referral_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) AS source, COUNT(*) AS count
				FROM {$table}
				WHERE event_type = 'referral_source'
					AND created_at BETWEEN %s AND %s
				GROUP BY source",
				$start_datetime,
				$end_datetime
			)
		);

		$referrals = array(
			'organic'  => 0,
			'direct'   => 0,
			'social'   => 0,
			'referral' => 0,
			'email'    => 0,
			'internal' => 0,
		);

		if ( $referral_rows ) {
			foreach ( $referral_rows as $row ) {
				$source_key = strtolower( $row->source );
				if ( isset( $referrals[ $source_key ] ) ) {
					$referrals[ $source_key ] = (int) $row->count;
				}
			}
		}

		// Average session depth.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$avg_session_depth_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(event_value) FROM {$table} WHERE event_type = 'session_depth' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);
		$avg_session_depth = null !== $avg_session_depth_raw ? round( (float) $avg_session_depth_raw, 2 ) : 0.0;

		// Return visitor percentage: DISTINCT visitors with 'return_visitor' event / total DISTINCT visitors.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$return_visitors = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_hash) FROM {$table} WHERE event_type = 'return_visitor' AND created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_visitors = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor_hash) FROM {$table} WHERE created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		$return_visitor_pct = $total_visitors > 0
			? round( $return_visitors / $total_visitors, 4 )
			: 0.0;

		// Average signals per session: COUNT(*) / COUNT(DISTINCT session_id).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_events = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE created_at BETWEEN %s AND %s",
				$start_datetime,
				$end_datetime
			)
		);

		$avg_signals_per_session = $total_sessions > 0
			? round( $total_events / $total_sessions, 2 )
			: 0.0;

		return AtlasAI_REST_API::success(
			array(
				'devices'                 => $devices,
				'referrals'               => $referrals,
				'avg_session_depth'       => $avg_session_depth,
				'return_visitor_pct'      => $return_visitor_pct,
				'avg_signals_per_session' => $avg_signals_per_session,
				'total_sessions'          => $total_sessions,
			)
		);
	}

	/**
	 * GET /personaflow/analytics/content-extended
	 *
	 * Returns top 25 engaged content posts with full engagement metrics.
	 * Supports sorting by signals, visitors, dwell, or read_rate.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_content_extended( $request ) {
		global $wpdb;

		list( $start_date, $end_date ) = $this->get_date_range( $request );

		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = $end_date . ' 23:59:59';

		$sort  = $request->get_param( 'sort' );
		$limit = self::CONTENT_LIMIT;
		$table = $wpdb->prefix . 'atlasai_events';

		// Determine ORDER BY clause based on sort param.
		$order_by_map = array(
			'signals'   => 'total_signals DESC',
			'visitors'  => 'unique_visitors DESC',
			'dwell'     => 'avg_dwell_time DESC',
			'read_rate' => 'read_completion_rate DESC',
		);

		$order_by = isset( $order_by_map[ $sort ] ) ? $order_by_map[ $sort ] : 'total_signals DESC';

		// For dwell and read_rate sorting, we need a subquery approach.
		// Build common query for all posts with metrics.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$top_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					e.post_id,
					p.post_title AS title,
					COUNT(*) AS total_signals,
					COUNT(DISTINCT e.visitor_hash) AS unique_visitors,
					COALESCE(
						(SELECT AVG(e2.event_value)
						FROM {$table} AS e2
						WHERE e2.event_type = 'dwell_time'
							AND e2.post_id = e.post_id
							AND e2.created_at BETWEEN %s AND %s),
						0
					) AS avg_dwell_time,
					COALESCE(
						CASE WHEN (
							SELECT COUNT(*)
							FROM {$table} AS e3
							WHERE e3.event_type = 'page_view'
								AND e3.post_id = e.post_id
								AND e3.created_at BETWEEN %s AND %s
						) > 0
						THEN (
							SELECT COUNT(*)
							FROM {$table} AS e4
							WHERE e4.event_type = 'read_completion'
								AND e4.post_id = e.post_id
								AND e4.created_at BETWEEN %s AND %s
						) * 1.0 / (
							SELECT COUNT(*)
							FROM {$table} AS e5
							WHERE e5.event_type = 'page_view'
								AND e5.post_id = e.post_id
								AND e5.created_at BETWEEN %s AND %s
						)
						ELSE 0
						END,
						0
					) AS read_completion_rate
				FROM {$table} AS e
				INNER JOIN {$wpdb->posts} AS p ON e.post_id = p.ID
				WHERE e.post_id > 0
					AND e.created_at BETWEEN %s AND %s
				GROUP BY e.post_id, p.post_title
				ORDER BY {$order_by}
				LIMIT %d",
				// avg_dwell_time subquery.
				$start_datetime,
				$end_datetime,
				// page_view count for read_completion_rate denominator check.
				$start_datetime,
				$end_datetime,
				// read_completion count for read_completion_rate numerator.
				$start_datetime,
				$end_datetime,
				// page_view count for read_completion_rate denominator.
				$start_datetime,
				$end_datetime,
				// Main WHERE clause.
				$start_datetime,
				$end_datetime,
				$limit
			)
		);

		$posts = array();

		if ( $top_posts ) {
			foreach ( $top_posts as $row ) {
				$posts[] = array(
					'post_id'              => (int) $row->post_id,
					'title'                => $row->title,
					'total_signals'        => (int) $row->total_signals,
					'unique_visitors'      => (int) $row->unique_visitors,
					'avg_dwell_time'       => round( (float) $row->avg_dwell_time, 2 ),
					'read_completion_rate' => round( (float) $row->read_completion_rate, 2 ),
				);
			}
		}

		return AtlasAI_REST_API::success(
			array(
				'posts' => $posts,
			)
		);
	}

	/**
	 * GET /personaflow/analytics/timeline-extended
	 *
	 * Returns daily event and unique visitor counts for the full requested date range.
	 * No 7-day clamp — supports full Pro date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_timeline_extended( $request ) {
		global $wpdb;

		list( $start_date, $end_date ) = $this->get_date_range( $request );

		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = $end_date . ' 23:59:59';

		$table = $wpdb->prefix . 'atlasai_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS events, COUNT(DISTINCT visitor_hash) AS visitors
				FROM {$table}
				WHERE created_at BETWEEN %s AND %s
				GROUP BY day
				ORDER BY day ASC",
				$start_datetime,
				$end_datetime
			)
		);

		// Build a complete date range with zero-fill for days without data.
		$labels   = array();
		$events   = array();
		$visitors = array();

		// Index results by day for quick lookup.
		$result_map = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$result_map[ $row->day ] = $row;
			}
		}

		$current = strtotime( $start_date );
		$end_ts  = strtotime( $end_date );

		while ( $current <= $end_ts ) {
			$day      = gmdate( 'Y-m-d', $current );
			$labels[] = $day;

			if ( isset( $result_map[ $day ] ) ) {
				$events[]   = (int) $result_map[ $day ]->events;
				$visitors[] = (int) $result_map[ $day ]->visitors;
			} else {
				$events[]   = 0;
				$visitors[] = 0;
			}

			$current = strtotime( '+1 day', $current );
		}

		return AtlasAI_REST_API::success(
			array(
				'labels'   => $labels,
				'datasets' => array(
					'events'   => $events,
					'visitors' => $visitors,
				),
			)
		);
	}
}
