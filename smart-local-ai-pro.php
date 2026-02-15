<?php
/**
 * Plugin Name: Smart Local AI Pro
 * Plugin URI:  https://atlasaidev.com/pro
 * Description: Pro add-on for Smart Local AI — adds 77 advanced behavioral signals, social tracking,
 *              negative signal detection, session analytics, and user exclusions to PersonaFlow.
 * Version:     1.0.0
 * Author:      AtlasAI Dev
 * Author URI:  https://atlasaidev.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-local-ai-pro
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'ATLAS_AI_PRO_VERSION', '1.0.0' );
define( 'ATLAS_AI_PRO_FILE', __FILE__ );
define( 'ATLAS_AI_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'ATLAS_AI_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'ATLAS_AI_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check that Smart Local AI (free) is active.
 */
//if ( ! defined( 'ATLAS_AI_VERSION' ) ) {
//	add_action(
//		'admin_notices',
//		function () {
//			echo '<div class="notice notice-error"><p>';
//			esc_html_e( 'Smart Local AI Pro requires Smart Local AI (free) to be active.', 'smart-local-ai-pro' );
//			echo '</p></div>';
//		}
//	);
//	return;
//}

/**
 * Include core files.
 */
require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-activator.php';
require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-deactivator.php';

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, array( 'AtlasAI_Pro_Activator', 'activate' ) );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, array( 'AtlasAI_Pro_Deactivator', 'deactivate' ) );

/**
 * Initialize Freemius SDK for Pro add-on.
 */
//if ( file_exists( ATLAS_AI_PRO_PATH . 'includes/freemius-init.php' ) ) {
//	require_once ATLAS_AI_PRO_PATH . 'includes/freemius-init.php';
//}

/**
 * Boot the Pro add-on.
 *
 * Priority 20 ensures the free plugin (default priority) is fully loaded first.
 */
add_action( 'plugins_loaded', 'atlas_ai_pro_init', 20 );

/**
 * Initialize Pro features if PersonaFlow is enabled.
 */
function atlas_ai_pro_init() {
	$plugin = Smart_Local_AI::get_instance();
	$pf     = $plugin->get_module( 'personaflow' );

	if ( ! $pf || ! $pf->is_enabled() ) {
		return; // PersonaFlow not enabled, Pro features inactive.
	}

	// Load all Pro class files.
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-signals.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-negative.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-social.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-session.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-woocommerce.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-rest-api.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-cron.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-loader.php';

	AtlasAI_Pro_Loader::init();
}
