<?php
/**
 * Automated regression smoke tests for WB Role Based Pricing.
 *
 * Run:
 * wp eval-file wp-content/plugins/wb-role-based-pricing-for-woocommerce/scripts/regression-smoke.php --allow-root
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	echo wp_json_encode(
		array(
			'pass'   => false,
			'error'  => 'WooCommerce is not active.',
			'tests'  => array(),
		),
		JSON_PRETTY_PRINT
	) . "\n";
	return;
}

$tests      = array();
$created_ids = array(
	'posts' => array(),
	'terms' => array(),
);
$original_options = array(
	'wbrbpw_enabled'         => get_option( 'wbrbpw_enabled', 'yes' ),
	'wbrbpw_source_priority' => get_option( 'wbrbpw_source_priority', 'subscription,membership,role' ),
	'wbrbpw_base_price'      => get_option( 'wbrbpw_base_price', 'lowest' ),
);
$original_user = get_current_user_id();

/**
 * @param string $name
 * @param bool   $pass
 * @param string $details
 */
$record_test = static function ( string $name, bool $pass, string $details = '' ) use ( &$tests ): void {
	$tests[] = array(
		'name'    => $name,
		'pass'    => $pass,
		'details' => $details,
	);
};

try {
	$admin_id = (int) username_exists( 'qaadmin' );
	if ( $admin_id <= 0 ) {
		$admin_id = (int) get_current_user_id();
	}

	if ( $admin_id <= 0 ) {
		throw new RuntimeException( 'No admin user available for smoke tests.' );
	}

	wp_set_current_user( $admin_id );
	update_option( 'wbrbpw_enabled', 'yes' );

	// Test 1: cart idempotency (no stacked discount on repeated recalculation).
	$group_id = (int) wp_insert_post(
		array(
			'post_type'   => 'wb_pricing_group',
			'post_status' => 'publish',
			'post_title'  => 'QA Smoke Cart Group',
		)
	);
	$created_ids['posts'][] = $group_id;
	update_post_meta( $group_id, '_wb_group_enabled', '1' );
	update_post_meta( $group_id, '_wb_group_priority', 1 );
	update_post_meta( $group_id, '_wb_group_role_map', array( 'administrator' ) );
	update_post_meta( $group_id, '_wb_group_default_rule', array( 'type' => 'percent', 'percent' => '-20' ) );

	$product = new WC_Product_Simple();
	$product->set_name( 'QA Smoke Product' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '100' );
	$product_id = (int) $product->save();
	$created_ids['posts'][] = $product_id;

	$eligible   = new WBCOM\WBRBPW\Eligibility_Resolver();
	$calculator = new WBCOM\WBRBPW\Price_Calculator( $eligible );
	$cart_class = new ReflectionClass( WBCOM\WBRBPW\Cart_Pricing::class );
	$cart_logic = $cart_class->newInstanceWithoutConstructor();

	$calculator_property = $cart_class->getProperty( 'calculator' );
	$calculator_property->setAccessible( true );
	$calculator_property->setValue( $cart_logic, $calculator );

	$eligibility_property = $cart_class->getProperty( 'eligibility' );
	$eligibility_property->setAccessible( true );
	$eligibility_property->setValue( $cart_logic, $eligible );

	$cart_product = wc_get_product( $product_id );
	if ( ! $cart_product instanceof WC_Product ) {
		throw new RuntimeException( 'Failed to load cart smoke product.' );
	}

	$cart                = new WC_Cart();
	$cart->cart_contents = array(
		'qa-smoke-item' => array(
			'data' => $cart_product,
		),
	);

	$cart_logic->apply_cart_prices( $cart );
	$first_price = (float) $cart_product->get_price( 'edit' );
	$cart_logic->apply_cart_prices( $cart );
	$second_price = (float) $cart_product->get_price( 'edit' );

	$record_test(
		'Cart Idempotency',
		( 80.0 === $first_price && 80.0 === $second_price ),
		'first=' . $first_price . ', second=' . $second_price
	);

	// Test 2: variation category fallback to parent categories.
	$term = wp_insert_term( 'QA Smoke Cat', 'product_cat' );
	if ( is_wp_error( $term ) ) {
		throw new RuntimeException( 'Failed creating smoke category: ' . $term->get_error_message() );
	}
	$term_id = (int) $term['term_id'];
	$created_ids['terms'][] = $term_id;

	update_term_meta(
		$term_id,
		'_wb_pricing_group_category_rules',
		array(
			$group_id => array(
				'enabled'  => '1',
				'type'     => 'amount',
				'behavior' => 'if_no_product_rule',
				'amount'   => '-5',
			),
		)
	);

	$parent = new WC_Product_Variable();
	$parent->set_name( 'QA Smoke Variable Parent' );
	$parent->set_status( 'publish' );
	$parent_id = (int) $parent->save();
	$created_ids['posts'][] = $parent_id;
	wp_set_post_terms( $parent_id, array( $term_id ), 'product_cat' );

	$attr = new WC_Product_Attribute();
	$attr->set_id( 0 );
	$attr->set_name( 'Size' );
	$attr->set_options( array( 'S' ) );
	$attr->set_visible( true );
	$attr->set_variation( true );
	$parent->set_attributes( array( $attr ) );
	$parent->save();

	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $parent_id );
	$variation->set_attributes( array( 'size' => 'S' ) );
	$variation->set_regular_price( '50' );
	$variation_id = (int) $variation->save();
	$created_ids['posts'][] = $variation_id;

	$resolved = $calculator->resolve_price( wc_get_product( $variation_id ), $group_id );
	$final    = is_array( $resolved ) ? (float) $resolved['final_price'] : -1;
	$record_test( 'Variation Category Fallback', ( 45.0 === $final ), 'final=' . $final );

	// Test 3: settings allowlist fallbacks.
	update_option( 'wbrbpw_base_price', 'not-valid' );
	$base_source = WBCOM\WBRBPW\Settings::get_base_price_source();
	$record_test( 'Settings Allowlist Fallback', ( 'lowest' === $base_source ), 'base_source=' . $base_source );

	// Test 4: nonce guard on product rule save.
	$nonce_product = new WC_Product_Simple();
	$nonce_product->set_name( 'QA Smoke Nonce Product' );
	$nonce_product->set_status( 'publish' );
	$nonce_product->set_regular_price( '30' );
	$nonce_product_id = (int) $nonce_product->save();
	$created_ids['posts'][] = $nonce_product_id;

	$before_meta = get_post_meta( $nonce_product_id, '_wb_pricing_group_prices', true );
	$_POST       = array(
		'wbrbpw_product_rules' => array(
			$group_id => array(
				'enabled' => '1',
				'type'    => 'amount',
				'amount'  => '-2',
			),
		),
	);
	WBCOM\WBRBPW\Admin\Product_Pricing::save_product_rules( wc_get_product( $nonce_product_id ) );
	$after_meta = get_post_meta( $nonce_product_id, '_wb_pricing_group_prices', true );
	$record_test( 'Security Nonce Guard', ( $before_meta === $after_meta ) );

	$_POST = array();
} catch ( Throwable $e ) {
	$record_test( 'Runner Fatal', false, $e->getMessage() );
}

// Cleanup.
foreach ( $created_ids['posts'] as $post_id ) {
	if ( $post_id > 0 ) {
		wp_delete_post( $post_id, true );
	}
}

foreach ( $created_ids['terms'] as $term_id ) {
	if ( $term_id > 0 ) {
		wp_delete_term( $term_id, 'product_cat' );
	}
}

update_option( 'wbrbpw_enabled', $original_options['wbrbpw_enabled'] );
update_option( 'wbrbpw_source_priority', $original_options['wbrbpw_source_priority'] );
update_option( 'wbrbpw_base_price', $original_options['wbrbpw_base_price'] );
wp_set_current_user( (int) $original_user );

$all_passed = ! array_filter(
	$tests,
	static function ( array $result ): bool {
		return empty( $result['pass'] );
	}
);

echo wp_json_encode(
	array(
		'pass'  => $all_passed,
		'tests' => $tests,
	),
	JSON_PRETTY_PRINT
) . "\n";
