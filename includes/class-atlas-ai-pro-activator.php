<?php
/**
 * Fired during Pro plugin activation.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Activator
 */
class AtlasAI_Pro_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
	}

	/**
	 * Create Pro-specific database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}atlasai_user_exclusions (
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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set default Pro plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'version'                  => ATLAS_AI_PRO_VERSION,
			'delete_data_on_uninstall' => false,
			'enable_negative_signals'  => true,
			'enable_social_signals'    => true,
			'enable_session_context'   => true,
			'exclusion_ttl'            => 365,
			'bounce_threshold'         => 15,
			'pogo_stick_threshold'     => 10,
			'fast_scroll_velocity'     => 5000,
			'idle_timeout'             => 60,
		);

		$existing = get_option( 'atlas_ai_pro_settings' );

		if ( ! $existing ) {
			add_option( 'atlas_ai_pro_settings', $defaults );
			return;
		}

		// Merge missing keys without overwriting.
		$updated = false;
		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $existing[ $key ] ) ) {
				$existing[ $key ] = $value;
				$updated          = true;
			}
		}

		if ( ! isset( $existing['version'] ) || $existing['version'] !== ATLAS_AI_PRO_VERSION ) {
			$existing['version'] = ATLAS_AI_PRO_VERSION;
			$updated             = true;
		}

		if ( $updated ) {
			update_option( 'atlas_ai_pro_settings', $existing );
		}
	}
}
