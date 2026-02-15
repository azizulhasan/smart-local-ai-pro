<?php
/**
 * Fired during Pro plugin deactivation.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Deactivator
 */
class AtlasAI_Pro_Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * Clears Pro-specific cron events. Does not remove data.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'atlas_ai_pro_aggregate_affinities' );
		wp_clear_scheduled_hook( 'atlas_ai_pro_abandoned_checkout' );
		wp_clear_scheduled_hook( 'atlas_ai_pro_license_check' );
	}
}
