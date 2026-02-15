<?php
/**
 * Pro REST API endpoints.
 *
 * Adds 9 additional endpoints for exclusions, reactions, and analytics.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_REST_API
 */
class AtlasAI_Pro_REST_API {

	/**
	 * REST namespace (shared with free plugin).
	 *
	 * @var string
	 */
	const NAMESPACE = 'atlas-ai/v1';

	/**
	 * Negative signals handler.
	 *
	 * @var AtlasAI_Pro_Negative|null
	 */
	private $negative;

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Dismiss recommendation.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_dismiss' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => $this->get_signal_args(),
			)
		);

		// Hide post.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/hide',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_hide' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => $this->get_signal_args(),
			)
		);

		// Mute author.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/mute',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_mute' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => $this->get_signal_args(),
			)
		);

		// Block author.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/block',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_block' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => $this->get_signal_args(),
			)
		);

		// Get exclusions.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/exclusions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_exclusions' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => array(
					'visitor_hash' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Remove exclusion.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/exclusions/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'remove_exclusion' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => array(
					'id'           => array(
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && $value > 0;
						},
					),
					'visitor_hash' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Pro stats.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/pro/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pro_stats' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'admin_permission_check' ),
			)
		);

		// Multi-react.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/react',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_react' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => array_merge(
					$this->get_signal_args(),
					array(
						'reaction_type' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return in_array( $value, array( 'celebrate', 'insightful', 'curious' ), true );
							},
						),
					)
				),
			)
		);

		// Star rating.
		register_rest_route(
			self::NAMESPACE,
			'/personaflow/star-rate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_star_rate' ),
				'permission_callback' => array( 'AtlasAI_REST_API', 'public_permission_check' ),
				'args'                => array_merge(
					$this->get_signal_args(),
					array(
						'rating' => array(
							'required'          => true,
							'validate_callback' => function ( $value ) {
								return is_numeric( $value ) && $value >= 1 && $value <= 5;
							},
						),
					)
				),
			)
		);
	}

	/**
	 * Handle dismiss signal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_dismiss( $request ) {
		return $this->store_signal( $request, 'dismiss', -3.0 );
	}

	/**
	 * Handle hide post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_hide( $request ) {
		return $this->store_signal( $request, 'hide_post', -5.0 );
	}

	/**
	 * Handle mute author.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_mute( $request ) {
		return $this->store_signal( $request, 'mute_author', -6.0 );
	}

	/**
	 * Handle block author.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_block( $request ) {
		return $this->store_signal( $request, 'block_author', -8.0 );
	}

	/**
	 * Get user's exclusion list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_exclusions( $request ) {
		$visitor_hash = $request->get_param( 'visitor_hash' );
		$negative     = $this->get_negative_handler();
		$exclusions   = $negative->get_exclusions( $visitor_hash );

		// Enrich with readable names.
		$enriched = array();
		foreach ( $exclusions as $exclusion ) {
			$item = $exclusion;

			switch ( $exclusion['exclusion_type'] ) {
				case 'hide_post':
					$item['target_title'] = get_the_title( $exclusion['target_id'] );
					break;
				case 'mute_author':
				case 'block_author':
					$user = get_user_by( 'ID', $exclusion['target_id'] );
					$item['target_title'] = $user ? $user->display_name : __( 'Unknown', 'smart-local-ai-pro' );
					break;
				case 'dismiss_category':
					$term = get_term( $exclusion['target_id'] );
					$item['target_title'] = $term && ! is_wp_error( $term ) ? $term->name : __( 'Unknown', 'smart-local-ai-pro' );
					break;
			}

			$enriched[] = $item;
		}

		return AtlasAI_REST_API::success( $enriched );
	}

	/**
	 * Remove an exclusion.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_exclusion( $request ) {
		$exclusion_id = absint( $request->get_param( 'id' ) );
		$visitor_hash = $request->get_param( 'visitor_hash' );
		$negative     = $this->get_negative_handler();

		$removed = $negative->remove_exclusion( $exclusion_id, $visitor_hash );

		if ( ! $removed ) {
			return AtlasAI_REST_API::error( 'not_found', __( 'Exclusion not found or not owned by this visitor.', 'smart-local-ai-pro' ), 404 );
		}

		return AtlasAI_REST_API::success( array( 'removed' => true ) );
	}

	/**
	 * Get extended Pro analytics.
	 *
	 * @return WP_REST_Response
	 */
	public function get_pro_stats() {
		global $wpdb;

		// Total exclusions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_exclusions = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}atlasai_user_exclusions"
		);

		// Exclusions by type.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exclusions_by_type = $wpdb->get_results(
			"SELECT exclusion_type, COUNT(*) as count
			FROM {$wpdb->prefix}atlasai_user_exclusions
			GROUP BY exclusion_type
			ORDER BY count DESC",
			ARRAY_A
		);

		// Negative signal count (last 7 days).
		$negative_types = array(
			'bounce', 'pogo_stick', 'fast_scroll', 'dismiss', 'hide_post',
			'mute_author', 'block_author', 'report_content', 'close_widget',
			'rage_quit', 'cart_abandon_final',
		);
		$placeholders   = implode( ',', array_fill( 0, count( $negative_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders
		$negative_events_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}atlasai_events
				WHERE event_type IN ({$placeholders})
				AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
				...$negative_types
			)
		);

		// Social signal count (last 7 days).
		$social_types = array( 'native_share', 'social_click', 'copy_link', 'email_share', 'private_share', 'reshare' );
		$s_holders    = implode( ',', array_fill( 0, count( $social_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders
		$social_events_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}atlasai_events
				WHERE event_type IN ({$s_holders})
				AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
				...$social_types
			)
		);

		// Top Pro signal types (last 30 days).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$top_pro_signals = $wpdb->get_results(
			"SELECT event_type, COUNT(*) as count
			FROM {$wpdb->prefix}atlasai_events
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			AND event_type NOT IN (
				'page_view', 'dwell_time', 'scroll_depth', 'read_completion',
				'content_revisit', 'click_read_more', 'text_selection', 'copy_text',
				'media_play', 'internal_link_click', 'search_query', 'search_click',
				'related_post_click', 'category_browse_deep', 'tag_explore',
				'product_view', 'add_to_cart', 'add_to_wishlist', 'purchase_complete',
				'product_review', 'product_rating', 'cross_sell_click', 'product_tab_switch',
				'like', 'dislike', 'bookmark_save', 'bookmark_remove',
				'session_start', 'return_visitor', 'recommendation_impression'
			)
			GROUP BY event_type
			ORDER BY count DESC
			LIMIT 10",
			ARRAY_A
		);

		return AtlasAI_REST_API::success(
			array(
				'total_exclusions'    => $total_exclusions,
				'exclusions_by_type'  => $exclusions_by_type ? $exclusions_by_type : array(),
				'negative_events_7d'  => $negative_events_7d,
				'social_events_7d'    => $social_events_7d,
				'top_pro_signals'     => $top_pro_signals ? $top_pro_signals : array(),
			)
		);
	}

	/**
	 * Handle multi-react signal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_react( $request ) {
		$reaction_type = $request->get_param( 'reaction_type' );
		$meta          = wp_json_encode( array( 'reaction' => $reaction_type ) );

		return $this->store_signal( $request, 'multi_react', 4.0, $meta );
	}

	/**
	 * Handle star rating signal.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_star_rate( $request ) {
		$rating = (int) $request->get_param( 'rating' );

		// Scale weight by rating: 1 star = -2.0, 2 = 0.0, 3 = 2.0, 4 = 3.5, 5 = 5.0.
		$weight_map = array( 1 => -2.0, 2 => 0.0, 3 => 2.0, 4 => 3.5, 5 => 5.0 );
		$weight     = isset( $weight_map[ $rating ] ) ? $weight_map[ $rating ] : 2.0;
		$meta       = wp_json_encode( array( 'rating' => $rating ) );

		return $this->store_signal( $request, 'star_rating', $weight, $meta );
	}

	/**
	 * Store a signal event via the free plugin's tracker.
	 *
	 * @param WP_REST_Request $request    Request object.
	 * @param string          $event_type Signal type.
	 * @param float           $weight     Signal weight.
	 * @param string|null     $meta       Optional JSON meta.
	 * @return WP_REST_Response
	 */
	private function store_signal( $request, $event_type, $weight, $meta = null ) {
		$visitor_hash = $request->get_param( 'visitor_hash' );
		$post_id      = absint( $request->get_param( 'post_id' ) );

		$plugin  = Smart_Local_AI::get_instance();
		$pf      = $plugin->get_module( 'personaflow' );
		$tracker = $pf ? $pf->get_tracker() : null;

		if ( ! $tracker ) {
			return AtlasAI_REST_API::error( 'tracker_unavailable', __( 'PersonaFlow tracker is not available.', 'smart-local-ai-pro' ) );
		}

		$stored = $tracker->store_events(
			array(
				array(
					'visitor_hash' => $visitor_hash,
					'session_id'   => $request->get_param( 'session_id' ) ?: 'rest_' . time(),
					'event_type'   => $event_type,
					'post_id'      => $post_id,
					'event_value'  => 0,
					'weight'       => $weight,
					'meta'         => $meta,
				),
			)
		);

		return AtlasAI_REST_API::success(
			array(
				'stored'     => $stored > 0,
				'event_type' => $event_type,
				'post_id'    => $post_id,
			)
		);
	}

	/**
	 * Get common signal endpoint arguments.
	 *
	 * @return array Argument definitions.
	 */
	private function get_signal_args() {
		return array(
			'visitor_hash' => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_id'      => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
			'session_id'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get or create the negative handler instance.
	 *
	 * @return AtlasAI_Pro_Negative
	 */
	private function get_negative_handler() {
		if ( null === $this->negative ) {
			$this->negative = new AtlasAI_Pro_Negative();
		}
		return $this->negative;
	}
}
