<?php

namespace WBCOM\WBRBPW\Admin;

defined( 'ABSPATH' ) || exit;

final class Pricing_Groups {
	private const META_PRIORITY = '_wb_group_priority';
	private const META_ENABLED = '_wb_group_enabled';
	private const META_DEFAULT_RULE = '_wb_group_default_rule';
	private const META_ROLE_MAP = '_wb_group_role_map';
	private const META_MEMBERSHIP_MAP = '_wb_group_membership_map';
	private const META_SUBSCRIPTION_MAP = '_wb_group_subscription_map';

	/**
	 * @var array<int, array<string,mixed>>|null
	 */
	private static ?array $groups_cache = null;

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_wb_pricing_group', array( __CLASS__, 'save_group_meta' ) );
		add_filter( 'manage_wb_pricing_group_posts_columns', array( __CLASS__, 'register_columns' ) );
		add_action( 'manage_wb_pricing_group_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	public static function register_post_type(): void {
		register_post_type(
			'wb_pricing_group',
			array(
				'labels' => array(
					'name'          => __( 'Pricing Groups', 'wb-role-based-pricing' ),
					'singular_name' => __( 'Pricing Group', 'wb-role-based-pricing' ),
					'add_new_item'  => __( 'Add New Pricing Group', 'wb-role-based-pricing' ),
					'edit_item'     => __( 'Edit Pricing Group', 'wb-role-based-pricing' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'woocommerce',
				'menu_position'   => 56,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
			),
		);
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'wb-pricing-group-rules',
			__( 'Pricing Group Rules', 'wb-role-based-pricing' ),
			array( __CLASS__, 'render_rules_metabox' ),
			'wb_pricing_group',
			'normal',
			'default'
		);
	}

	public static function render_rules_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'wbrbpw_save_group', 'wbrbpw_group_nonce' );

		$priority = (int) get_post_meta( $post->ID, self::META_PRIORITY, true );
		$enabled  = get_post_meta( $post->ID, self::META_ENABLED, true );
		$rule     = get_post_meta( $post->ID, self::META_DEFAULT_RULE, true );
		$roles    = get_post_meta( $post->ID, self::META_ROLE_MAP, true );
		$memberships = get_post_meta( $post->ID, self::META_MEMBERSHIP_MAP, true );
		$subscriptions = get_post_meta( $post->ID, self::META_SUBSCRIPTION_MAP, true );

		$rule = is_array( $rule ) ? $rule : array();
		$roles = is_array( $roles ) ? $roles : array();
		$memberships = is_array( $memberships ) ? array_map( 'absint', $memberships ) : array();
		$subscriptions = is_array( $subscriptions ) ? $subscriptions : array();
		$sub_product_ids = isset( $subscriptions['product_ids'] ) && is_array( $subscriptions['product_ids'] ) ? array_map( 'absint', $subscriptions['product_ids'] ) : array();
		$sub_statuses = isset( $subscriptions['statuses'] ) && is_array( $subscriptions['statuses'] ) ? array_map( 'sanitize_key', $subscriptions['statuses'] ) : array( 'active' );
		$rule_type = isset( $rule['type'] ) ? (string) $rule['type'] : 'none';

