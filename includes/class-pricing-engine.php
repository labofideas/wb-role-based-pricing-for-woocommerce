<?php

namespace WBCOM\WBRBPW;

defined( 'ABSPATH' ) || exit;

final class Pricing_Engine {
	private static ?self $instance = null;

	private Price_Calculator $calculator;

	private Eligibility_Resolver $eligibility;

	private bool $in_filter = false;

	public static function init( Price_Calculator $calculator, Eligibility_Resolver $eligibility ): void {
		if ( null === self::$instance ) {
			self::$instance = new self( $calculator, $eligibility );
		}

		self::$instance->register_hooks();
	}

	private function __construct( Price_Calculator $calculator, Eligibility_Resolver $eligibility ) {
		$this->calculator  = $calculator;
		$this->eligibility = $eligibility;
	}

	private function register_hooks(): void {
		add_filter( 'woocommerce_get_price_html', array( $this, 'maybe_hide_guest_price_html' ), 999, 2 );
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_price' ), 999, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_price' ), 999, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price' ), 999, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'variation_price_hash' ), 10, 3 );
	}

	public function maybe_hide_guest_price_html( string $price_html, \WC_Product $product ): string {
		if ( ! Settings::is_enabled() ) {
			return $price_html;
		}

		if ( ! is_user_logged_in() && Settings::hide_guest_price() ) {
			return '<span class="wbrbpw-login-price-text">' . esc_html( Settings::get_guest_text() ) . '</span>';
		}

		return $price_html;
	}

	/**
	 * @param float|string $price
	 * @return float|string
	 */
	public function filter_price( $price, \WC_Product $product ) {
		if ( ! Settings::is_enabled() || '' === $price || null === $price ) {
			return $price;
		}

		if ( $this->in_filter ) {
			return $price;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price;
		}

		$group_id = $this->eligibility->get_primary_group_id();
		if ( $group_id <= 0 ) {
			return $price;
		}

		$resolved = $this->calculator->resolve_price( $product, $group_id );
		if ( null === $resolved ) {
			return $price;
		}

		$this->in_filter = true;
		$final = wc_format_decimal( $resolved['final_price'], wc_get_price_decimals() );
		$this->in_filter = false;

		return $final;
	}

	/**
	 * @param float|string $price
	 * @return float|string
	 */
	public function filter_variation_price( $price, \WC_Product_Variation $variation, \WC_Product_Variable $product ) {
		return $this->filter_price( $price, $variation );
	}

	/**
	 * @param array<string,mixed> $price_hash
	 * @return array<string,mixed>
	 */
	public function variation_price_hash( array $price_hash, \WC_Product $product, bool $for_display ): array {
		$price_hash['wbrbpw_enabled'] = Settings::is_enabled() ? '1' : '0';
		$price_hash['wbrbpw_group']   = $this->eligibility->get_primary_group_id();
		$price_hash['wbrbpw_guest']   = is_user_logged_in() ? '0' : '1';
		return $price_hash;
	}
}
