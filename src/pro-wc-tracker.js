/**
 * Smart Local AI Pro — WooCommerce JS tracker extension.
 *
 * Tracks WC-specific JavaScript signals that can't be captured
 * server-side: gallery views, variation selects, quantity changes.
 *
 * @package Smart_Local_AI_Pro
 */
( function () {
	'use strict';

	var tracker = window.atlasAIPersonaFlowTracker;

	if ( ! tracker || ! tracker.pushEvent ) {
		return;
	}

	var push   = tracker.pushEvent;
	var postId = ( window.atlasAIPersonaFlow && window.atlasAIPersonaFlow.postId ) || 0;

	// ── Product Gallery View ──
	// Detect clicks on WooCommerce product gallery thumbnails.
	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( ! target.closest ) {
			return;
		}

		// WC product gallery thumbnail click.
		var galleryItem = target.closest( '.woocommerce-product-gallery__image, .flex-control-thumbs li, .woocommerce-product-gallery .woocommerce-product-gallery__trigger' );
		if ( galleryItem ) {
			push( 'gallery_view', postId, 1, 1.5 );
			return;
		}

		// Product compare button.
		var compareBtn = target.closest( '.compare-button, .compare, [data-compare], .yith-wcpc-compare' );
		if ( compareBtn ) {
			var compProductId = parseInt( compareBtn.getAttribute( 'data-product-id' ) || compareBtn.getAttribute( 'data-product_id' ) || postId, 10 );
			push( 'product_compare', compProductId, 1, 2.0 );
			return;
		}

		// Product Q&A clicks.
		var qaElement = target.closest( '.product-qa, .woocommerce-Reviews .comment-reply-link, #ask-question, .qa-form' );
		if ( qaElement ) {
			push( 'product_qa', postId, 1, 3.0 );
			return;
		}

		// Helpful vote clicks.
		var helpfulBtn = target.closest( '.helpful-vote, [data-helpful], .review-helpful' );
		if ( helpfulBtn ) {
			push( 'helpful_vote', postId, 1, 2.0 );
		}
	} );

	// ── Variation Select ──
	// Detect WC variation dropdown changes.
	document.addEventListener( 'change', function ( e ) {
		var target = e.target;

		// WC variation select.
		if ( target.closest && target.closest( '.variations select, .variations .value select' ) ) {
			push( 'variation_select', postId, 1, 1.0 );
			return;
		}

		// Quantity input change.
		if ( target.closest && target.closest( '.quantity input[type="number"], .qty' ) ) {
			push( 'quantity_change', postId, parseFloat( target.value ) || 1, 1.5 );
		}
	} );

	// ── Gallery Swipe (touch) ──
	var touchStartX = 0;
	document.addEventListener( 'touchstart', function ( e ) {
		if ( e.target.closest && e.target.closest( '.woocommerce-product-gallery' ) ) {
			touchStartX = e.touches[ 0 ].clientX;
		}
	}, { passive: true } );

	document.addEventListener( 'touchend', function ( e ) {
		if ( e.target.closest && e.target.closest( '.woocommerce-product-gallery' ) ) {
			var deltaX = ( e.changedTouches[ 0 ].clientX - touchStartX );
			if ( Math.abs( deltaX ) > 50 ) {
				push( 'gallery_view', postId, 1, 1.5 );
			}
		}
	}, { passive: true } );

} )();
