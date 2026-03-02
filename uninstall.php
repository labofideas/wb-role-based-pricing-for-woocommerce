<?php
/**
 * Uninstall cleanup for WB Role Based Pricing for WooCommerce.
 *
 * @package WBRBPW
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_names = array(
	'wbrbpw_enabled',
	'wbrbpw_base_price',
	'wbrbpw_sale_interaction',
	'wbrbpw_guest_pricing',
	'wbrbpw_guest_group',
	'wbrbpw_group_resolution',
	'wbrbpw_source_priority',
	'wbrbpw_rounding',
	'wbrbpw_hide_guest_price',
	'wbrbpw_guest_text',
	'wbrbpw_version',
);

foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
}
