/**
 * Smart Local AI Pro — Extended behavioral tracker.
 *
 * Supplements the free PersonaFlow tracker by hooking into
 * window.atlasAIPersonaFlowTracker.pushEvent() to add social,
 * negative, and session context signals.
 *
 * @package Smart_Local_AI_Pro
 */
( function () {
	'use strict';

	// Wait for the free tracker to be available.
	var tracker = window.atlasAIPersonaFlowTracker;
	var config  = window.atlasAIProConfig || {};

	if ( ! tracker || ! tracker.pushEvent ) {
		return; // Free tracker not loaded.
	}

	var originalPush = tracker.pushEvent;
	var postId       = ( window.atlasAIPersonaFlow && window.atlasAIPersonaFlow.postId ) || 0;
	var disabledSignals = ( window.atlasAIPersonaFlow && window.atlasAIPersonaFlow.disabledSignals ) || [];

	// Wrap pushEvent to also check disabled signals for Pro signals.
	function push( type, pId, value, weight, meta ) {
		if ( disabledSignals.length && disabledSignals.indexOf( type ) !== -1 ) {
			return;
		}
		originalPush( type, pId, value, weight, meta );
	}

	// ── Social Signal Tracking ──
	if ( config.enableSocial ) {

		// 1. Navigator.share() interception.
		if ( navigator.share ) {
			var originalShare = navigator.share.bind( navigator );
			navigator.share = function ( data ) {
				push( 'native_share', postId, 1, 6.0 );
				return originalShare( data ).catch( function () {
					push( 'share_cancel', postId, 1, -0.5 );
				} );
			};
		}

		// 2. Social share button clicks.
		document.addEventListener( 'click', function ( e ) {
			var target = e.target;
			if ( ! target.closest ) {
				return;
			}

			// Social platform shares.
			var socialBtn = target.closest( '.share-facebook, .share-twitter, .share-linkedin, .share-pinterest, .share-reddit, [data-share], .social-share-button, .wp-block-social-link' );
			if ( socialBtn ) {
				push( 'social_click', postId, 1, 4.0 );
				return;
			}

			// Copy link.
			var copyBtn = target.closest( '.copy-link, .copy-url, [data-copy-link], .share-copy' );
			if ( copyBtn ) {
				push( 'copy_link', postId, 1, 3.0 );
				return;
			}

			// Email share.
			var emailLink = target.closest( 'a[href^="mailto:"], .share-email, .email-share' );
			if ( emailLink ) {
				push( 'email_share', postId, 1, 5.0 );
				return;
			}

			// Private messaging (WhatsApp, Telegram).
			var privateBtn = target.closest( '.share-whatsapp, .share-telegram, .share-signal, [data-private-share]' );
			if ( privateBtn ) {
				push( 'private_share', postId, 1, 4.0 );
				return;
			}

			// Outbound link clicks.
			var link = target.closest( 'a[href]' );
			if ( link ) {
				var href = link.getAttribute( 'href' ) || '';
				if ( href && href.indexOf( '//' ) !== -1 && href.indexOf( window.location.hostname ) === -1 ) {
					push( 'outbound_link_click', postId, 1, 0.5 );
				}
			}
		} );
	}

	// ── Negative Signal Detection ──
	if ( config.enableNegative ) {

		var pageLoadTime    = Date.now();
		var hasInteracted   = false;
		var bounceThreshold = ( config.bounceThreshold || 15 ) * 1000; // Convert to ms.

		// Track first interaction.
		var interactionEvents = [ 'click', 'scroll', 'keydown', 'touchstart' ];
		function markInteraction() {
			hasInteracted = true;
			interactionEvents.forEach( function ( evt ) {
				document.removeEventListener( evt, markInteraction );
			} );
		}
		interactionEvents.forEach( function ( evt ) {
			document.addEventListener( evt, markInteraction, { once: false, passive: true } );
		} );

		// 1. Bounce detection — leave page quickly without interaction.
		window.addEventListener( 'beforeunload', function () {
			var timeOnPage = Date.now() - pageLoadTime;
			if ( timeOnPage < bounceThreshold && ! hasInteracted && postId > 0 ) {
				push( 'bounce', postId, timeOnPage / 1000, -3.0 );
				tracker.flush();
			}
		} );

		// 2. Fast scroll detection.
		var lastScrollY    = 0;
		var lastScrollTime = 0;
		var fastScrollVelocity = config.fastScrollVelocity || 5000;

		window.addEventListener( 'scroll', function () {
			var now = Date.now();
			var deltaY = Math.abs( window.scrollY - lastScrollY );
			var deltaT = now - lastScrollTime;

			if ( deltaT > 0 && deltaT < 500 ) {
				var velocity = ( deltaY / deltaT ) * 1000; // px/s
				if ( velocity > fastScrollVelocity && ! hasInteracted ) {
					push( 'fast_scroll', postId, velocity, -2.0 );
				}
			}

			lastScrollY    = window.scrollY;
			lastScrollTime = now;
		}, { passive: true } );

		// 3. Dismiss/hide/mute/block button clicks (Pro UI).
		document.addEventListener( 'click', function ( e ) {
			var target = e.target;
			if ( ! target.closest ) {
				return;
			}

			var dismissBtn = target.closest( '[data-pf-dismiss]' );
			if ( dismissBtn ) {
				var dismissPostId = parseInt( dismissBtn.getAttribute( 'data-post-id' ) || postId, 10 );
				push( 'dismiss', dismissPostId, 1, -3.0 );
				return;
			}

			var hideBtn = target.closest( '[data-pf-hide]' );
			if ( hideBtn ) {
				var hidePostId = parseInt( hideBtn.getAttribute( 'data-post-id' ) || postId, 10 );
				push( 'hide_post', hidePostId, 1, -5.0 );
				return;
			}

			var muteBtn = target.closest( '[data-pf-mute]' );
			if ( muteBtn ) {
				var mutePostId = parseInt( muteBtn.getAttribute( 'data-post-id' ) || postId, 10 );
				push( 'mute_author', mutePostId, 1, -6.0 );
				return;
			}

			var blockBtn = target.closest( '[data-pf-block]' );
			if ( blockBtn ) {
				var blockPostId = parseInt( blockBtn.getAttribute( 'data-post-id' ) || postId, 10 );
				push( 'block_author', blockPostId, 1, -8.0 );
				return;
			}

			// Close widget.
			var closeBtn = target.closest( '[data-pf-close]' );
			if ( closeBtn ) {
				push( 'close_widget', 0, 1, -1.0 );
			}
		} );
	}

	// ── Session Context Tracking ──
	if ( config.enableSession ) {

		// 1. Tab visibility changes.
		var hiddenCount = 0;
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				hiddenCount++;
			}
			push( 'tab_visibility', 0, document.hidden ? 0 : 1, 0 );
		} );

		// 2. Idle detection.
		var idleTimeout = ( config.idleTimeout || 60 ) * 1000;
		var idleTimer   = null;
		var isIdle      = false;

		function resetIdle() {
			if ( isIdle ) {
				isIdle = false;
			}
			clearTimeout( idleTimer );
			idleTimer = setTimeout( function () {
				isIdle = true;
				push( 'idle_detection', postId, 1, -0.5 );
			}, idleTimeout );
		}

		[ 'mousemove', 'keydown', 'scroll', 'touchstart' ].forEach( function ( evt ) {
			document.addEventListener( evt, resetIdle, { passive: true } );
		} );
		resetIdle();

		// 3. Scroll direction tracking.
		var scrollUpCount   = 0;
		var scrollDownCount = 0;
		var prevScrollY     = window.scrollY;

		window.addEventListener( 'scroll', function () {
			if ( window.scrollY > prevScrollY ) {
				scrollDownCount++;
			} else if ( window.scrollY < prevScrollY ) {
				scrollUpCount++;
			}
			prevScrollY = window.scrollY;
		}, { passive: true } );

		// Send scroll direction data on page unload.
		window.addEventListener( 'beforeunload', function () {
			var total = scrollUpCount + scrollDownCount;
			if ( total > 5 ) {
				push( 'scroll_direction', postId, scrollUpCount / ( total || 1 ), 0 );
			}
		} );

		// 4. Font size change detection (Ctrl+/Ctrl-).
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && ( e.key === '+' || e.key === '-' || e.key === '=' ) ) {
				push( 'font_size_change', postId, e.key === '-' ? -1 : 1, 0 );
			}
		} );

		// 5. Print detection.
		window.addEventListener( 'beforeprint', function () {
			push( 'print_page', postId, 1, 2.0 );
		} );

		// 6. Session depth (pages viewed this session).
		var sessionDepth = parseInt( sessionStorage.getItem( 'atlas_ai_pro_depth' ) || '0', 10 ) + 1;
		sessionStorage.setItem( 'atlas_ai_pro_depth', String( sessionDepth ) );
		if ( sessionDepth > 1 ) {
			push( 'session_depth', 0, sessionDepth, 0 );
		}

		// 7. First visit detection.
		if ( ! localStorage.getItem( 'atlas_ai_pro_visited' ) ) {
			push( 'first_visit', 0, 1, 1.0 );
			localStorage.setItem( 'atlas_ai_pro_visited', '1' );
		}
	}

	// ── Content Interaction Signals ──

	// Expand content (accordion, read-more, toggle).
	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( ! target.closest ) {
			return;
		}

		var expandable = target.closest( '[data-toggle], .accordion-toggle, .wp-block-details summary, details summary' );
		if ( expandable ) {
			push( 'expand_content', postId, 1, 1.5 );
		}
	} );

	// Image interaction (lightbox, zoom).
	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( target.tagName === 'IMG' && target.closest( '.entry-content, .post-content, article, .gallery, .wp-block-gallery' ) ) {
			push( 'image_interaction', postId, 1, 1.5 );
		}
	} );

	// Code copy detection (code blocks).
	document.addEventListener( 'copy', function () {
		var sel = window.getSelection();
		if ( sel && sel.anchorNode ) {
			var parent = sel.anchorNode.parentElement;
			if ( parent && parent.closest && parent.closest( 'pre, code, .wp-block-code, .code-block' ) ) {
				push( 'code_copy', postId, 1, 3.0 );
			}
		}
	} );

} )();
