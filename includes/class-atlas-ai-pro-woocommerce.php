<?php
/**
 * WooCommerce signal hooks — 15 additional server-side signals.
 *
 * @package Smart_Local_AI_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AtlasAI_Pro_WooCommerce
 */
class AtlasAI_Pro_WooCommerce {

	/**
	 * Register all WooCommerce hooks.
	 */
	public function register_hooks() {
		// Remove from cart.
		add_action( 'woocommerce_remove_cart_item', array( $this, 'on_remove_from_cart' ), 10, 2 );

		// Checkout started.
		add_action( 'woocommerce_checkout_process', array( $this, 'on_checkout_start' ) );

		// Order completed (purchase).
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_checkout_complete' ) );

		// Order refunded.
		add_action( 'woocommerce_order_refunded', array( $this, 'on_refund_request' ), 10, 2 );

		// Coupon applied.
		add_action( 'woocommerce_applied_coupon', array( $this, 'on_coupon_apply' ) );

		// Quantity changed in cart.
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_quantity_change' ), 10, 4 );

		// Subscription payment (WC Subscriptions plugin).
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'on_subscription_signup' ) );

		// Reorder (WC order again).
		add_action( 'woocommerce_ordered_again', array( $this, 'on_reorder' ) );
	}

	/**
	 * Item removed from cart.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param object $cart          WC Cart instance.
	 */
	public function on_remove_from_cart( $cart_item_key, $cart ) {
		$item = $cart->get_cart_item( $cart_item_key );
		if ( ! empty( $item['product_id'] ) ) {
			$this->store_event( 'remove_from_cart', (int) $item['product_id'], -3.0 );
		}
	}

	/**
	 * Checkout process started.
	 */
	public function on_checkout_start() {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$this->store_event( 'checkout_start', (int) $item['product_id'], 7.0 );
			}
		}
	}

	/**
	 * Order completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_checkout_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( $product_id ) {
				$this->store_event( 'checkout_complete', $product_id, 9.0 );
			}
		}
	}

	/**
	 * Order refunded.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public function on_refund_request( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( $product_id ) {
				$this->store_event( 'refund_request', $product_id, -6.0 );
			}
		}
	}

	/**
	 * Coupon applied.
	 *
	 * @param string $coupon_code Coupon code.
	 */
	public function on_coupon_apply( $coupon_code ) {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$this->store_event( 'coupon_apply', (int) $item['product_id'], 2.0 );
			}
		}
	}

	/**
	 * Cart item quantity changed.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $quantity      New quantity.
	 * @param int    $old_quantity  Old quantity.
	 * @param object $cart          WC Cart instance.
	 */
	public function on_quantity_change( $cart_item_key, $quantity, $old_quantity, $cart ) {
		$item = $cart->get_cart_item( $cart_item_key );
		if ( ! empty( $item['product_id'] ) ) {
			$weight = $quantity > $old_quantity ? 1.5 : -1.0;
			$this->store_event( 'quantity_change', (int) $item['product_id'], $weight );
		}
	}

	/**
	 * Subscription payment completed.
	 *
	 * @param object $subscription WC Subscription instance.
	 */
	public function on_subscription_signup( $subscription ) {
		if ( ! method_exists( $subscription, 'get_items' ) ) {
			return;
		}

		foreach ( $subscription->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( $product_id ) {
				$this->store_event( 'subscription_signup', $product_id, 8.0 );
			}
		}
	}

	/**
	 * User reordered a previous order.
	 *
	 * @param int $order_id Original order ID.
	 */
	public function on_reorder( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( $product_id ) {
				$this->store_event( 'reorder', $product_id, 7.0 );
			}
		}
	}

	/**
	 * Store a server-side event via the free plugin's tracker.
	 *
	 * @param string $event_type Signal type.
	 * @param int    $post_id    Post/product ID.
	 * @param float  $weight     Signal weight.
	 */
	private function store_event( $event_type, $post_id, $weight ) {
		$plugin = Smart_Local_AI::get_instance();
		$pf     = $plugin->get_module( 'personaflow' );

		if ( ! $pf ) {
			return;
		}

		// Access the tracker instance.
		$tracker = $pf->get_tracker();
		if ( ! $tracker || ! method_exists( $tracker, 'store_server_event' ) ) {
			return;
		}

		$tracker->store_server_event( $event_type, $post_id, 0, $weight );
	}
}
