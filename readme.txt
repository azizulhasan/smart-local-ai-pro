=== Smart Local AI Pro – Browser-Based Private AI Tools ===
Contributors: hasanazizul
Tags: machine learning, personalization, behavioral tracking, AI, recommendations
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pro add-on for Smart Local AI — adds 77 advanced behavioral signals, social tracking, negative signal detection, session analytics, user exclusions, WooCommerce integration, and extended analytics to PersonaFlow.

== Description ==

Smart Local AI Pro extends the free [Smart Local AI](https://wordpress.org/plugins/smart-local-ai/) plugin with advanced PersonaFlow features for deeper personalization and analytics. All processing remains 100% browser-based — no cloud APIs, no external data transfers.

= Pro Features =

**77 Advanced Behavioral Signals**

Go beyond the 30 free signals with 77 additional tracking points across seven categories:

* **Content signals** — multi-revisit, expand content, image interaction, media completion, download, print, code copy, outbound link click
* **Navigation signals** — archive browse, pagination, breadcrumb navigate, author archive visit, back button return, and more
* **Social signals** — native share, social click, copy link, email share, comment upvote, mention author, reshare
* **Explicit feedback** — multi-react (celebrate/insightful/curious), weighted like, star rating, follow/unfollow author, subscribe/unsubscribe category, newsletter signup
* **Negative/disengagement signals** — bounce, pogo stick, fast scroll, dismiss, hide post, mute author, block author, report content, rage quit
* **Session context** — session depth, duration, referral source, device type, time of day, logged-in context, idle detection
* **WooCommerce signals** — remove from cart, checkout start/abandon/complete, subscription signup, refund request, reorder, product compare, gallery view, coupon apply, variation select, quantity change

**User Exclusion System**

Let visitors customize their feed:

* Hide specific posts
* Mute or block authors
* Dismiss categories
* All exclusion data stored locally per visitor

**Extended Analytics Dashboard**

* Engagement funnel visualization
* Session insights (referral, device, depth)
* WooCommerce analytics (cart, checkout, revenue)
* Extended content analytics (top 25 posts with sorting)
* Daily timeline with full history

**WooCommerce Integration**

Tracks 15 commerce-specific signals including cart activity, checkout flow, subscriptions, refunds, and coupon usage to power product recommendations.

**Automated Background Tasks**

* Category/author/tag affinity aggregation every 6 hours
* Abandoned checkout detection every hour

= Requirements =

* Smart Local AI (free) plugin must be active
* PersonaFlow module must be enabled for behavioral tracking features
* Valid Freemius license required

== Installation ==

1. Install and activate [Smart Local AI](https://wordpress.org/plugins/smart-local-ai/) (free)
2. Upload the `smart-local-ai-pro` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu
4. Activate your license when prompted
5. Enable PersonaFlow in Smart Local AI settings to unlock Pro behavioral tracking

== Frequently Asked Questions ==

= Does this plugin work without the free version? =

No. Smart Local AI Pro is an add-on that requires the free Smart Local AI plugin to be installed and active.

= What happens if my license expires? =

All Pro features will be disabled. The free plugin continues to work normally with its 30 base signals.

= Does the Pro plugin send data to external servers? =

The Pro plugin includes optional telemetry (opt-in only) for usage analytics. All behavioral tracking and personalization remains 100% browser-based. Deactivation feedback is shared with our support system to help improve the product.

= Does it work with WooCommerce? =

Yes. When WooCommerce is active, Pro automatically tracks 15 commerce-specific signals for product recommendations.

== Changelog ==

= 2.0.0 — 2026-03-17 =
* New: Freemius SDK integration for license management and activation
* New: All Pro features gated behind valid license — no license means free-only mode
* New: License activation notice in admin with direct link to account page
* New: AtlasAiDev telemetry with Freemius deactivation feedback bridge
* New: Deactivation data sent to both AtlasAiDev tracker and Freemius
* New: Opt-in tracking for usage analytics
* Fix: Removed unsupported Insights method calls that caused fatal errors
* Fix: Disabled Freemius affiliation page to prevent null reference crash
* Update: Version bumped to 2.0.0

= 1.0.0 =
* Initial release
* 77 advanced behavioral signals for PersonaFlow
* Social sharing tracking with configurable selectors
* Negative signal detection (bounce, pogo stick, fast scroll, dismiss)
* User exclusion system (hide post, mute/block author, dismiss category)
* Session context tracking (referral, device, login status, idle detection)
* WooCommerce integration with 15 commerce signals
* Extended analytics dashboard with 5 REST endpoints
* Automated cron tasks for affinity aggregation and abandoned checkout detection
* Pro admin sections for signal configuration

== Upgrade Notice ==

= 2.0.0 =
Freemius license integration — a valid license is now required for Pro features. Activate your license after updating to continue using Pro functionality.
