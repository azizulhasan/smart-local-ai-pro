<?php
/**
 * AtlasAiDev library wrapper for Smart Local AI Pro.
 *
 * Integrates AtlasAiDev tracking, license management, auto-updates,
 * promotions, and Freemius deactivation feedback into the Pro plugin.
 * Telemetry is disabled until data collection scope is finalized.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Lib_AtlasAiDev
 */
class AtlasAI_Pro_Lib_AtlasAiDev {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * AtlasAiDev client.
	 *
	 * @var \AtlasAiDev\AppService\Pro\Client|null
	 */
	private $client = null;

	/**
	 * Insights handler.
	 *
	 * @var object|null
	 */
	private $insights = null;

	/**
	 * License handler.
	 *
	 * @var object|null
	 */
	private $license = null;

	/**
	 * Updater handler.
	 *
	 * @var object|null
	 */
	private $updater = null;

	/**
	 * Promotions handler.
	 *
	 * @var object|null
	 */
	private $promotion = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the AtlasAiDev integration.
	 */
	public function init() {
		if ( ! class_exists( '\AtlasAiDev\AppService\Pro\Client' ) ) {
			require_once ATLAS_AI_PRO_PATH . 'Libs/AtlasAiDev/Client.php';
		}

		$this->client = new \AtlasAiDev\AppService\Pro\Client(
			'a1b2c3d4-5678-90ab-cdef-smart-local-ai', // Same hash as free plugin.
			'Smart Local AI Pro',
			ATLAS_AI_PRO_FILE
		);

		$this->insights  = $this->client->insights();
		$this->license   = $this->client->license();
		$this->updater   = $this->client->updater();
		$this->promotion = $this->client->promotions();

		// License is managed by Freemius — skip AtlasAiDev license init.
		// $this->license->init();

		// Set promotion source (update with actual gist URL when ready).
		$this->promotion->set_source(
			'https://gist.githubusercontent.com/azizulhasan/afcc74f398b290e586f3a4578341b699/raw/smart-local-ai-pro.json'
		);

		// Initialize updater, promotions, and telemetry (includes Freemius deactivation bridge).
		$this->insightInit();
		$this->updater->init();
		$this->promotion->init();
	}

	/**
	 * Initialize insights (telemetry).
	 *
	 * Currently disabled. Uncomment the call in init() when ready.
	 */
	private function insightInit() {
		$project_slug = $this->client->getSlug();

		add_filter( $project_slug . '_what_tracked', array( $this, 'data_we_collect' ), 10, 1 );

		// Freemius deactivation data bridge.
		add_filter(
			"AtlasAiDev_{$project_slug}_freemius_deactivation_data",
			function () {
				if ( function_exists( 'atlas_ai_fs' ) ) {
					$fs = atlas_ai_fs();
					return array(
						'action'    => $fs->get_ajax_action( 'submit_uninstall_reason' ),
						'security'  => $fs->get_ajax_security( 'submit_uninstall_reason' ),
						'module_id' => $fs->get_id(),
					);
				}
				return array();
			}
		);

		$this->insights->init();
	}

	/**
	 * Describe what data is collected for the opt-in notice.
	 *
	 * @param array $data Existing data descriptions.
	 * @return array
	 */
	public function data_we_collect( $data ) {
		$data = array_merge(
			$data,
			array(
				esc_html__( 'Site name, language and url.', 'smart-local-ai' ),
				esc_html__( 'Number of active and inactive plugins.', 'smart-local-ai' ),
				esc_html__( 'Your name and email address.', 'smart-local-ai' ),
				esc_html__( 'Which AI modules are enabled and Pro features active.', 'smart-local-ai' ),
				esc_html__( 'Feature usage flags — no content data is collected.', 'smart-local-ai' ),
			)
		);
		return $data;
	}

	/**
	 * Opt in to tracking.
	 *
	 * @param bool $override Override minimum interval check.
	 */
	public function trackerOptIn( $override = false ) {
		$this->insights->optIn( $override );
	}

	/**
	 * Opt out of tracking.
	 */
	public function trackerOptOut() {
		$this->insights->optOut();
	}

	/**
	 * Check if tracking is allowed.
	 *
	 * @return bool
	 */
	public function is_tracking_allowed() {
		return $this->insights->is_tracking_allowed();
	}

	/**
	 * Get the AtlasAiDev client.
	 *
	 * @return \AtlasAiDev\AppService\Pro\Client|null
	 */
	public function get_client() {
		return $this->client;
	}
}
