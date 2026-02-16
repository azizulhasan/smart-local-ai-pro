<?php
/**
 * Central hook registration for Smart Local AI Pro.
 *
 * Hooks into all 12 free plugin extensibility points to add
 * Pro features without modifying any free plugin code.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Loader
 */
class AtlasAI_Pro_Loader {

	/**
	 * Initialize all Pro hooks.
	 *
	 * Called from smart-local-ai-pro.php after confirming:
	 * 1. Free plugin is active
	 * 2. PersonaFlow module is enabled
	 * 3. All Pro class files are loaded
	 */
	public static function init() {
		$pro_settings = get_option( 'atlas_ai_pro_settings', array() );

		$signals  = new AtlasAI_Pro_Signals();
		$negative = new AtlasAI_Pro_Negative();
		$social   = new AtlasAI_Pro_Social();
		$session  = new AtlasAI_Pro_Session();
		$wc       = new AtlasAI_Pro_WooCommerce();
		$rest     = new AtlasAI_Pro_REST_API();
		$cron     = new AtlasAI_Pro_Cron();

		// ── Filter hooks (10) ──

		// 1. Add 77 Pro signal types to validation whitelist.
		add_filter( 'atlas_ai_personaflow_signal_types', array( $signals, 'add_pro_signals' ) );

		// 2. Modify signal weights for Pro scoring.
		add_filter( 'atlas_ai_personaflow_signal_weight', array( $signals, 'modify_weight' ), 10, 3 );

		// 3. Add social tracking selectors to JS tracker config.
		if ( ! empty( $pro_settings['enable_social_signals'] ) ) {
			add_filter( 'atlas_ai_personaflow_tracker_config', array( $social, 'extend_tracker_config' ) );
		}

		// 4. Add session context data to JS tracker.
		if ( ! empty( $pro_settings['enable_session_context'] ) ) {
			add_filter( 'atlas_ai_personaflow_tracker_js_data', array( $session, 'extend_tracker_data' ) );
		}

		// 5. Apply exclusion penalties to recommendation scores.
		if ( ! empty( $pro_settings['enable_negative_signals'] ) ) {
			add_filter( 'atlas_ai_personaflow_recommendation_query', array( $negative, 'apply_exclusion_penalty' ), 10, 3 );
		}

		// 6. Filter final recommendation results (remove excluded, apply penalties).
		if ( ! empty( $pro_settings['enable_negative_signals'] ) ) {
			add_filter( 'atlas_ai_personaflow_recommendation_results', array( $negative, 'filter_results' ), 10, 2 );
		}

		// 7. Add Pro exclusions table during free plugin activation.
		add_filter( 'atlas_ai_personaflow_tables', array( __CLASS__, 'add_pro_tables' ) );

		// 8. Add Pro cron schedules.
		add_filter( 'atlas_ai_personaflow_cron_schedules', array( $cron, 'add_pro_schedules' ) );

		// ── Action hooks (2) ──

		// 9. Process negative signals after event storage.
		if ( ! empty( $pro_settings['enable_negative_signals'] ) ) {
			add_action( 'atlas_ai_personaflow_event_stored', array( $negative, 'process_event' ), 10, 4 );
		}

		// 10. Update aggregates after profile computed.
		add_action( 'atlas_ai_personaflow_profile_computed', array( $session, 'on_profile_computed' ), 10, 2 );

		// ── Additional Pro hooks ──

		// Pro REST endpoints.
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

		// Pro Analytics REST endpoints.
		add_action( 'rest_api_init', function () {
			require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-analytics.php';
			$pro_analytics = new AtlasAI_Pro_Analytics();
			$pro_analytics->register_routes();
		} );

		// Pro frontend scripts (priority 20 = after free tracker at 10).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_pro_scripts' ), 20 );

		// WooCommerce hooks (if WC is active).
		if ( class_exists( 'WooCommerce' ) ) {
			$wc->register_hooks();
		}

		// Initialize Pro cron schedules.
		$cron->schedule_events();
	}

