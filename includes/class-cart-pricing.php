<?php

namespace WBCOM\WBRBPW;

defined( 'ABSPATH' ) || exit;

final class Cart_Pricing {
	private static ?self $instance = null;

	private const CART_ORIGINAL_PRICE_KEY = 'wbrbpw_original_price';

	private Price_Calculator $calculator;

	private Eligibility_Resolver $eligibility;

	public static function init( Price_Calculator $calculator, Eligibility_Resolver $eligibility ): void {
		if ( null === self::$instance ) {
			self::$instance = new self( $calculator, $eligibility );
		}

		add_action( 'woocommerce_before_calculate_totals', array( self::$instance, 'apply_cart_prices' ), 20 );
	}

	private function __construct( Price_Calculator $calculator, Eligibility_Resolver $eligibility ) {
		$this->calculator  = $calculator;
		$this->eligibility = $eligibility;
	}

	public function apply_cart_prices( \WC_Cart $cart ): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( $cart->is_empty() ) {
			return;
		}

		$group_id     = $this->eligibility->get_primary_group_id();
		$group_source = $this->eligibility->get_primary_group_source();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
				continue;
			}

			$product  = $cart_item['data'];
			$original_price = isset( $cart_item[ self::CART_ORIGINAL_PRICE_KEY ] ) ? (float) $cart_item[ self::CART_ORIGINAL_PRICE_KEY ] : (float) $product->get_price( 'edit' );

			$cart->cart_contents[ $cart_item_key ][ self::CART_ORIGINAL_PRICE_KEY ] = $original_price;
			$product->set_price( $original_price );
			unset( $cart->cart_contents[ $cart_item_key ]['wbrbpw_pricing'] );

			if ( $group_id <= 0 ) {
				continue;
			}

			$resolved = $this->calculator->resolve_price( $product, $group_id );

			if ( null === $resolved ) {
				continue;
			}

			$resolved_price = (float) $resolved['final_price'];
			$product->set_price( $resolved_price );

			$cart->cart_contents[ $cart_item_key ]['wbrbpw_pricing'] = array(
				'group_id'    => (int) $resolved['group_id'],
				'group_source'=> sanitize_key( $group_source ),
				'source'      => (string) $resolved['source'],
				'base_price'  => (float) $resolved['base_price'],
				'final_price' => $resolved_price,
				'adjustment'  => is_array( $resolved['adjustment'] ) ? $resolved['adjustment'] : array(),
			);
		}
	}
}
