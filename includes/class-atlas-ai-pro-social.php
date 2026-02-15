<?php
/**
 * Social signal tracking configuration.
 *
 * Extends the free tracker's JS config with social sharing selectors
 * and navigator.share interception flags.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Social
 */
class AtlasAI_Pro_Social {

	/**
	 * Extend the JS tracker configuration with social tracking settings.
	 *
	 * Hooked to `atlas_ai_personaflow_tracker_config` filter.
	 *
	 * @param array $config Tracker configuration.
	 * @return array Extended configuration.
	 */
	public function extend_tracker_config( $config ) {
		$config['social'] = array(
			'enabled'                => true,
			'interceptNavigatorShare' => true,
			'shareSelectors'         => array(
				'.share-facebook',
				'.share-twitter',
				'.share-linkedin',
				'.share-pinterest',
				'.share-reddit',
				'.share-whatsapp',
				'.share-telegram',
				'[data-share]',
				'.social-share-button',
				'.wp-block-social-link',
			),
			'copyLinkSelectors'      => array(
				'.copy-link',
				'.copy-url',
				'[data-copy-link]',
				'.share-copy',
			),
			'emailShareSelectors'    => array(
				'a[href^="mailto:"]',
				'.share-email',
				'.email-share',
			),
			'privateShareSelectors'  => array(
				'.share-whatsapp',
				'.share-telegram',
				'.share-signal',
				'[data-private-share]',
			),
		);

		return $config;
	}

	/**
	 * Get social signal type descriptions for admin display.
	 *
	 * @return array Signal descriptions.
	 */
	public static function get_signal_descriptions() {
		return array(
			'native_share'   => __( 'Used browser native share (Web Share API)', 'smart-local-ai-pro' ),
			'social_click'   => __( 'Clicked share to social platform', 'smart-local-ai-pro' ),
			'copy_link'      => __( 'Copied post link to clipboard', 'smart-local-ai-pro' ),
			'email_share'    => __( 'Shared via email', 'smart-local-ai-pro' ),
			'private_share'  => __( 'Shared via private messaging (WhatsApp, Telegram)', 'smart-local-ai-pro' ),
			'comment_upvote' => __( 'Upvoted a comment', 'smart-local-ai-pro' ),
			'mention_author' => __( 'Mentioned author in comment', 'smart-local-ai-pro' ),
			'share_cancel'   => __( 'Opened share dialog but cancelled', 'smart-local-ai-pro' ),
			'reshare'        => __( 'Shared same content again', 'smart-local-ai-pro' ),
		);
	}
}
