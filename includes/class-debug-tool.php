<?php

namespace WBCOM\WBRBPW;

defined( 'ABSPATH' ) || exit;

final class Debug_Tool {
	private static ?self $instance = null;

	private Eligibility_Resolver $eligibility;

	public static function init( Eligibility_Resolver $eligibility ): void {
		if ( null === self::$instance ) {
			self::$instance = new self( $eligibility );
		}

		add_action( 'wp_footer', array( self::$instance, 'render_debug_panel' ) );
	}

	private function __construct( Eligibility_Resolver $eligibility ) {
		$this->eligibility = $eligibility;
	}

	public function render_debug_panel(): void {
		$debug_flag = isset( $_GET['wbrbpw_debug'] ) ? sanitize_text_field( wp_unslash( $_GET['wbrbpw_debug'] ) ) : '';
		if ( '1' !== $debug_flag ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$data = $this->eligibility->get_current_user_debug_data();
		if ( empty( $data ) ) {
			return;
		}

		$ordered_groups = isset( $data['ordered_group_ids'] ) && is_array( $data['ordered_group_ids'] ) ? $data['ordered_group_ids'] : array();
		$selected_group = isset( $data['selected_group_id'] ) ? absint( $data['selected_group_id'] ) : 0;
		$selected_source = isset( $data['selected_group_source'] ) ? sanitize_key( (string) $data['selected_group_source'] ) : '';
		$source_priority = isset( $data['source_priority'] ) && is_array( $data['source_priority'] ) ? $data['source_priority'] : array();
		$eligible_groups = isset( $data['eligible_groups'] ) && is_array( $data['eligible_groups'] ) ? $data['eligible_groups'] : array();

		?>
		<div style="position:fixed;right:12px;bottom:12px;z-index:99999;background:#111;color:#fff;padding:12px 14px;border-radius:6px;max-width:360px;font-size:12px;line-height:1.45;box-shadow:0 5px 20px rgba(0,0,0,.35);">
			<div style="font-weight:700;margin-bottom:8px;"><?php esc_html_e( 'WB Pricing Debug', 'wb-role-based-pricing' ); ?></div>
			<div><strong><?php esc_html_e( 'User ID:', 'wb-role-based-pricing' ); ?></strong> <?php echo esc_html( (string) ( isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0 ) ); ?></div>
			<div><strong><?php esc_html_e( 'Roles:', 'wb-role-based-pricing' ); ?></strong> <?php echo esc_html( implode( ', ', array_map( 'sanitize_key', (array) ( $data['roles'] ?? array() ) ) ) ); ?></div>
			<div><strong><?php esc_html_e( 'Source Priority:', 'wb-role-based-pricing' ); ?></strong> <?php echo esc_html( implode( ' > ', array_map( 'sanitize_key', $source_priority ) ) ); ?></div>
			<div><strong><?php esc_html_e( 'Selected Group:', 'wb-role-based-pricing' ); ?></strong> <?php echo esc_html( (string) $selected_group ); ?></div>
			<div><strong><?php esc_html_e( 'Selected Source:', 'wb-role-based-pricing' ); ?></strong> <?php echo esc_html( $selected_source ); ?></div>
			<div><strong><?php esc_html_e( 'Ordered Groups:', 'wb-role-based-pricing' ); ?></strong> <?php echo esc_html( implode( ', ', array_map( 'absint', $ordered_groups ) ) ); ?></div>
			<div style="margin-top:8px;">
				<strong><?php esc_html_e( 'Eligible by Source', 'wb-role-based-pricing' ); ?></strong>
				<div><?php esc_html_e( 'Subscription:', 'wb-role-based-pricing' ); ?> <?php echo esc_html( implode( ', ', array_map( 'absint', (array) ( $eligible_groups['subscription'] ?? array() ) ) ) ); ?></div>
				<div><?php esc_html_e( 'Membership:', 'wb-role-based-pricing' ); ?> <?php echo esc_html( implode( ', ', array_map( 'absint', (array) ( $eligible_groups['membership'] ?? array() ) ) ) ); ?></div>
				<div><?php esc_html_e( 'Role:', 'wb-role-based-pricing' ); ?> <?php echo esc_html( implode( ', ', array_map( 'absint', (array) ( $eligible_groups['role'] ?? array() ) ) ) ); ?></div>
			</div>
		</div>
		<?php
	}
}
