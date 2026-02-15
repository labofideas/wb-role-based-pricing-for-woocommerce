<?php

namespace WBCOM\WBRBPW;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public const OPTION_ENABLED = 'wbrbpw_enabled';
	public const OPTION_BASE_PRICE = 'wbrbpw_base_price';
	public const OPTION_SALE_INTERACTION = 'wbrbpw_sale_interaction';
	public const OPTION_GUEST_PRICING = 'wbrbpw_guest_pricing';
	public const OPTION_GUEST_GROUP = 'wbrbpw_guest_group';
	public const OPTION_GROUP_RESOLUTION = 'wbrbpw_group_resolution';
	public const OPTION_SOURCE_PRIORITY = 'wbrbpw_source_priority';
	public const OPTION_ROUNDING = 'wbrbpw_rounding';
	public const OPTION_HIDE_GUEST_PRICE = 'wbrbpw_hide_guest_price';
	public const OPTION_GUEST_TEXT = 'wbrbpw_guest_text';

	public static function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 60 );
		add_action( 'woocommerce_settings_tabs_wb_role_based_pricing', array( __CLASS__, 'render_settings' ) );
		add_action( 'woocommerce_update_options_wb_role_based_pricing', array( __CLASS__, 'update_settings' ) );
	}

	public static function add_settings_tab( array $tabs ): array {
		$tabs['wb_role_based_pricing'] = __( 'WB Role Based Pricing', 'wb-role-based-pricing' );
		return $tabs;
	}

	public static function render_settings(): void {
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function update_settings(): void {
		woocommerce_update_options( self::get_settings() );
	}

	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'yes' );
	}

	public static function get_base_price_source(): string {
		return get_option( self::OPTION_BASE_PRICE, 'lowest' );
	}

	public static function get_sale_interaction(): string {
		return get_option( self::OPTION_SALE_INTERACTION, 'respect_sale' );
	}

	public static function get_guest_pricing_mode(): string {
		return get_option( self::OPTION_GUEST_PRICING, 'default' );
	}

	public static function get_guest_group_id(): int {
		return absint( get_option( self::OPTION_GUEST_GROUP, 0 ) );
	}

	public static function get_group_resolution_mode(): string {
		return get_option( self::OPTION_GROUP_RESOLUTION, 'priority' );
	}

	public static function get_rounding_mode(): string {
		return get_option( self::OPTION_ROUNDING, 'none' );
	}

	/**
	 * @return string[]
	 */
	public static function get_source_priority_order(): array {
		$raw = (string) get_option( self::OPTION_SOURCE_PRIORITY, 'subscription,membership,role' );
		$parts = array_filter(
			array_map(
				'sanitize_key',
				array_map( 'trim', explode( ',', $raw ) )
			)
		);
		$allowed = array( 'subscription', 'membership', 'role' );
		$order = array_values( array_intersect( $parts, $allowed ) );

		foreach ( $allowed as $source ) {
			if ( ! in_array( $source, $order, true ) ) {
				$order[] = $source;
			}
		}

		return $order;
	}

	public static function hide_guest_price(): bool {
		return 'yes' === get_option( self::OPTION_HIDE_GUEST_PRICE, 'no' );
	}

	public static function get_guest_text(): string {
		$text = (string) get_option( self::OPTION_GUEST_TEXT, __( 'Login to see pricing', 'wb-role-based-pricing' ) );
		return '' !== trim( $text ) ? $text : __( 'Login to see pricing', 'wb-role-based-pricing' );
	}

	private static function get_settings(): array {
		return array(
			array(
				'name' => __( 'WB Role Based Pricing', 'wb-role-based-pricing' ),
				'type' => 'title',
				'desc' => __( 'Configure role/group based dynamic pricing behavior.', 'wb-role-based-pricing' ),
				'id'   => 'wbrbpw_settings_section',
			),
			array(
				'name'    => __( 'Enable plugin', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_ENABLED,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'name'    => __( 'Base price for adjustments', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_BASE_PRICE,
				'type'    => 'select',
				'options' => array(
					'regular' => __( 'Regular price', 'wb-role-based-pricing' ),
					'sale'    => __( 'Sale price (if on sale)', 'wb-role-based-pricing' ),
					'lowest'  => __( 'Lowest of regular/sale', 'wb-role-based-pricing' ),
				),
				'default' => 'lowest',
			),
			array(
				'name'    => __( 'Sale price interaction', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_SALE_INTERACTION,
				'type'    => 'select',
				'options' => array(
					'respect_sale' => __( 'Respect Woo sale', 'wb-role-based-pricing' ),
					'override_sale' => __( 'Pricing Group overrides sale', 'wb-role-based-pricing' ),
				),
				'default' => 'respect_sale',
			),
			array(
				'name'    => __( 'Guest pricing', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_GUEST_PRICING,
				'type'    => 'select',
				'options' => array(
					'default' => __( 'Use default Woo pricing', 'wb-role-based-pricing' ),
					'group'   => __( 'Assign guests to a pricing group', 'wb-role-based-pricing' ),
				),
				'default' => 'default',
			),
			array(
				'name'              => __( 'Guest pricing group ID', 'wb-role-based-pricing' ),
				'id'                => self::OPTION_GUEST_GROUP,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'default'           => '0',
				'desc'              => __( 'Set a Pricing Group post ID to apply for guests when guest mode is enabled.', 'wb-role-based-pricing' ),
				'desc_tip'          => true,
			),
			array(
				'name'    => __( 'Multiple eligibility resolution', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_GROUP_RESOLUTION,
				'type'    => 'select',
				'options' => array(
					'priority' => __( 'Highest priority group wins', 'wb-role-based-pricing' ),
				),
				'default' => 'priority',
			),
			array(
				'name'    => __( 'Eligibility source priority', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_SOURCE_PRIORITY,
				'type'    => 'select',
				'options' => array(
					'subscription,membership,role' => __( 'Subscription > Membership > Role', 'wb-role-based-pricing' ),
					'membership,subscription,role' => __( 'Membership > Subscription > Role', 'wb-role-based-pricing' ),
					'role,membership,subscription' => __( 'Role > Membership > Subscription', 'wb-role-based-pricing' ),
					'role,subscription,membership' => __( 'Role > Subscription > Membership', 'wb-role-based-pricing' ),
				),
				'default' => 'subscription,membership,role',
				'desc'    => __( 'Controls which source wins when a user qualifies from multiple sources.', 'wb-role-based-pricing' ),
			),
			array(
				'name'    => __( 'Rounding', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_ROUNDING,
				'type'    => 'select',
				'options' => array(
					'none' => __( 'None', 'wb-role-based-pricing' ),
					'2'    => __( 'Round to 2 decimals', 'wb-role-based-pricing' ),
					'0'    => __( 'Round to nearest integer', 'wb-role-based-pricing' ),
				),
				'default' => 'none',
			),
			array(
				'name'    => __( 'Hide prices for guests', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_HIDE_GUEST_PRICE,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'name'    => __( 'Guest hidden price text', 'wb-role-based-pricing' ),
				'id'      => self::OPTION_GUEST_TEXT,
				'type'    => 'text',
				'default' => __( 'Login to see pricing', 'wb-role-based-pricing' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbrbpw_settings_section',
			),
		);
	}
}
