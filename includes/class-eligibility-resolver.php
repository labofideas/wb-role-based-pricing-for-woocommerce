<?php

namespace WBCOM\WBRBPW;

use WBCOM\WBRBPW\Admin\Pricing_Groups;

defined( 'ABSPATH' ) || exit;

final class Eligibility_Resolver {
	/**
	 * @var array<string,mixed>
	 */
	private array $cache = array();

	/**
	 * @var array<string,array<string,mixed>>
	 */
	private array $debug_cache = array();

	/**
	 * @return int[]
	 */
	public function get_current_user_group_ids(): array {
		$cache_key = is_user_logged_in() ? 'user_' . get_current_user_id() : 'guest';

		if ( isset( $this->cache[ $cache_key ] ) && is_array( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		if ( ! is_user_logged_in() ) {
			$group_ids = array();
			if ( 'group' === Settings::get_guest_pricing_mode() ) {
				$guest_group = Settings::get_guest_group_id();
				if ( $guest_group > 0 ) {
					$group_ids[] = $guest_group;
				}
			}

			$group_ids = apply_filters( 'wbrbpw_guest_group_ids', $group_ids );
			$group_ids = array_values( array_unique( array_map( 'absint', $group_ids ) ) );
			$this->debug_cache[ $cache_key ] = array(
				'is_guest'             => true,
				'user_id'              => 0,
				'roles'                => array(),
				'source_priority'      => Settings::get_source_priority_order(),
				'eligible_groups'      => array(
					'subscription' => array(),
					'membership'   => array(),
					'role'         => array(),
					'guest'        => $group_ids,
				),
				'ordered_group_ids'    => $group_ids,
				'selected_group_id'    => ! empty( $group_ids ) ? (int) $group_ids[0] : 0,
				'selected_group_source'=> ! empty( $group_ids ) ? 'guest' : '',
			);
			$this->cache[ $cache_key ] = $group_ids;
			return $this->cache[ $cache_key ];
		}

		$current_user = wp_get_current_user();
		$user_roles   = is_array( $current_user->roles ) ? $current_user->roles : array();
		$user_id      = (int) $current_user->ID;

		$role_groups = $this->get_role_group_ids( $user_roles );
		$membership_groups = $this->get_membership_group_ids( $user_id );
		$subscription_groups = $this->get_subscription_group_ids( $user_id );

		$source_groups = array(
			'role'         => $role_groups,
			'membership'   => $membership_groups,
			'subscription' => $subscription_groups,
		);

		$source_groups = apply_filters( 'wbrbpw_user_group_ids_by_source', $source_groups, $current_user );
		if ( ! is_array( $source_groups ) ) {
			$source_groups = array(
				'role'         => $role_groups,
				'membership'   => $membership_groups,
				'subscription' => $subscription_groups,
			);
		}

		$legacy_seed = (array) ( $source_groups['role'] ?? array() );
		$legacy_groups = apply_filters( 'wbrbpw_user_group_ids', $legacy_seed, $current_user );
		$legacy_groups = array_values( array_unique( array_map( 'absint', (array) $legacy_groups ) ) );
		$source_groups = $this->inject_legacy_groups_into_role_source( $source_groups, $legacy_groups );

		$source_order = Settings::get_source_priority_order();
		$group_ids = array();
		$selected_group_source = '';
		foreach ( $source_order as $source ) {
			$source_ids = $this->sort_group_ids_by_priority( (array) ( $source_groups[ $source ] ?? array() ) );
			foreach ( $source_ids as $group_id ) {
				if ( ! in_array( $group_id, $group_ids, true ) ) {
					$group_ids[] = $group_id;
					if ( '' === $selected_group_source ) {
						$selected_group_source = $source;
					}
				}
			}
		}

		$this->debug_cache[ $cache_key ] = array(
			'is_guest'              => false,
			'user_id'               => $user_id,
			'roles'                 => $user_roles,
			'source_priority'       => $source_order,
			'eligible_groups'       => array(
				'subscription' => $this->sort_group_ids_by_priority( (array) ( $source_groups['subscription'] ?? array() ) ),
				'membership'   => $this->sort_group_ids_by_priority( (array) ( $source_groups['membership'] ?? array() ) ),
				'role'         => $this->sort_group_ids_by_priority( (array) ( $source_groups['role'] ?? array() ) ),
			),
			'ordered_group_ids'     => $group_ids,
			'selected_group_id'     => ! empty( $group_ids ) ? (int) $group_ids[0] : 0,
			'selected_group_source' => $selected_group_source,
		);
		$this->cache[ $cache_key ] = $group_ids;
		return $group_ids;
	}

	public function get_primary_group_id(): int {
		$groups = $this->get_current_user_group_ids();
		return ! empty( $groups ) ? (int) $groups[0] : 0;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_current_user_debug_data(): array {
		$cache_key = is_user_logged_in() ? 'user_' . get_current_user_id() : 'guest';
		if ( ! isset( $this->debug_cache[ $cache_key ] ) ) {
			$this->get_current_user_group_ids();
		}

		return isset( $this->debug_cache[ $cache_key ] ) && is_array( $this->debug_cache[ $cache_key ] )
			? $this->debug_cache[ $cache_key ]
			: array();
	}

	public function get_primary_group_source(): string {
		$debug = $this->get_current_user_debug_data();
		return isset( $debug['selected_group_source'] ) ? (string) $debug['selected_group_source'] : '';
	}

	/**
	 * @param string[] $user_roles
	 * @return int[]
	 */
	private function get_role_group_ids( array $user_roles ): array {
		$group_ids = array();
		foreach ( Pricing_Groups::get_active_groups() as $group ) {
			$roles = isset( $group['roles'] ) && is_array( $group['roles'] ) ? $group['roles'] : array();
			if ( array_intersect( $roles, $user_roles ) ) {
				$group_ids[] = (int) $group['id'];
			}
		}

		return $this->sort_group_ids_by_priority( $group_ids );
	}

	/**
	 * @return int[]
	 */
	private function get_membership_group_ids( int $user_id ): array {
		if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return array();
		}

		$levels = pmpro_getMembershipLevelsForUser( $user_id );
		if ( empty( $levels ) || ! is_array( $levels ) ) {
			return array();
		}

		$user_level_ids = array();
		foreach ( $levels as $level ) {
			if ( is_object( $level ) && isset( $level->id ) ) {
				$user_level_ids[] = absint( $level->id );
			}
		}

		if ( empty( $user_level_ids ) ) {
			return array();
		}

		$group_ids = array();
		foreach ( Pricing_Groups::get_active_groups() as $group ) {
			$mapped_levels = isset( $group['membership_levels'] ) && is_array( $group['membership_levels'] )
				? array_map( 'absint', $group['membership_levels'] )
				: array();
			if ( array_intersect( $mapped_levels, $user_level_ids ) ) {
				$group_ids[] = (int) $group['id'];
			}
		}

		return $this->sort_group_ids_by_priority( $group_ids );
	}

	/**
	 * @return int[]
	 */
	private function get_subscription_group_ids( int $user_id ): array {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return array();
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			return array();
		}

		$group_ids = array();
		foreach ( Pricing_Groups::get_active_groups() as $group ) {
			$map = isset( $group['subscriptions'] ) && is_array( $group['subscriptions'] ) ? $group['subscriptions'] : array();
			$has_product_ids_key = array_key_exists( 'product_ids', $map );
			$has_statuses_key    = array_key_exists( 'statuses', $map );
			if ( ! $has_product_ids_key && ! $has_statuses_key ) {
				continue;
			}

			$product_ids = isset( $map['product_ids'] ) && is_array( $map['product_ids'] ) ? array_values( array_filter( array_map( 'absint', $map['product_ids'] ) ) ) : array();
			$statuses = isset( $map['statuses'] ) && is_array( $map['statuses'] ) ? array_values( array_filter( array_map( 'sanitize_key', $map['statuses'] ) ) ) : array();
			if ( empty( $statuses ) ) {
				$statuses = array( 'active' );
			}

			$has_match = false;
			foreach ( $subscriptions as $subscription ) {
				if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_status' ) ) {
					continue;
				}

				$status = sanitize_key( (string) $subscription->get_status() );
				$status = str_replace( 'wc-', '', $status );
				if ( ! in_array( $status, $statuses, true ) ) {
					continue;
				}

				if ( empty( $product_ids ) ) {
					$has_match = true;
					break;
				}

				if ( ! method_exists( $subscription, 'get_items' ) ) {
					continue;
				}

				$items = $subscription->get_items();
				foreach ( $items as $item ) {
					if ( ! is_object( $item ) ) {
						continue;
					}

					$product_id = method_exists( $item, 'get_product_id' ) ? absint( $item->get_product_id() ) : 0;
					$variation_id = method_exists( $item, 'get_variation_id' ) ? absint( $item->get_variation_id() ) : 0;

					if ( in_array( $product_id, $product_ids, true ) || in_array( $variation_id, $product_ids, true ) ) {
						$has_match = true;
						break 2;
					}
				}
			}

			if ( $has_match ) {
				$group_ids[] = (int) $group['id'];
			}
		}

		return $this->sort_group_ids_by_priority( $group_ids );
	}

