<?php
/**
 * Session context tracking and aggregation.
 *
 * Extends the JS tracker data with session context information
 * and handles post-profile cache invalidation.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Session
 */
class AtlasAI_Pro_Session {

	/**
	 * Extend the JS tracker data with session context.
	 *
	 * Hooked to `atlas_ai_personaflow_tracker_js_data` filter.
	 *
	 * @param array $data Tracker JS data.
	 * @return array Extended data.
	 */
	public function extend_tracker_data( $data ) {
		$pro_settings = get_option( 'atlas_ai_pro_settings', array() );

		$data['session'] = array(
			'enabled'          => true,
			'referralSource'   => $this->get_referral_source(),
			'deviceType'       => $this->get_device_type(),
			'isLoggedIn'       => is_user_logged_in(),
			'isFirstVisit'     => ! isset( $_COOKIE['atlas_ai_returning'] ),
			'idleTimeout'      => isset( $pro_settings['idle_timeout'] ) ? (int) $pro_settings['idle_timeout'] : 60,
			'trackVisibility'  => true,
			'trackScrollDir'   => true,
			'trackFontSize'    => true,
			'trackReaderMode'  => true,
		);

		// Negative signal thresholds.
		$data['negative'] = array(
			'enabled'            => ! empty( $pro_settings['enable_negative_signals'] ),
			'bounceThreshold'    => isset( $pro_settings['bounce_threshold'] ) ? (int) $pro_settings['bounce_threshold'] : 15,
			'pogoStickThreshold' => isset( $pro_settings['pogo_stick_threshold'] ) ? (int) $pro_settings['pogo_stick_threshold'] : 10,
			'fastScrollVelocity' => isset( $pro_settings['fast_scroll_velocity'] ) ? (int) $pro_settings['fast_scroll_velocity'] : 5000,
		);

		return $data;
	}

	/**
	 * Handle post-profile computation tasks.
	 *
	 * Hooked to `atlas_ai_personaflow_profile_computed` action.
	 *
	 * @param string $visitor_hash Visitor hash.
	 * @param array  $taste_vector 384-dim taste vector.
	 */
	public function on_profile_computed( $visitor_hash, $taste_vector ) {
		// Invalidate any Pro-specific cached data.
		delete_transient( 'atlas_ai_pro_affinities_' . $visitor_hash );
	}

	/**
	 * Determine referral source category.
	 *
	 * @return string Source category (organic, social, direct, referral, email).
	 */
	private function get_referral_source() {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) || empty( $_SERVER['HTTP_REFERER'] ) ) {
			return 'direct';
		}

		$referer = wp_unslash( $_SERVER['HTTP_REFERER'] );
		$host    = wp_parse_url( $referer, PHP_URL_HOST );

		if ( ! $host ) {
			return 'direct';
		}

		// Check if same site.
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host === $site_host ) {
			return 'internal';
		}

		// Search engines.
		$search_engines = array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex' );
		foreach ( $search_engines as $engine ) {
			if ( false !== strpos( $host, $engine ) ) {
				return 'organic';
			}
		}

		// Social platforms.
		$social_platforms = array( 'facebook', 'twitter', 'linkedin', 'instagram', 'pinterest', 'reddit', 'tiktok', 'youtube', 't.co' );
		foreach ( $social_platforms as $platform ) {
			if ( false !== strpos( $host, $platform ) ) {
				return 'social';
			}
		}

		// Email services.
		$email_services = array( 'mail.google', 'outlook', 'mail.yahoo', 'protonmail' );
		foreach ( $email_services as $service ) {
			if ( false !== strpos( $host, $service ) ) {
				return 'email';
			}
		}

		return 'referral';
	}

	/**
	 * Determine device type from user agent.
	 *
	 * @return string Device type (mobile, tablet, desktop).
	 */
	private function get_device_type() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return 'desktop';
		}

		$ua = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );

		// Tablet check first (before mobile, as tablets may match mobile patterns).
		if ( preg_match( '/iPad|Android(?!.*Mobile)|Tablet/i', $ua ) ) {
			return 'tablet';
		}

		// Mobile check.
		if ( preg_match( '/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|Opera Mini|IEMobile/i', $ua ) ) {
			return 'mobile';
		}

		return 'desktop';
	}
}
