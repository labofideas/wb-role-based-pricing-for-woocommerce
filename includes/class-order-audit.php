<?php

namespace WBCOM\WBRBPW;

defined( 'ABSPATH' ) || exit;

final class Order_Audit {
	public static function init(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'store_pricing_meta' ), 10, 4 );
	}

	/**
	 * @param array<string,mixed> $values
	 */
	public static function store_pricing_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		if ( empty( $values['wbrbpw_pricing'] ) || ! is_array( $values['wbrbpw_pricing'] ) ) {
			return;
		}

		$pricing = $values['wbrbpw_pricing'];

		$item->add_meta_data( '_wb_applied_pricing_group', isset( $pricing['group_id'] ) ? absint( $pricing['group_id'] ) : 0, true );
		$item->add_meta_data( '_wb_pricing_source', isset( $pricing['source'] ) ? sanitize_text_field( (string) $pricing['source'] ) : '', true );
		$item->add_meta_data( '_wb_base_price', isset( $pricing['base_price'] ) ? wc_format_decimal( (string) $pricing['base_price'] ) : '', true );
		$item->add_meta_data( '_wb_final_price', isset( $pricing['final_price'] ) ? wc_format_decimal( (string) $pricing['final_price'] ) : '', true );

		if ( isset( $pricing['adjustment'] ) && is_array( $pricing['adjustment'] ) ) {
			$item->add_meta_data( '_wb_adjustment_details', wp_json_encode( $pricing['adjustment'] ), true );
		}
	}
}