	/**
	 * @param int[] $group_ids
	 * @return int[]
	 */
	private function sort_group_ids_by_priority( array $group_ids ): array {
		$group_ids = array_values( array_unique( array_map( 'absint', $group_ids ) ) );
		usort(
			$group_ids,
			static function ( int $a, int $b ): int {
				$ga = Pricing_Groups::get_group( $a );
				$gb = Pricing_Groups::get_group( $b );
				$pa = isset( $ga['priority'] ) ? (int) $ga['priority'] : PHP_INT_MAX;
				$pb = isset( $gb['priority'] ) ? (int) $gb['priority'] : PHP_INT_MAX;
				return $pa <=> $pb;
			}
		);

		return $group_ids;
	}

	/**
	 * @param array<string,mixed> $source_groups
	 * @param int[]               $legacy_groups
	 * @return array<string,mixed>
	 */
	private function inject_legacy_groups_into_role_source( array $source_groups, array $legacy_groups ): array {
		$current_role_groups = isset( $source_groups['role'] ) && is_array( $source_groups['role'] ) ? $source_groups['role'] : array();
		$source_groups['role'] = array_values(
			array_unique(
				array_map(
					'absint',
					array_merge( $current_role_groups, $legacy_groups )
				)
			)
		);

		return $source_groups;
	}
}
