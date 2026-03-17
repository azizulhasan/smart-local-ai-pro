<?php
/**
 * Plugin Name: Smart Local AI Pro – Browser-Based Private AI Tools
 * Plugin URI:  https://atlasaidev.com/pro
 * Description: Pro add-on for Smart Local AI — adds 77 advanced behavioral signals, social tracking,
 *              negative signal detection, session analytics, and user exclusions to PersonaFlow.
 * Version:     1.0.0
 * Author:      AtlasAI Dev
 * Author URI:  https://atlasaidev.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: smart-local-ai
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
 *
 * Uses the same product ID and slug as the free plugin — this is how
 * Freemius links the Pro add-on to the free parent plugin.
 */
if ( ! function_exists( 'atlas_ai_fs' ) ) {
	/**
	 * Create a helper function for easy SDK access.
	 *
	 * @return Freemius
	 */
	function atlas_ai_fs() {
		global $atlas_ai_fs;

		if ( ! isset( $atlas_ai_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

			$atlas_ai_fs = fs_dynamic_init(
				array(
					'id'               => '25926',
					'slug'             => 'smart-local-ai',
					'type'             => 'plugin',
					'public_key'       => 'pk_fb3274faf50553dfad1d3ea34dc28',
					'is_premium'       => true,
					'is_premium_only'  => true,
					'has_addons'       => false,
					'has_paid_plans'   => true,
					'has_affiliation'  => false,
					'menu'             => array(
						'slug'    => 'smart-local-ai',
						'support' => false,
						'contact' => true,
						'account' => true,
						'pricing' => false,
					),
				)
			);
		}

		return $atlas_ai_fs;
	}

	// Init Freemius.
	atlas_ai_fs();
	// Signal that SDK was initiated.
	do_action( 'atlas_ai_fs_loaded' );
}

/**
 * Customize Freemius opt-in message.
 */
if ( function_exists( 'atlas_ai_fs' ) ) {
	/**
	 * Custom connect message for Pro plugin updates.
	 *
	 * @param string $message        Default message.
	 * @param string $user_first_name User first name.
	 * @param string $plugin_title   Plugin title.
	 * @param string $user_login     User login.
	 * @param string $site_link      Site link.
	 * @param string $freemius_link  Freemius link.
	 * @return string
	 */
	function atlas_ai_fs_custom_connect_message(
		$message,
		$user_first_name,
		$plugin_title,
		$user_login,
		$site_link,
		$freemius_link
	) {
		return sprintf(
			/* translators: %1$s: user first name, %2$s: plugin title, %5$s: freemius link */
			__( 'Hey %1$s', 'smart-local-ai' ) . ',<br>' .
			__( 'Please help us improve %2$s! If you opt-in, some data about your usage of %2$s will be sent to %5$s. If you skip this, that\'s okay! %2$s will still work just fine.', 'smart-local-ai' ),
			$user_first_name,
			'<b>' . $plugin_title . '</b>',
			'<b>' . $user_login . '</b>',
			$site_link,
			$freemius_link
		);
	}

	atlas_ai_fs()->add_filter( 'connect_message_on_update', 'atlas_ai_fs_custom_connect_message', 10, 6 );

	// Disable Freemius deactivation feedback — AtlasAiDev modal handles it.
	atlas_ai_fs()->add_filter( 'show_deactivation_feedback_form', '__return_false' );
}

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
	// Check that Smart Local AI (free) is active — by priority 20, it's fully loaded.
	if ( ! defined( 'ATLAS_AI_VERSION' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Smart Local AI Pro requires Smart Local AI (free) to be active.', 'smart-local-ai-pro' );
				echo '</p></div>';
			}
		);
		return;
	}

	// If Freemius is active but no valid license, disable all Pro features.
	if ( function_exists( 'atlas_ai_fs' ) && ! atlas_ai_fs()->can_use_premium_code() ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-warning"><p>';
				esc_html_e( 'Smart Local AI Pro requires an active license to enable Pro features.', 'smart-local-ai-pro' );
				if ( function_exists( 'atlas_ai_fs' ) ) {
					printf(
						' <a href="%s">%s</a>',
						esc_url( atlas_ai_fs()->get_account_url() ),
						esc_html__( 'Activate License', 'smart-local-ai-pro' )
					);
				}
				echo '</p></div>';
			}
		);
		return;
	}

	// Load the Pro loader for module-independent hooks (e.g. Bulk Alt Text).
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-loader.php';

	// Register Pro features that work regardless of module state.
	AtlasAI_Pro_Loader::register_global_hooks();

	// PersonaFlow Pro features require PersonaFlow to be enabled.
	$plugin = Smart_Local_AI::get_instance();
	$pf     = $plugin->get_module( 'personaflow' );

	if ( ! $pf || ! $pf->is_enabled() ) {
		return; // PersonaFlow not enabled, PersonaFlow Pro features inactive.
	}

	// Load PersonaFlow Pro class files.
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-signals.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-negative.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-social.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-session.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-woocommerce.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-rest-api.php';
	require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-cron.php';

	AtlasAI_Pro_Loader::init();
}

/**
 * Initialize AtlasAiDev library (updater, promotions, deactivation feedback).
 */
add_action(
	'init',
	function () {
		require_once ATLAS_AI_PRO_PATH . 'includes/class-atlas-ai-pro-lib-atlasaidev.php';
		AtlasAI_Pro_Lib_AtlasAiDev::instance()->init();
	}
);
