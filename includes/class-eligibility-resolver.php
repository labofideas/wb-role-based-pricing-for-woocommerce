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
	 * @return int[]
	 */
	public function get_current_user_group_ids(): array {
		$cache_key = is_user_logged_in() ? 'user_' . get_current_user_id() : 'guest';

		if ( isset( $this->cache[ $cache_key ] ) && is_array( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$group_ids = array();

		if ( ! is_user_logged_in() ) {
			if ( 'group' === Settings::get_guest_pricing_mode() ) {
				$guest_group = Settings::get_guest_group_id();
				if ( $guest_group > 0 ) {
					$group_ids[] = $guest_group;
				}
			}

			$group_ids = apply_filters( 'wbrbpw_guest_group_ids', $group_ids );
			$this->cache[ $cache_key ] = array_values( array_unique( array_map( 'absint', $group_ids ) ) );
			return $this->cache[ $cache_key ];
		}

		$current_user = wp_get_current_user();
		$user_roles   = is_array( $current_user->roles ) ? $current_user->roles : array();

		foreach ( Pricing_Groups::get_active_groups() as $group ) {
			$roles = isset( $group['roles'] ) && is_array( $group['roles'] ) ? $group['roles'] : array();
			if ( array_intersect( $roles, $user_roles ) ) {
				$group_ids[] = (int) $group['id'];
			}
		}

		$group_ids = apply_filters( 'wbrbpw_user_group_ids', $group_ids, $current_user );
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

		$this->cache[ $cache_key ] = $group_ids;
		return $group_ids;
	}

	public function get_primary_group_id(): int {
		$groups = $this->get_current_user_group_ids();
		return ! empty( $groups ) ? (int) $groups[0] : 0;
	}
}