		global $wp_roles;
		$role_options = is_object( $wp_roles ) ? $wp_roles->roles : array();
		?>
		<p>
			<label for="wbrbpw_group_priority"><strong><?php esc_html_e( 'Priority', 'wb-role-based-pricing' ); ?></strong></label><br/>
			<input type="number" min="0" step="1" id="wbrbpw_group_priority" name="wbrbpw_group_priority" value="<?php echo esc_attr( $priority ); ?>" />
			<span class="description"><?php esc_html_e( 'Lower number = higher priority.', 'wb-role-based-pricing' ); ?></span>
		</p>
		<p>
			<label>
				<input type="checkbox" name="wbrbpw_group_enabled" value="1" <?php checked( '1', (string) $enabled ); ?> />
				<?php esc_html_e( 'Enable this pricing group', 'wb-role-based-pricing' ); ?>
			</label>
		</p>
		<hr/>
		<h4><?php esc_html_e( 'Default Rule', 'wb-role-based-pricing' ); ?></h4>
		<p>
			<select name="wbrbpw_group_rule[type]">
				<option value="none" <?php selected( $rule_type, 'none' ); ?>><?php esc_html_e( 'None', 'wb-role-based-pricing' ); ?></option>
				<option value="fixed_price" <?php selected( $rule_type, 'fixed_price' ); ?>><?php esc_html_e( 'Fixed Price Override', 'wb-role-based-pricing' ); ?></option>
				<option value="percent" <?php selected( $rule_type, 'percent' ); ?>><?php esc_html_e( 'Percentage Adjustment', 'wb-role-based-pricing' ); ?></option>
				<option value="amount" <?php selected( $rule_type, 'amount' ); ?>><?php esc_html_e( 'Fixed Amount Adjustment', 'wb-role-based-pricing' ); ?></option>
			</select>
		</p>
		<p>
			<label><?php esc_html_e( 'Fixed price', 'wb-role-based-pricing' ); ?></label><br/>
			<input type="number" step="0.01" name="wbrbpw_group_rule[fixed_price]" value="<?php echo esc_attr( $rule['fixed_price'] ?? '' ); ?>" />
		</p>
		<p>
			<label><?php esc_html_e( 'Percent (+/-)', 'wb-role-based-pricing' ); ?></label><br/>
			<input type="number" step="0.01" name="wbrbpw_group_rule[percent]" value="<?php echo esc_attr( $rule['percent'] ?? '' ); ?>" />
		</p>
		<p>
			<label><?php esc_html_e( 'Amount (+/-)', 'wb-role-based-pricing' ); ?></label><br/>
			<input type="number" step="0.01" name="wbrbpw_group_rule[amount]" value="<?php echo esc_attr( $rule['amount'] ?? '' ); ?>" />
		</p>
		<hr/>
		<h4><?php esc_html_e( 'Role Mapping', 'wb-role-based-pricing' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Users with selected roles qualify for this group.', 'wb-role-based-pricing' ); ?></p>
		<?php foreach ( $role_options as $role_key => $role_data ) : ?>
			<label style="display:block;margin-bottom:4px;">
				<input type="checkbox" name="wbrbpw_group_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $roles, true ) ); ?> />
				<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
			</label>
		<?php endforeach; ?>
		<hr/>
		<h4><?php esc_html_e( 'Membership Mapping (Paid Memberships Pro)', 'wb-role-based-pricing' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Enter PMPro membership level IDs (comma-separated). Example: 1,2,3', 'wb-role-based-pricing' ); ?>
		</p>
		<p>
			<input
				type="text"
				class="regular-text"
				name="wbrbpw_group_membership_levels"
				value="<?php echo esc_attr( implode( ',', $memberships ) ); ?>"
				placeholder="<?php esc_attr_e( '1,2,3', 'wb-role-based-pricing' ); ?>"
			/>
		</p>
		<?php if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) : ?>
			<p class="description"><?php esc_html_e( 'Paid Memberships Pro is not active. Mapping can still be saved and will apply when PMPro is active.', 'wb-role-based-pricing' ); ?></p>
		<?php endif; ?>
		<hr/>
		<h4><?php esc_html_e( 'Subscription Mapping (WooCommerce Subscriptions)', 'wb-role-based-pricing' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Map subscription products and statuses to this group.', 'wb-role-based-pricing' ); ?>
		</p>
		<p>
			<label><?php esc_html_e( 'Subscription product IDs (comma-separated, blank = any)', 'wb-role-based-pricing' ); ?></label><br/>
			<input
				type="text"
				class="regular-text"
				name="wbrbpw_group_subscription_products"
				value="<?php echo esc_attr( implode( ',', $sub_product_ids ) ); ?>"
				placeholder="<?php esc_attr_e( '101,102', 'wb-role-based-pricing' ); ?>"
			/>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Eligible subscription statuses', 'wb-role-based-pricing' ); ?></strong></label><br/>
			<?php
			$available_statuses = array(
				'active'         => __( 'Active', 'wb-role-based-pricing' ),
				'on-hold'        => __( 'On hold', 'wb-role-based-pricing' ),
				'pending-cancel' => __( 'Pending cancel', 'wb-role-based-pricing' ),
			);
			foreach ( $available_statuses as $status_key => $status_label ) :
				?>
				<label style="display:block;margin-bottom:4px;">
					<input type="checkbox" name="wbrbpw_group_subscription_statuses[]" value="<?php echo esc_attr( $status_key ); ?>" <?php checked( in_array( $status_key, $sub_statuses, true ) ); ?> />
					<?php echo esc_html( $status_label ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<?php if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) : ?>
			<p class="description"><?php esc_html_e( 'WooCommerce Subscriptions is not active. Mapping can still be saved and will apply when Subscriptions is active.', 'wb-role-based-pricing' ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function save_group_meta( int $post_id ): void {
		if ( ! isset( $_POST['wbrbpw_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbrbpw_group_nonce'] ) ), 'wbrbpw_save_group' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$priority = isset( $_POST['wbrbpw_group_priority'] ) ? absint( wp_unslash( $_POST['wbrbpw_group_priority'] ) ) : 0;
		$enabled  = isset( $_POST['wbrbpw_group_enabled'] ) ? '1' : '0';
		$roles    = isset( $_POST['wbrbpw_group_roles'] ) && is_array( $_POST['wbrbpw_group_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['wbrbpw_group_roles'] ) ) : array();
		$membership_levels_raw = isset( $_POST['wbrbpw_group_membership_levels'] ) ? sanitize_text_field( wp_unslash( $_POST['wbrbpw_group_membership_levels'] ) ) : '';
		$membership_levels = array_values(
			array_filter(
				array_map(
					'absint',
					array_map( 'trim', explode( ',', $membership_levels_raw ) )
				)
			)
		);
		$subscription_products_raw = isset( $_POST['wbrbpw_group_subscription_products'] ) ? sanitize_text_field( wp_unslash( $_POST['wbrbpw_group_subscription_products'] ) ) : '';
		$subscription_products = array_values(
			array_filter(
				array_map(
					'absint',
					array_map( 'trim', explode( ',', $subscription_products_raw ) )
				)
			)
		);
		$subscription_statuses = isset( $_POST['wbrbpw_group_subscription_statuses'] ) && is_array( $_POST['wbrbpw_group_subscription_statuses'] )
			? array_values( array_unique( array_map( 'sanitize_key', wp_unslash( $_POST['wbrbpw_group_subscription_statuses'] ) ) ) )
			: array( 'active' );
		$subscription_allowed_statuses = array( 'active', 'on-hold', 'pending-cancel' );
		$subscription_statuses = array_values( array_intersect( $subscription_statuses, $subscription_allowed_statuses ) );
		if ( empty( $subscription_statuses ) ) {
			$subscription_statuses = array( 'active' );
		}

		$rule_in = isset( $_POST['wbrbpw_group_rule'] ) && is_array( $_POST['wbrbpw_group_rule'] ) ? wp_unslash( $_POST['wbrbpw_group_rule'] ) : array();
		$rule = array(
			'type'        => isset( $rule_in['type'] ) ? sanitize_key( $rule_in['type'] ) : 'none',
			'fixed_price' => isset( $rule_in['fixed_price'] ) ? wc_format_decimal( $rule_in['fixed_price'] ) : '',
			'percent'     => isset( $rule_in['percent'] ) ? wc_format_decimal( $rule_in['percent'] ) : '',
			'amount'      => isset( $rule_in['amount'] ) ? wc_format_decimal( $rule_in['amount'] ) : '',
		);

		update_post_meta( $post_id, self::META_PRIORITY, $priority );
		update_post_meta( $post_id, self::META_ENABLED, $enabled );
		update_post_meta( $post_id, self::META_ROLE_MAP, $roles );
		update_post_meta( $post_id, self::META_MEMBERSHIP_MAP, $membership_levels );
		update_post_meta(
			$post_id,
			self::META_SUBSCRIPTION_MAP,
			array(
				'product_ids' => $subscription_products,
				'statuses'    => $subscription_statuses,
			)
		);
		update_post_meta( $post_id, self::META_DEFAULT_RULE, $rule );

		self::$groups_cache = null;
	}

	public static function register_columns( array $columns ): array {
		$columns['wb_priority'] = __( 'Priority', 'wb-role-based-pricing' );
		$columns['wb_enabled']  = __( 'Enabled', 'wb-role-based-pricing' );
		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		if ( 'wb_priority' === $column ) {
			echo esc_html( (string) (int) get_post_meta( $post_id, self::META_PRIORITY, true ) );
		}

		if ( 'wb_enabled' === $column ) {
			echo '1' === (string) get_post_meta( $post_id, self::META_ENABLED, true ) ? esc_html__( 'Yes', 'wb-role-based-pricing' ) : esc_html__( 'No', 'wb-role-based-pricing' );
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_active_groups(): array {
		if ( null !== self::$groups_cache ) {
			return self::$groups_cache;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wb_pricing_group',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value_num',
				'meta_key'       => self::META_PRIORITY,
				'order'          => 'ASC',
			)
		);

		$groups = array();
		foreach ( $posts as $post ) {
			$enabled = (string) get_post_meta( $post->ID, self::META_ENABLED, true );
			if ( '1' !== $enabled ) {
				continue;
			}

			$groups[] = array(
				'id'           => (int) $post->ID,
				'name'         => $post->post_title,
				'priority'     => (int) get_post_meta( $post->ID, self::META_PRIORITY, true ),
				'roles'        => (array) get_post_meta( $post->ID, self::META_ROLE_MAP, true ),
				'membership_levels' => (array) get_post_meta( $post->ID, self::META_MEMBERSHIP_MAP, true ),
				'subscriptions' => (array) get_post_meta( $post->ID, self::META_SUBSCRIPTION_MAP, true ),
				'default_rule' => (array) get_post_meta( $post->ID, self::META_DEFAULT_RULE, true ),
			);
		}

		self::$groups_cache = $groups;
		return self::$groups_cache;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_group( int $group_id ): ?array {
		foreach ( self::get_active_groups() as $group ) {
			if ( (int) $group['id'] === $group_id ) {
				return $group;
			}
		}

		return null;
	}
}
