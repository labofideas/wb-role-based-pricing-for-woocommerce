<?php

namespace WBCOM\WBRBPW\Admin;

defined( 'ABSPATH' ) || exit;

final class Category_Pricing {
	public static function init(): void {
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'render_add_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'render_edit_fields' ) );
		add_action( 'created_product_cat', array( __CLASS__, 'save_fields' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_fields' ) );
	}

	public static function render_add_fields(): void {
		self::render_fields_markup( 0 );
	}

	public static function render_edit_fields( \WP_Term $term ): void {
		echo '<tr class="form-field"><th colspan="2">';
		self::render_fields_markup( (int) $term->term_id );
		echo '</th></tr>';
	}

	private static function render_fields_markup( int $term_id ): void {
		$rules  = $term_id > 0 ? get_term_meta( $term_id, '_wb_pricing_group_category_rules', true ) : array();
		$rules  = is_array( $rules ) ? $rules : array();
		$groups = Pricing_Groups::get_active_groups();

		wp_nonce_field( 'wbrbpw_save_category_rules', 'wbrbpw_category_rules_nonce' );
		echo '<h3>' . esc_html__( 'Pricing Group Category Rules', 'wb-role-based-pricing' ) . '</h3>';

		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'Create and enable at least one Pricing Group first.', 'wb-role-based-pricing' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Group', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Enable', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Behavior', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Percent', 'wb-role-based-pricing' ) . '</th>';
		echo '<th>' . esc_html__( 'Amount', 'wb-role-based-pricing' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $groups as $group ) {
			$group_id = (int) $group['id'];
			$rule     = isset( $rules[ $group_id ] ) && is_array( $rules[ $group_id ] ) ? $rules[ $group_id ] : array();
			$type     = isset( $rule['type'] ) ? (string) $rule['type'] : 'none';
			$behavior = isset( $rule['behavior'] ) ? (string) $rule['behavior'] : 'if_no_product_rule';

			echo '<tr>';
			echo '<td>' . esc_html( (string) $group['name'] ) . '</td>';
			echo '<td><input type="checkbox" name="wbrbpw_category_rules[' . esc_attr( (string) $group_id ) . '][enabled]" value="1" ' . checked( '1', isset( $rule['enabled'] ) ? (string) $rule['enabled'] : '0', false ) . '></td>';
			echo '<td><select name="wbrbpw_category_rules[' . esc_attr( (string) $group_id ) . '][type]">';
			echo '<option value="none" ' . selected( $type, 'none', false ) . '>' . esc_html__( 'None', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="percent" ' . selected( $type, 'percent', false ) . '>' . esc_html__( 'Percent', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="amount" ' . selected( $type, 'amount', false ) . '>' . esc_html__( 'Amount', 'wb-role-based-pricing' ) . '</option>';
			echo '</select></td>';
			echo '<td><select name="wbrbpw_category_rules[' . esc_attr( (string) $group_id ) . '][behavior]">';
			echo '<option value="if_no_product_rule" ' . selected( $behavior, 'if_no_product_rule', false ) . '>' . esc_html__( 'Only if no product rule', 'wb-role-based-pricing' ) . '</option>';
			echo '<option value="override_product_rules" ' . selected( $behavior, 'override_product_rules', false ) . '>' . esc_html__( 'Override product rules', 'wb-role-based-pricing' ) . '</option>';
			echo '</select></td>';
			echo '<td><input type="number" step="0.01" name="wbrbpw_category_rules[' . esc_attr( (string) $group_id ) . '][percent]" value="' . esc_attr( isset( $rule['percent'] ) ? (string) $rule['percent'] : '' ) . '"></td>';
			echo '<td><input type="number" step="0.01" name="wbrbpw_category_rules[' . esc_attr( (string) $group_id ) . '][amount]" value="' . esc_attr( isset( $rule['amount'] ) ? (string) $rule['amount'] : '' ) . '"></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	public static function save_fields( int $term_id ): void {
		if ( ! isset( $_POST['wbrbpw_category_rules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbrbpw_category_rules_nonce'] ) ), 'wbrbpw_save_category_rules' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		$raw_rules = isset( $_POST['wbrbpw_category_rules'] ) && is_array( $_POST['wbrbpw_category_rules'] ) ? wp_unslash( $_POST['wbrbpw_category_rules'] ) : array();
		$rules = array();

		foreach ( $raw_rules as $group_id => $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$group_id = absint( $group_id );
			if ( $group_id <= 0 ) {
				continue;
			}

			$rules[ $group_id ] = array(
				'enabled'  => isset( $rule['enabled'] ) ? '1' : '0',
				'type'     => isset( $rule['type'] ) ? sanitize_key( (string) $rule['type'] ) : 'none',
				'behavior' => isset( $rule['behavior'] ) ? sanitize_key( (string) $rule['behavior'] ) : 'if_no_product_rule',
				'percent'  => isset( $rule['percent'] ) ? wc_format_decimal( $rule['percent'] ) : '',
				'amount'   => isset( $rule['amount'] ) ? wc_format_decimal( $rule['amount'] ) : '',
			);
		}

		update_term_meta( $term_id, '_wb_pricing_group_category_rules', $rules );
	}
}
