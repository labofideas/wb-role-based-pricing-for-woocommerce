<?php

namespace WBCOM\WBRBPW;

use WBCOM\WBRBPW\Admin\Category_Pricing;
use WBCOM\WBRBPW\Admin\Pricing_Groups;
use WBCOM\WBRBPW\Admin\Product_Pricing;

defined( 'ABSPATH' ) || exit;

require_once WBRBPW_PATH . 'includes/class-settings.php';
require_once WBRBPW_PATH . 'includes/class-eligibility-resolver.php';
require_once WBRBPW_PATH . 'includes/class-price-calculator.php';
require_once WBRBPW_PATH . 'includes/class-pricing-engine.php';
require_once WBRBPW_PATH . 'includes/class-cart-pricing.php';
require_once WBRBPW_PATH . 'includes/class-order-audit.php';
require_once WBRBPW_PATH . 'includes/Admin/class-pricing-groups.php';
require_once WBRBPW_PATH . 'includes/Admin/class-product-pricing.php';
require_once WBRBPW_PATH . 'includes/Admin/class-category-pricing.php';

final class Plugin {
	private static ?self $instance = null;

	private Eligibility_Resolver $eligibility;

	private Price_Calculator $calculator;

	private function __construct() {
		$this->eligibility = new Eligibility_Resolver();
		$this->calculator  = new Price_Calculator( $this->eligibility );
	}

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		self::$instance->boot();
	}

	private function boot(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( WBRBPW_FILE ), array( $this, 'plugin_action_links' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_wc_required_notice' ) );
			return;
		}

		Settings::init();
		Pricing_Groups::init();
		Product_Pricing::init();
		Category_Pricing::init();
		Pricing_Engine::init( $this->calculator, $this->eligibility );
		Cart_Pricing::init( $this->calculator, $this->eligibility );
		Order_Audit::init();
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=wb_role_based_pricing' );
		$groups_url   = admin_url( 'edit.php?post_type=wb_pricing_group' );

		$custom_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wb-role-based-pricing' ) . '</a>',
			'<a href="' . esc_url( $groups_url ) . '">' . esc_html__( 'Pricing Groups', 'wb-role-based-pricing' ) . '</a>',
		);

		return array_merge( $custom_links, $links );
	}

	public function render_wc_required_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WB Role Based Pricing for WooCommerce requires WooCommerce to be active.', 'wb-role-based-pricing' );
		echo '</p></div>';
	}
}
