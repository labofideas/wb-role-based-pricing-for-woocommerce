<?php
/**
 * Plugin Name: WB Role Based Pricing for WooCommerce
 * Plugin URI:  https://wbcomdesigns.com/
 * Description: Dynamic WooCommerce pricing by role and pricing groups.
 * Version:     1.0.0
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com/
 * Text Domain: wb-role-based-pricing-for-woocommerce
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WBRBPW_FILE' ) ) {
	define( 'WBRBPW_FILE', __FILE__ );
}

if ( ! defined( 'WBRBPW_PATH' ) ) {
	define( 'WBRBPW_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WBRBPW_URL' ) ) {
	define( 'WBRBPW_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WBRBPW_VERSION' ) ) {
	define( 'WBRBPW_VERSION', '1.0.0' );
}

require_once WBRBPW_PATH . 'includes/class-plugin.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\WBCOM\WBRBPW\Plugin::init();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'WB Role Based Pricing for WooCommerce requires WooCommerce to be active.', 'wb-role-based-pricing-for-woocommerce' ) );
		}

		update_option( 'wbrbpw_version', WBRBPW_VERSION );

		\WBCOM\WBRBPW\Admin\Pricing_Groups::register_post_type();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
