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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_settings_assets' ) );
	}

	public static function add_settings_tab( array $tabs ): array {
		$tabs['wb_role_based_pricing'] = __( 'WB Role Based Pricing', 'wb-role-based-pricing-for-woocommerce' );
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
		return self::get_allowed_option( self::OPTION_BASE_PRICE, 'lowest', array( 'regular', 'sale', 'lowest' ) );
	}

	public static function get_sale_interaction(): string {
		return self::get_allowed_option( self::OPTION_SALE_INTERACTION, 'respect_sale', array( 'respect_sale', 'override_sale' ) );
	}

	public static function get_guest_pricing_mode(): string {
		return self::get_allowed_option( self::OPTION_GUEST_PRICING, 'default', array( 'default', 'group' ) );
	}

	public static function get_guest_group_id(): int {
		return absint( get_option( self::OPTION_GUEST_GROUP, 0 ) );
	}

	public static function get_group_resolution_mode(): string {
		return self::get_allowed_option( self::OPTION_GROUP_RESOLUTION, 'priority', array( 'priority' ) );
	}

	public static function get_rounding_mode(): string {
		return self::get_allowed_option( self::OPTION_ROUNDING, 'none', array( 'none', '2', '0' ) );
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
		$text = (string) get_option( self::OPTION_GUEST_TEXT, __( 'Login to see pricing', 'wb-role-based-pricing-for-woocommerce' ) );
		return '' !== trim( $text ) ? $text : __( 'Login to see pricing', 'wb-role-based-pricing-for-woocommerce' );
	}

	public static function enqueue_settings_assets(): void {
		if ( ! self::is_wbrbpw_settings_screen() ) {
			return;
		}

		wp_register_style( 'wbrbpw-admin-settings', false, array(), WBRBPW_VERSION );
		wp_enqueue_style( 'wbrbpw-admin-settings' );
		wp_add_inline_style(
			'wbrbpw-admin-settings',
			':root{--wbrbpw-bg:#f6f8fb;--wbrbpw-card:#fff;--wbrbpw-border:#dce3ee;--wbrbpw-text:#19212c;--wbrbpw-muted:#5f6f86;--wbrbpw-primary:#0f766e;--wbrbpw-shadow:0 12px 32px rgba(17,40,75,.08);}
			body.woocommerce_page_wc-settings{background:var(--wbrbpw-bg);}
			.woocommerce .wc-settings-sub-nav li a[href*=\"tab=wb_role_based_pricing\"]{font-weight:600;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen #mainform{max-width:1100px;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen h2{display:none;}
			#wbrbpw-settings-hero{background:linear-gradient(135deg,#042f2e 0%,#115e59 52%,#0f766e 100%);color:#fff;border-radius:18px;padding:26px 28px;margin:16px 0 22px;box-shadow:var(--wbrbpw-shadow);}
			#wbrbpw-settings-hero .wbrbpw-badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.16);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin-bottom:10px;}
			#wbrbpw-settings-hero h3{margin:0 0 6px;font-size:24px;line-height:1.25;color:#fff;}
			#wbrbpw-settings-hero p{margin:0;color:rgba(255,255,255,.88);font-size:14px;}
			#wbrbpw-health{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 16px;}
			#wbrbpw-health .wbrbpw-health-card{background:#fff;border:1px solid var(--wbrbpw-border);border-radius:14px;padding:14px 14px 12px;box-shadow:var(--wbrbpw-shadow);}
			#wbrbpw-health .wbrbpw-health-label{display:block;color:#667890;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px;}
			#wbrbpw-health .wbrbpw-health-value{display:block;color:#172133;font-size:16px;font-weight:700;line-height:1.25;}
			#wbrbpw-health .wbrbpw-health-value[data-tone=\"good\"]{color:#0f7a3d;}
			#wbrbpw-health .wbrbpw-health-value[data-tone=\"warn\"]{color:#9a5d00;}
			#wbrbpw-health .wbrbpw-health-value[data-tone=\"neutral\"]{color:#172133;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table{background:var(--wbrbpw-card);border:1px solid var(--wbrbpw-border);border-radius:16px;overflow:hidden;box-shadow:var(--wbrbpw-shadow);}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table td, body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table th{padding:16px 18px;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table tr{border-top:1px solid #eef2f8;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table tr:first-child{border-top:none;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table th{width:280px;color:var(--wbrbpw-text);font-weight:600;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table td{color:var(--wbrbpw-muted);}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table .description{color:var(--wbrbpw-muted);font-size:12px;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .wbrbpw-help-dot{display:inline-flex;align-items:center;justify-content:center;width:17px;height:17px;border-radius:999px;border:none;background:#ccfbf1;color:#115e59;font-size:11px;font-weight:700;line-height:1;cursor:help;padding:0;margin-left:6px;vertical-align:middle;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"text\"],body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"password\"],body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"number\"],body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table select{min-height:38px;border-radius:10px;border:1px solid #cfd9e6;padding:0 12px;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"text\"]:focus,body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"password\"]:focus,body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"number\"]:focus,body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table select:focus{border-color:var(--wbrbpw-primary);box-shadow:0 0 0 3px rgba(15,118,110,.15);}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"checkbox\"]{appearance:none;-webkit-appearance:none;width:42px;height:24px;border-radius:999px;background:#c6d2e4;position:relative;border:none;box-shadow:inset 0 0 0 1px rgba(0,0,0,.04);margin:0 10px 0 0;vertical-align:middle;cursor:pointer;transition:background .2s ease;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"checkbox\"]:before{content:\"\";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:left .2s ease;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"checkbox\"]:checked{background:var(--wbrbpw-primary);}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table input[type=\"checkbox\"]:checked:before{left:21px;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen p.submit{position:sticky;bottom:0;background:rgba(246,248,251,.96);backdrop-filter:blur(6px);padding:14px 0 4px;margin:0;}
			body.woocommerce_page_wc-settings.wbrbpw-settings-screen p.submit .button-primary{min-height:40px;padding:0 16px;border-radius:10px;font-weight:600;background:var(--wbrbpw-primary);border-color:var(--wbrbpw-primary);}
			@media (max-width: 960px){
				#wbrbpw-health{grid-template-columns:1fr 1fr;}
				body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table th,body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table td{display:block;width:100%;padding:12px 14px;}
				body.woocommerce_page_wc-settings.wbrbpw-settings-screen .form-table th{padding-bottom:4px;}
			}'
		);

		wp_register_script( 'wbrbpw-admin-settings', '', array( 'jquery' ), WBRBPW_VERSION, true );
		wp_enqueue_script( 'wbrbpw-admin-settings' );
		wp_add_inline_script(
			'wbrbpw-admin-settings',
			'window.wbrbpwSettingsMeta = ' . wp_json_encode( self::settings_screen_meta() ) . ';',
			'before'
		);
		wp_add_inline_script(
			'wbrbpw-admin-settings',
			"(function($){
				'use strict';
				$(function(){
					$('body').addClass('wbrbpw-settings-screen');
					var hero = '<div id=\"wbrbpw-settings-hero\"><span class=\"wbrbpw-badge\">Premium Controls</span><h3>WB Role Based Pricing Settings</h3><p>Configure role, membership, subscription, and guest pricing with production-ready controls.</p></div>';
					var tips = {
						wbrbpw_base_price: 'Defines the baseline used before group adjustments are applied.',
						wbrbpw_sale_interaction: 'Choose whether pricing groups should respect or override WooCommerce sale prices.',
						wbrbpw_guest_pricing: 'Decide if guests use default Woo pricing or a dedicated pricing group.',
						wbrbpw_guest_group: 'Use a valid Pricing Group post ID when guest group mode is enabled.',
						wbrbpw_source_priority: 'Controls which eligibility source wins when more than one source matches.',
						wbrbpw_rounding: 'Apply rounding only if your catalog requires specific currency display behavior.'
					};
					var meta = window.wbrbpwSettingsMeta || null;
					function esc(value){ return $('<div/>').text(String(value || '')).html(); }
					function buildHealthCard(label, value, tone){
						return '<div class=\"wbrbpw-health-card\"><span class=\"wbrbpw-health-label\">' + esc(label) + '</span><span class=\"wbrbpw-health-value\" data-tone=\"' + esc(tone) + '\">' + esc(value) + '</span></div>';
					}
					if (!$('#wbrbpw-settings-hero').length) {
						$('#mainform').prepend(hero);
					}
					if (meta && !$('#wbrbpw-health').length) {
						var health = '<div id=\"wbrbpw-health\">';
						health += buildHealthCard('Plugin Status', meta.plugin_status, meta.plugin_status_tone);
						health += buildHealthCard('Active Groups', meta.active_groups, meta.active_groups_tone);
						health += buildHealthCard('Guest Pricing', meta.guest_pricing, meta.guest_pricing_tone);
						health += buildHealthCard('Source Priority', meta.source_priority, meta.source_priority_tone);
						health += '</div>';
						$('#wbrbpw-settings-hero').after(health);
					}
					$.each(tips, function(fieldId, helpText){
						var fieldEl = $('#' + fieldId);
						if (!fieldEl.length) {
							return;
						}
						var thEl = fieldEl.closest('tr').find('th');
						if (!thEl.length || thEl.find('.wbrbpw-help-dot').length) {
							return;
						}
						thEl.append('<button type=\"button\" class=\"wbrbpw-help-dot\" title=\"' + esc(helpText) + '\" aria-label=\"' + esc(helpText) + '\">?</button>');
					});
				});
			})(jQuery);"
		);
	}

	/**
	 * @param string[] $allowed
	 */
	private static function get_allowed_option( string $option_name, string $default, array $allowed ): string {
		$value = sanitize_key( (string) get_option( $option_name, $default ) );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * @return array<string,string>
	 */
	private static function settings_screen_meta(): array {
		$groups_count = count( \WBCOM\WBRBPW\Admin\Pricing_Groups::get_active_groups() );
		$guest_mode   = self::get_guest_pricing_mode();
		$priority     = implode( ' > ', self::get_source_priority_order() );
		$enabled      = self::is_enabled();

		return array(
			'plugin_status'       => $enabled ? __( 'Enabled', 'wb-role-based-pricing-for-woocommerce' ) : __( 'Disabled', 'wb-role-based-pricing-for-woocommerce' ),
			'plugin_status_tone'  => $enabled ? 'good' : 'warn',
			'active_groups'       => $groups_count > 0 ? sprintf(
				/* translators: %d: number of active groups. */
				_n( '%d group active', '%d groups active', $groups_count, 'wb-role-based-pricing-for-woocommerce' ),
				$groups_count
			) : __( 'No active groups', 'wb-role-based-pricing-for-woocommerce' ),
			'active_groups_tone'  => $groups_count > 0 ? 'good' : 'warn',
			'guest_pricing'       => 'group' === $guest_mode ? __( 'Group mode', 'wb-role-based-pricing-for-woocommerce' ) : __( 'Default Woo', 'wb-role-based-pricing-for-woocommerce' ),
			'guest_pricing_tone'  => 'group' === $guest_mode ? 'neutral' : 'good',
			'source_priority'     => $priority,
			'source_priority_tone'=> 'neutral',
		);
	}

	private static function is_wbrbpw_settings_screen(): bool {
		$page = sanitize_key( (string) filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$tab  = sanitize_key( (string) filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		return 'wc-settings' === $page && 'wb_role_based_pricing' === $tab;
	}

	private static function get_settings(): array {
		return array(
			array(
				'name' => __( 'WB Role Based Pricing', 'wb-role-based-pricing-for-woocommerce' ),
				'type' => 'title',
				'desc' => __( 'Configure role/group based dynamic pricing behavior.', 'wb-role-based-pricing-for-woocommerce' ),
				'id'   => 'wbrbpw_settings_section',
			),
			array(
				'name'    => __( 'Enable plugin', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_ENABLED,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'name'    => __( 'Base price for adjustments', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_BASE_PRICE,
				'type'    => 'select',
				'options' => array(
					'regular' => __( 'Regular price', 'wb-role-based-pricing-for-woocommerce' ),
					'sale'    => __( 'Sale price (if on sale)', 'wb-role-based-pricing-for-woocommerce' ),
					'lowest'  => __( 'Lowest of regular/sale', 'wb-role-based-pricing-for-woocommerce' ),
				),
				'default' => 'lowest',
			),
			array(
				'name'    => __( 'Sale price interaction', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_SALE_INTERACTION,
				'type'    => 'select',
				'options' => array(
					'respect_sale' => __( 'Respect Woo sale', 'wb-role-based-pricing-for-woocommerce' ),
					'override_sale' => __( 'Pricing Group overrides sale', 'wb-role-based-pricing-for-woocommerce' ),
				),
				'default' => 'respect_sale',
			),
			array(
				'name'    => __( 'Guest pricing', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_GUEST_PRICING,
				'type'    => 'select',
				'options' => array(
					'default' => __( 'Use default Woo pricing', 'wb-role-based-pricing-for-woocommerce' ),
					'group'   => __( 'Assign guests to a pricing group', 'wb-role-based-pricing-for-woocommerce' ),
				),
				'default' => 'default',
			),
			array(
				'name'              => __( 'Guest pricing group ID', 'wb-role-based-pricing-for-woocommerce' ),
				'id'                => self::OPTION_GUEST_GROUP,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'default'           => '0',
				'desc'              => __( 'Set a Pricing Group post ID to apply for guests when guest mode is enabled.', 'wb-role-based-pricing-for-woocommerce' ),
				'desc_tip'          => true,
			),
			array(
				'name'    => __( 'Multiple eligibility resolution', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_GROUP_RESOLUTION,
				'type'    => 'select',
				'options' => array(
					'priority' => __( 'Highest priority group wins', 'wb-role-based-pricing-for-woocommerce' ),
				),
				'default' => 'priority',
			),
			array(
				'name'    => __( 'Eligibility source priority', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_SOURCE_PRIORITY,
				'type'    => 'select',
				'options' => array(
					'subscription,membership,role' => __( 'Subscription > Membership > Role', 'wb-role-based-pricing-for-woocommerce' ),
					'membership,subscription,role' => __( 'Membership > Subscription > Role', 'wb-role-based-pricing-for-woocommerce' ),
					'role,membership,subscription' => __( 'Role > Membership > Subscription', 'wb-role-based-pricing-for-woocommerce' ),
					'role,subscription,membership' => __( 'Role > Subscription > Membership', 'wb-role-based-pricing-for-woocommerce' ),
				),
				'default' => 'subscription,membership,role',
				'desc'    => __( 'Controls which source wins when a user qualifies from multiple sources.', 'wb-role-based-pricing-for-woocommerce' ),
			),
			array(
				'name'    => __( 'Rounding', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_ROUNDING,
				'type'    => 'select',
				'options' => array(
					'none' => __( 'None', 'wb-role-based-pricing-for-woocommerce' ),
					'2'    => __( 'Round to 2 decimals', 'wb-role-based-pricing-for-woocommerce' ),
					'0'    => __( 'Round to nearest integer', 'wb-role-based-pricing-for-woocommerce' ),
				),
				'default' => 'none',
			),
			array(
				'name'    => __( 'Hide prices for guests', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_HIDE_GUEST_PRICE,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'name'    => __( 'Guest hidden price text', 'wb-role-based-pricing-for-woocommerce' ),
				'id'      => self::OPTION_GUEST_TEXT,
				'type'    => 'text',
				'default' => __( 'Login to see pricing', 'wb-role-based-pricing-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbrbpw_settings_section',
			),
		);
	}
}
