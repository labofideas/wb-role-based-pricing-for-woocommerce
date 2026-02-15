<?php

namespace WBCOM\WBRBPW\Admin;

defined( 'ABSPATH' ) || exit;

final class Product_Pricing {
	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'register_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_rules' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_fields' ), 10, 2 );
	}

	public static function register_tab( array $tabs ): array {
		$tabs['wb_pricing_groups'] = array(
			'label'    => __( 'Pricing Groups', 'wb-role-based-pricing' ),
			'target'   => 'wb_pricing_groups_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 80,
		);

		return $tabs;
	}

	public static function render_panel(): void {
		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$rules      = $product_id > 0 ? get_post_meta( $product_id, '_wb_pricing_group_prices', true ) : array();
		$rules      = is_array( $rules ) ? $rules : array();
		$groups     = Pricing_Groups::get_active_groups();

		echo '<div id="wb_pricing_groups_product_data" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( 'wbrbpw_save_product_rules', 'wbrbpw_product_rules_nonce' );

		if ( empty( $groups ) ) {
			echo '<p class="form-field">' . esc_html__( 'Create and enable at least one Pricing Group first.', 'wb-role-based-pricing' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<p>' . esc_html__( 'Set product-level pricing rules by group.', 'wb-role-based-pricing' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Group', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Enable', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Fixed Regular', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Fixed Sale', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Percent', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Amount', 'wb-role-based-pricing' ) . '</th>';
		echo '</tr></thead><tbody>';

			foreach ( $groups as $group ) {
			$group_id = (int) $group['id'];
			$rule     = isset( $rules[ $group_id ] ) && is_array( $rules[ $group_id ] ) ? $rules[ $group_id ] : array();
			$type     = isset( $rule['type'] ) ? (string) $rule['type'] : 'none';
			$enabled  = isset( $rule['enabled'] ) ? (string) $rule['enabled'] : '0';

			echo '<tr>';
			echo '<td>' . esc_html( (string) $group['name'] ) . '</td>';
			echo '<td><input type="checkbox" name="wbrbpw_product_rules[' . esc_attr( (string) $group_id ) . '][enabled]" value="1" ' . checked( '1', $enabled, false ) . '></td>';
			echo '<td><select name="wbrbpw_product_rules[' . esc_attr( (string) $group_id ) . '][type]">';
			echo '<option value="none" ' . selected( $type, 'none', false ) . '>' . esc_html__( 'None', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="fixed_price" ' . selected( $type, 'fixed_price', false ) . '>' . esc_html__( 'Fixed Price', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="percent" ' . selected( $type, 'percent', false ) . '>' . esc_html__( 'Percent', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="amount" ' . selected( $type, 'amount', false ) . '>' . esc_html__( 'Amount', 'wb-role-based-pricing' ) . '</option>';
			echo '</select></td>';
			echo '<td><input type="number" step="0.01" name="wbrbpw_product_rules[' . esc_attr( (string) $group_id ) . '][fixed_regular]" value="' . esc_attr( isset( $rule['fixed_regular'] ) ? (string) $rule['fixed_regular'] : '' ) . '"></td>';
			echo '<td><input type="number" step="0.01" name="wbrbpw_product_rules[' . esc_attr( (string) $group_id ) . '][fixed_sale]" value="' . esc_attr( isset( $rule['fixed_sale'] ) ? (string) $rule['fixed_sale'] : '' ) . '"></td>';
			echo '<td><input type="number" step="0.01" name="wbrbpw_product_rules[' . esc_attr( (string) $group_id ) . '][percent]" value="' . esc_attr( isset( $rule['percent'] ) ? (string) $rule['percent'] : '' ) . '"></td>';
			echo '<td><input type="number" step="0.01" name="wbrbpw_product_rules[' . esc_attr( (string) $group_id ) . '][amount]" value="' . esc_attr( isset( $rule['amount'] ) ? (string) $rule['amount'] : '' ) . '"></td>';
			echo '</tr>';
			}

			echo '</tbody></table>';

			if ( $product_id > 0 ) {
				$product = wc_get_product( $product_id );
				if ( $product instanceof \WC_Product && $product->is_type( 'variable' ) ) {
					echo '<hr/>';
					echo '<h3>' . esc_html__( 'Variation Bulk Actions', 'wb-role-based-pricing' ) . '</h3>';
					echo '<p class="description">' . esc_html__( 'Apply parent rules to all variations, clear variation rules, or apply a specific group rule to all variations.', 'wb-role-based-pricing' ) . '</p>';

					echo '<p><label><input type="checkbox" name="wbrbpw_bulk_copy_parent_all" value="1" /> ' . esc_html__( 'Copy all parent group rules to all variations', 'wb-role-based-pricing' ) . '</label></p>';
					echo '<p><label><input type="checkbox" name="wbrbpw_bulk_clear_all_variations" value="1" /> ' . esc_html__( 'Clear all variation group rules', 'wb-role-based-pricing' ) . '</label></p>';

					echo '<table class="widefat striped"><thead><tr>';
					echo '<th>' . esc_html__( 'Group', 'wb-role-based-pricing' ) . '</th>';
					echo '<th>' . esc_html__( 'Apply parent rule to all variations', 'wb-role-based-pricing' ) . '</th>';
					echo '<th>' . esc_html__( 'Clear this group from all variations', 'wb-role-based-pricing' ) . '</th>';
					echo '</tr></thead><tbody>';

					foreach ( $groups as $group ) {
						$group_id = (int) $group['id'];
						echo '<tr>';
						echo '<td>' . esc_html( (string) $group['name'] ) . '</td>';
						echo '<td><input type="checkbox" name="wbrbpw_bulk_apply_to_variations[' . esc_attr( (string) $group_id ) . ']" value="1" /></td>';
						echo '<td><input type="checkbox" name="wbrbpw_bulk_clear_group_variations[' . esc_attr( (string) $group_id ) . ']" value="1" /></td>';
						echo '</tr>';
					}

					echo '</tbody></table>';
				}
			}

			echo '</div>';
		}

	public static function save_product_rules( \WC_Product $product ): void {
		if ( ! isset( $_POST['wbrbpw_product_rules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbrbpw_product_rules_nonce'] ) ), 'wbrbpw_save_product_rules' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$raw_rules = isset( $_POST['wbrbpw_product_rules'] ) && is_array( $_POST['wbrbpw_product_rules'] ) ? wp_unslash( $_POST['wbrbpw_product_rules'] ) : array();
		$rules = array();

		foreach ( $raw_rules as $group_id => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$group_id = absint( $group_id );
			if ( $group_id <= 0 ) {
				continue;
			}

			$rules[ $group_id ] = self::sanitize_rule( $rule );
		}

			$product->update_meta_data( '_wb_pricing_group_prices', $rules );

			if ( $product->is_type( 'variable' ) ) {
				self::process_variation_bulk_actions( $product, $rules );
			}
		}

		/**
		 * @param array<int,array<string,mixed>> $parent_rules
		 */
		private static function process_variation_bulk_actions( \WC_Product $product, array $parent_rules ): void {
			$copy_parent_all = isset( $_POST['wbrbpw_bulk_copy_parent_all'] );
			$clear_all       = isset( $_POST['wbrbpw_bulk_clear_all_variations'] );
			$apply_groups    = isset( $_POST['wbrbpw_bulk_apply_to_variations'] ) && is_array( $_POST['wbrbpw_bulk_apply_to_variations'] )
				? array_map( 'absint', array_keys( wp_unslash( $_POST['wbrbpw_bulk_apply_to_variations'] ) ) )
				: array();
			$clear_groups    = isset( $_POST['wbrbpw_bulk_clear_group_variations'] ) && is_array( $_POST['wbrbpw_bulk_clear_group_variations'] )
				? array_map( 'absint', array_keys( wp_unslash( $_POST['wbrbpw_bulk_clear_group_variations'] ) ) )
				: array();

			if ( ! $copy_parent_all && ! $clear_all && empty( $apply_groups ) && empty( $clear_groups ) ) {
				return;
			}

			$children = $product->get_children();
			if ( empty( $children ) ) {
				return;
			}

			foreach ( $children as $variation_id ) {
				$variation_id = absint( $variation_id );
				if ( $variation_id <= 0 ) {
					continue;
				}

				$existing = get_post_meta( $variation_id, '_wb_pricing_group_prices', true );
				$existing = is_array( $existing ) ? $existing : array();

				if ( $copy_parent_all ) {
					$existing = $parent_rules;
				}

				if ( $clear_all ) {
					$existing = array();
				}

				foreach ( $apply_groups as $group_id ) {
					if ( $group_id <= 0 || empty( $parent_rules[ $group_id ] ) || ! is_array( $parent_rules[ $group_id ] ) ) {
						continue;
					}

					$existing[ $group_id ] = $parent_rules[ $group_id ];
				}

				foreach ( $clear_groups as $group_id ) {
					if ( $group_id <= 0 ) {
						continue;
					}

					unset( $existing[ $group_id ] );
				}

				update_post_meta( $variation_id, '_wb_pricing_group_prices', $existing );
			}
		}

	/**
	 * @param array<string,mixed> $rule
	 * @return array<string,mixed>
	 */
	private static function sanitize_rule( array $rule ): array {
		return array(
			'enabled'       => isset( $rule['enabled'] ) ? '1' : '0',
			'type'          => isset( $rule['type'] ) ? sanitize_key( (string) $rule['type'] ) : 'none',
			'fixed_regular' => isset( $rule['fixed_regular'] ) ? wc_format_decimal( $rule['fixed_regular'] ) : '',
			'fixed_sale'    => isset( $rule['fixed_sale'] ) ? wc_format_decimal( $rule['fixed_sale'] ) : '',
			'fixed_price'   => isset( $rule['fixed_price'] ) ? wc_format_decimal( $rule['fixed_price'] ) : '',
			'percent'       => isset( $rule['percent'] ) ? wc_format_decimal( $rule['percent'] ) : '',
			'amount'        => isset( $rule['amount'] ) ? wc_format_decimal( $rule['amount'] ) : '',
		);
	}

	public static function render_variation_fields( int $loop, array $variation_data, \WP_Post $variation_post ): void {
		$rules  = get_post_meta( $variation_post->ID, '_wb_pricing_group_prices', true );
		$rules  = is_array( $rules ) ? $rules : array();
		$groups = Pricing_Groups::get_active_groups();

		if ( empty( $groups ) ) {
			return;
		}

		echo '<div class="options_group">';
		echo '<p><strong>' . esc_html__( 'Pricing Group Rules', 'wb-role-based-pricing' ) . '</strong></p>';
		foreach ( $groups as $group ) {
			$group_id = (int) $group['id'];
			$rule     = isset( $rules[ $group_id ] ) && is_array( $rules[ $group_id ] ) ? $rules[ $group_id ] : array();
			$type     = isset( $rule['type'] ) ? (string) $rule['type'] : 'none';

			echo '<p style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
			echo '<strong>' . esc_html( (string) $group['name'] ) . '</strong>';
			echo '<label><input type="checkbox" name="wbrbpw_variation_rules[' . esc_attr( (string) $variation_post->ID ) . '][' . esc_attr( (string) $group_id ) . '][enabled]" value="1" ' . checked( '1', isset( $rule['enabled'] ) ? (string) $rule['enabled'] : '0', false ) . '> ' . esc_html__( 'Enable', 'wb-role-based-pricing' ) . '</label>';
			echo '<select name="wbrbpw_variation_rules[' . esc_attr( (string) $variation_post->ID ) . '][' . esc_attr( (string) $group_id ) . '][type]">';
			echo '<option value="none" ' . selected( $type, 'none', false ) . '>' . esc_html__( 'None', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="fixed_price" ' . selected( $type, 'fixed_price', false ) . '>' . esc_html__( 'Fixed Price', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="percent" ' . selected( $type, 'percent', false ) . '>' . esc_html__( 'Percent', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="amount" ' . selected( $type, 'amount', false ) . '>' . esc_html__( 'Amount', 'wb-role-based-pricing' ) . '</option>';
			echo '</select>';
			echo '<input type="number" step="0.01" placeholder="' . esc_attr__( 'Fixed regular', 'wb-role-based-pricing' ) . '" name="wbrbpw_variation_rules[' . esc_attr( (string) $variation_post->ID ) . '][' . esc_attr( (string) $group_id ) . '][fixed_regular]" value="' . esc_attr( isset( $rule['fixed_regular'] ) ? (string) $rule['fixed_regular'] : '' ) . '">';
			echo '<input type="number" step="0.01" placeholder="' . esc_attr__( 'Fixed sale', 'wb-role-based-pricing' ) . '" name="wbrbpw_variation_rules[' . esc_attr( (string) $variation_post->ID ) . '][' . esc_attr( (string) $group_id ) . '][fixed_sale]" value="' . esc_attr( isset( $rule['fixed_sale'] ) ? (string) $rule['fixed_sale'] : '' ) . '">';
			echo '<input type="number" step="0.01" placeholder="' . esc_attr__( '%', 'wb-role-based-pricing' ) . '" name="wbrbpw_variation_rules[' . esc_attr( (string) $variation_post->ID ) . '][' . esc_attr( (string) $group_id ) . '][percent]" value="' . esc_attr( isset( $rule['percent'] ) ? (string) $rule['percent'] : '' ) . '">';
			echo '<input type="number" step="0.01" placeholder="' . esc_attr__( 'Amount', 'wb-role-based-pricing' ) . '" name="wbrbpw_variation_rules[' . esc_attr( (string) $variation_post->ID ) . '][' . esc_attr( (string) $group_id ) . '][amount]" value="' . esc_attr( isset( $rule['amount'] ) ? (string) $rule['amount'] : '' ) . '">';
			echo '</p>';
		}
		echo '</div>';
	}

	public static function save_variation_fields( int $variation_id, int $index ): void {
		if ( ! isset( $_POST['wbrbpw_product_rules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbrbpw_product_rules_nonce'] ) ), 'wbrbpw_save_product_rules' ) ) {
			return;
		}

		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id <= 0 || ! current_user_can( 'edit_product', $parent_id ) ) {
			return;
		}

		$all_rules = isset( $_POST['wbrbpw_variation_rules'] ) && is_array( $_POST['wbrbpw_variation_rules'] ) ? wp_unslash( $_POST['wbrbpw_variation_rules'] ) : array();
		if ( empty( $all_rules[ $variation_id ] ) || ! is_array( $all_rules[ $variation_id ] ) ) {
			return;
		}

		$rules = array();
		foreach ( $all_rules[ $variation_id ] as $group_id => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$group_id = absint( $group_id );
			if ( $group_id <= 0 ) {
				continue;
			}

			$rules[ $group_id ] = self::sanitize_rule( $rule );
		}

		update_post_meta( $variation_id, '_wb_pricing_group_prices', $rules );
	}
}