	/**
	 * Add Pro exclusions table SQL to the PersonaFlow tables array.
	 *
	 * Hooked to `atlas_ai_personaflow_tables` filter.
	 *
	 * @param array $tables Array of SQL CREATE TABLE statements.
	 * @return array
	 */
	public static function add_pro_tables( $tables ) {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}atlasai_user_exclusions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_hash varchar(64) NOT NULL,
			user_id bigint(20) unsigned DEFAULT 0,
			exclusion_type varchar(30) NOT NULL,
			target_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_type_target (visitor_hash, exclusion_type, target_id),
			KEY visitor_hash (visitor_hash),
			KEY user_id (user_id),
			KEY exclusion_type (exclusion_type)
		) {$charset_collate};";

		return $tables;
	}

	/**
	 * Enqueue Pro frontend scripts.
	 *
	 * Loaded at priority 20, after the free tracker (priority 10).
	 */
	public static function enqueue_pro_scripts() {
		if ( ! is_singular() && ! is_archive() ) {
			return;
		}

		$pro_settings = get_option( 'atlas_ai_pro_settings', array() );

		// Pro tracker (social + negative + session signals).
		$tracker_path = ATLAS_AI_PRO_PATH . 'build/pro-tracker.asset.php';
		$tracker_ver  = file_exists( $tracker_path )
			? require $tracker_path
			: array( 'dependencies' => array(), 'version' => ATLAS_AI_PRO_VERSION );

		wp_enqueue_script(
			'atlas-ai-pro-tracker',
			ATLAS_AI_PRO_URL . 'build/pro-tracker.js',
			$tracker_ver['dependencies'],
			$tracker_ver['version'],
			true
		);

		wp_localize_script(
			'atlas-ai-pro-tracker',
			'atlasAIProConfig',
			array(
				'enableNegative'     => ! empty( $pro_settings['enable_negative_signals'] ),
				'enableSocial'       => ! empty( $pro_settings['enable_social_signals'] ),
				'enableSession'      => ! empty( $pro_settings['enable_session_context'] ),
				'bounceThreshold'    => isset( $pro_settings['bounce_threshold'] ) ? (int) $pro_settings['bounce_threshold'] : 15,
				'pogoStickThreshold' => isset( $pro_settings['pogo_stick_threshold'] ) ? (int) $pro_settings['pogo_stick_threshold'] : 10,
				'fastScrollVelocity' => isset( $pro_settings['fast_scroll_velocity'] ) ? (int) $pro_settings['fast_scroll_velocity'] : 5000,
				'idleTimeout'        => isset( $pro_settings['idle_timeout'] ) ? (int) $pro_settings['idle_timeout'] : 60,
				'dismissUrl'         => rest_url( 'atlas-ai/v1/personaflow/dismiss' ),
				'hideUrl'            => rest_url( 'atlas-ai/v1/personaflow/hide' ),
				'muteUrl'            => rest_url( 'atlas-ai/v1/personaflow/mute' ),
				'blockUrl'           => rest_url( 'atlas-ai/v1/personaflow/block' ),
				'reactUrl'           => rest_url( 'atlas-ai/v1/personaflow/react' ),
				'starRateUrl'        => rest_url( 'atlas-ai/v1/personaflow/star-rate' ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
			)
		);

		// Pro WC tracker (if WooCommerce is active).
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_tracker_path = ATLAS_AI_PRO_PATH . 'build/pro-wc-tracker.asset.php';
			$wc_tracker_ver  = file_exists( $wc_tracker_path )
				? require $wc_tracker_path
				: array( 'dependencies' => array(), 'version' => ATLAS_AI_PRO_VERSION );

			wp_enqueue_script(
				'atlas-ai-pro-wc-tracker',
				ATLAS_AI_PRO_URL . 'build/pro-wc-tracker.js',
				$wc_tracker_ver['dependencies'],
				$wc_tracker_ver['version'],
				true
			);
		}

		// Pro feedback CSS.
		wp_enqueue_style(
			'atlas-ai-pro-feedback',
			ATLAS_AI_PRO_URL . 'css/pro-feedback.css',
			array(),
			ATLAS_AI_PRO_VERSION
		);
	}
}
