<?php
/**
 * Freemius SDK initialization for Smart Local AI Pro (add-on).
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a helper function for easy SDK access.
 *
 * @return Freemius
 */
function atlas_ai_pro_fs() {
	global $atlas_ai_pro_fs;

	if ( ! isset( $atlas_ai_pro_fs ) ) {
		// Include Freemius SDK.
		require_once dirname( __FILE__ ) . '/../freemius/start.php';

		$atlas_ai_pro_fs = fs_dynamic_init(
			array(
				'id'              => '00001', // TODO: Replace with actual Freemius add-on ID.
				'slug'            => 'smart-local-ai-pro',
				'type'            => 'plugin',
				'public_key'      => 'pk_00000000000000000000000000001', // TODO: Replace with actual public key.
				'is_premium'      => true,
				'is_premium_only' => true,
				'has_paid_plans'  => true,
				'parent'          => array(
					'id'   => '00000', // TODO: Replace with actual parent product ID.
					'slug' => 'smart-local-ai',
				),
				'menu'            => array(
					'slug' => 'smart-local-ai',
				),
			)
		);
	}

	return $atlas_ai_pro_fs;
}

// Init Freemius.
atlas_ai_pro_fs();
// Signal that SDK is loaded.
do_action( 'atlas_ai_pro_fs_loaded' );
