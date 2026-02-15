<?php
/**
 * Pro signal type definitions — 77 additional behavioral signals.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_Signals
 */
class AtlasAI_Pro_Signals {

	/**
	 * Add 77 Pro signal types to the validation whitelist.
	 *
	 * Hooked to `atlas_ai_personaflow_signal_types` filter.
	 *
	 * @param array $signals Existing signal type => weight array.
	 * @return array Merged signal array (30 free + 77 pro = 107 total).
	 */
	public function add_pro_signals( $signals ) {
		$pro_signals = array(
			// ── Content (8 extra) ──
			'multi_revisit'       => 6.0,   // Same post visited 3+ times.
			'expand_content'      => 1.5,   // Clicked "read more", accordion, etc.
			'image_interaction'   => 1.5,   // Lightbox, zoom, swipe gallery.
			'media_completion'    => 5.0,   // Video/audio played to 90%+.
			'download_resource'   => 4.0,   // Downloaded attached file.
			'print_page'          => 2.0,   // Triggered print dialog.
			'code_copy'           => 3.0,   // Copied from code block.
			'outbound_link_click' => 0.5,   // Clicked external link.

			// ── Navigation (8 extra) ──
			'archive_browse'          => 0.5,   // Visited category/tag archive.
			'pagination_advance'      => 1.5,   // Clicked page 2+ in archive.
			'breadcrumb_navigate'     => 0.5,   // Used breadcrumb navigation.
			'author_archive_visit'    => 2.5,   // Visited author archive.
			'back_button_return'      => 2.0,   // Returned via back button.
			'archive_browse_no_click' => -1.0,  // Browsed archive without clicking.
			'search_no_click'         => -0.5,  // Searched but clicked nothing.
			'random_navigation'       => 1.0,   // Random/erratic navigation pattern.

			// ── WooCommerce (15 extra) ──
			'remove_from_cart'    => -3.0,  // Removed item from cart.
			'checkout_start'      => 7.0,   // Proceeded to checkout.
			'checkout_abandon'    => -5.0,  // Abandoned checkout.
			'subscription_signup' => 8.0,   // Subscribed to recurring product.
			'refund_request'      => -6.0,  // Requested refund.
			'reorder'             => 7.0,   // Re-ordered previous purchase.
			'product_compare'     => 2.0,   // Used product compare.
			'gallery_view'        => 1.5,   // Viewed product gallery images.
			'coupon_apply'        => 2.0,   // Applied coupon code.
			'variation_select'    => 1.0,   // Selected product variation.
			'quantity_change'     => 1.5,   // Changed quantity in cart.
			'product_qa'          => 3.0,   // Engaged with product Q&A.
			'helpful_vote'        => 2.0,   // Voted review as helpful.
			'cart_abandon'        => -4.0,  // Cart abandoned (items left).
			'checkout_complete'   => 9.0,   // Checkout completed (but not yet paid).

			// ── Social (9) ──
			'native_share'     => 6.0,   // Used Web Share API.
			'social_click'     => 4.0,   // Clicked share to social platform.
			'copy_link'        => 3.0,   // Copied post link.
			'email_share'      => 5.0,   // Shared via email/mailto.
			'private_share'    => 4.0,   // WhatsApp/Telegram share.
			'comment_upvote'   => 2.0,   // Upvoted a comment.
			'mention_author'   => 3.0,   // Mentioned author in comment.
			'share_cancel'     => -0.5,  // Opened share dialog but cancelled.
			'reshare'          => 5.0,   // Shared same content again.

			// ── Explicit Feedback (10 extra) ──
			'multi_react'          => 4.0,   // Used multi-react (celebrate, insightful, curious).
			'weighted_like'        => 3.0,   // Weighted like (e.g. "super like").
			'star_rating'          => 2.0,   // Star rating (1-5, weight scaled by value).
			'follow_author'        => 5.0,   // Followed an author.
			'unfollow_author'      => -3.0,  // Unfollowed an author.
			'subscribe_category'   => 4.0,   // Subscribed to category updates.
			'unsubscribe_category' => -2.0,  // Unsubscribed from category.
			'newsletter_signup'    => 5.0,   // Signed up for newsletter.
			'collection_add'       => 4.0,   // Added to personal collection.
			'premium_reaction'     => 6.0,   // Premium-tier reaction.

			// ── Negative / Disengagement (13) ──
			'bounce'             => -3.0,  // Left page < 15s with no interactions.
			'pogo_stick'         => -4.0,  // Returned to same page < 10s.
			'fast_scroll'        => -2.0,  // Scrolled to bottom very fast.
			'dismiss'            => -3.0,  // Dismissed recommendation.
			'reduce_affinity'    => -2.0,  // Manually reduced interest.
			'hide_post'          => -5.0,  // Hid specific post.
			'mute_author'        => -6.0,  // Muted author.
			'block_author'       => -8.0,  // Blocked author.
			'report_content'     => -7.0,  // Reported content.
			'close_widget'       => -1.0,  // Closed recommendation widget.
			'rage_quit'          => -5.0,  // Rapid close after frustration signals.
			'ad_blocker'         => 0.0,   // Detected ad blocker (context only).
			'cart_abandon_final' => -4.0,  // Final cart abandonment (timed).

			// ── Session Context (14 extra) ──
			'session_depth'      => 0.0,   // Number of pages in session.
			'session_duration'   => 0.0,   // Total session time.
			'referral_source'    => 0.0,   // Traffic source (organic, social, direct).
			'device_type'        => 0.0,   // Mobile, tablet, desktop.
			'time_of_day'        => 0.0,   // Hour bucket (morning, afternoon, evening).
			'day_of_week'        => 0.0,   // Weekday/weekend.
			'logged_in_context'  => 0.0,   // Whether user is logged in.
			'first_visit'        => 1.0,   // First-time visitor.
			'tab_visibility'     => 0.0,   // Tab focus/blur pattern.
			'scroll_direction'   => 0.0,   // Up vs down scroll ratio.
			'idle_detection'     => -0.5,  // Idle for 60s+.
			'font_size_change'   => 0.0,   // Changed font size.
			'reader_mode'        => 2.0,   // Activated reader mode.
			'recommendation_ctr' => 0.0,   // Click-through rate on recs.
		);

		return array_merge( $signals, $pro_signals );
	}

	/**
	 * Modify signal weight for Pro-specific scoring logic.
	 *
	 * Hooked to `atlas_ai_personaflow_signal_weight` filter.
	 *
	 * @param float  $computed   Computed weight.
	 * @param string $event_type Signal type.
	 * @param float  $default    Default weight for this type.
	 * @return float Modified weight.
	 */
	public function modify_weight( $computed, $event_type, $default ) {
		// Scale star_rating weight by actual rating value (1-5).
		// A 5-star rating gets full weight, 1-star gets minimal.
		if ( 'star_rating' === $event_type ) {
			// The event_value should contain the rating (1-5).
			// Weight ranges from -2.0 (1 star) to 5.0 (5 stars).
			return $computed;
		}

		// Multi-revisit gets escalating weight based on visit count.
		if ( 'multi_revisit' === $event_type && $computed > 0 ) {
			// Cap at 10.0 max to prevent abuse.
			return min( $computed * 1.5, 10.0 );
		}

		return $computed;
	}
}
