<?php

namespace WBCOM\WBRBPW;

use WBCOM\WBRBPW\Admin\Pricing_Groups;

defined( 'ABSPATH' ) || exit;

final class Price_Calculator {
	private Eligibility_Resolver $eligibility;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	private array $cache = array();

	public function __construct( Eligibility_Resolver $eligibility ) {
		$this->eligibility = $eligibility;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function resolve_price( \WC_Product $product, int $group_id ): ?array {
		$key = $product->get_id() . ':' . $group_id;
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$base_price = $this->get_base_price( $product );
		if ( null === $base_price ) {
			$this->cache[ $key ] = null;
			return null;
		}

		$rule_data = $this->get_rule_for_product( $product, $group_id );
		if ( null === $rule_data ) {
			$this->cache[ $key ] = null;
			return null;
		}

		$final_price = $this->apply_rule( $base_price, $rule_data['rule'] );
		if ( null === $final_price ) {
			$this->cache[ $key ] = null;
			return null;
		}

		$final_price = max( 0, $this->round_price( $final_price ) );

		$this->cache[ $key ] = array(
			'group_id'      => $group_id,
			'source'        => $rule_data['source'],
			'base_price'    => $base_price,
			'final_price'   => $final_price,
			'adjustment'    => $rule_data['rule'],
			'product_id'    => $product->get_id(),
			'variation_id'  => $product->is_type( 'variation' ) ? $product->get_id() : 0,
		);

		return $this->cache[ $key ];
	}

	private function get_base_price( \WC_Product $product ): ?float {
		$regular = (float) $product->get_regular_price( 'edit' );
		$sale_raw = $product->get_sale_price( 'edit' );
		$sale = '' !== (string) $sale_raw ? (float) $sale_raw : null;
		$current = (float) $product->get_price( 'edit' );
		$source = Settings::get_base_price_source();
		$sale_interaction = Settings::get_sale_interaction();

		if ( 'regular' === $source ) {
			return $regular > 0 ? $regular : ( $current > 0 ? $current : null );
		}

		if ( 'sale' === $source ) {
			if ( 'override_sale' === $sale_interaction ) {
				return $regular > 0 ? $regular : ( $current > 0 ? $current : null );
			}

			if ( null !== $sale && $sale > 0 ) {
				return $sale;
			}
			return $current > 0 ? $current : null;
		}

		if ( 'override_sale' === $sale_interaction ) {
			return $regular > 0 ? $regular : ( $current > 0 ? $current : null );
		}

		if ( null !== $sale && $sale > 0 && $regular > 0 ) {
			return min( $regular, $sale );
		}

		if ( null !== $sale && $sale > 0 ) {
			return $sale;
		}

		if ( $regular > 0 ) {
			return $regular;
		}

		return $current > 0 ? $current : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function get_rule_for_product( \WC_Product $product, int $group_id ): ?array {
		$category_override_rule = $this->extract_category_rule( $product, $group_id, true );
		if ( null !== $category_override_rule ) {
			return array(
				'source' => 'category',
				'rule'   => $category_override_rule,
			);
		}

		if ( $product->is_type( 'variation' ) ) {
			$variation_rule = $this->extract_product_rule( $product, $group_id );
			if ( null !== $variation_rule ) {
				return array(
					'source' => 'variation',
					'rule'   => $variation_rule,
				);
			}

			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent instanceof \WC_Product ) {
				$product_rule = $this->extract_product_rule( $parent, $group_id );
				if ( null !== $product_rule ) {
					return array(
						'source' => 'product',
						'rule'   => $product_rule,
					);
				}
			}
		} else {
			$product_rule = $this->extract_product_rule( $product, $group_id );
			if ( null !== $product_rule ) {
				return array(
					'source' => 'product',
					'rule'   => $product_rule,
				);
			}
		}

		$category_rule = $this->extract_category_rule( $product, $group_id, false );
		if ( null !== $category_rule ) {
			return array(
				'source' => 'category',
				'rule'   => $category_rule,
			);
		}

		$group = Pricing_Groups::get_group( $group_id );
		if ( is_array( $group ) && ! empty( $group['default_rule'] ) ) {
			$rule = $this->normalize_rule( (array) $group['default_rule'] );
			if ( null !== $rule ) {
				return array(
					'source' => 'global',
					'rule'   => $rule,
				);
			}
		}

		return null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function extract_product_rule( \WC_Product $product, int $group_id ): ?array {
		$rules = $product->get_meta( '_wb_pricing_group_prices', true, 'edit' );
		if ( ! is_array( $rules ) || empty( $rules[ $group_id ] ) ) {
			return null;
		}

		$rule = is_array( $rules[ $group_id ] ) ? $rules[ $group_id ] : array();
		if ( isset( $rule['enabled'] ) && '1' !== (string) $rule['enabled'] ) {
			return null;
		}

		return $this->normalize_rule( $rule );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function extract_category_rule( \WC_Product $product, int $group_id, bool $override_only ): ?array {
		$term_ids = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
		if ( empty( $term_ids ) ) {
			return null;
		}

		foreach ( $term_ids as $term_id ) {
			$rules = get_term_meta( $term_id, '_wb_pricing_group_category_rules', true );
			if ( ! is_array( $rules ) || empty( $rules[ $group_id ] ) ) {
				continue;
			}

			$rule = is_array( $rules[ $group_id ] ) ? $rules[ $group_id ] : array();
			if ( isset( $rule['enabled'] ) && '1' !== (string) $rule['enabled'] ) {
				continue;
			}

			$behavior = isset( $rule['behavior'] ) ? sanitize_key( (string) $rule['behavior'] ) : 'if_no_product_rule';
			if ( $override_only && 'override_product_rules' !== $behavior ) {
				continue;
			}

			if ( ! $override_only && 'override_product_rules' === $behavior ) {
				continue;
			}

			return $this->normalize_rule( $rule );
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $rule
	 * @return array<string,mixed>|null
	 */
	private function normalize_rule( array $rule ): ?array {
		$type = isset( $rule['type'] ) ? sanitize_key( (string) $rule['type'] ) : 'none';
		$normalized = array(
			'type'          => $type,
			'fixed_price'   => isset( $rule['fixed_price'] ) ? (float) $rule['fixed_price'] : 0.0,
			'fixed_regular' => isset( $rule['fixed_regular'] ) ? (float) $rule['fixed_regular'] : 0.0,
			'fixed_sale'    => isset( $rule['fixed_sale'] ) ? (float) $rule['fixed_sale'] : 0.0,
			'percent'       => isset( $rule['percent'] ) ? (float) $rule['percent'] : 0.0,
			'amount'        => isset( $rule['amount'] ) ? (float) $rule['amount'] : 0.0,
		);

		if ( 'fixed_price' === $type && ( $normalized['fixed_regular'] > 0 || $normalized['fixed_sale'] > 0 || $normalized['fixed_price'] > 0 ) ) {
			return $normalized;
		}

		if ( 'percent' === $type && 0.0 !== $normalized['percent'] ) {
			return $normalized;
		}

		if ( 'amount' === $type && 0.0 !== $normalized['amount'] ) {
			return $normalized;
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $rule
	 */
	private function apply_rule( float $base_price, array $rule ): ?float {
		$type = (string) ( $rule['type'] ?? 'none' );

		if ( 'fixed_price' === $type ) {
			$sale_interaction = Settings::get_sale_interaction();
			$fixed_sale       = isset( $rule['fixed_sale'] ) ? (float) $rule['fixed_sale'] : 0.0;
			$fixed_regular    = isset( $rule['fixed_regular'] ) ? (float) $rule['fixed_regular'] : 0.0;
			$fixed_legacy     = isset( $rule['fixed_price'] ) ? (float) $rule['fixed_price'] : 0.0;

			if ( 'respect_sale' === $sale_interaction && $fixed_sale > 0 ) {
				return $fixed_sale;
			}

			if ( $fixed_regular > 0 ) {
				return $fixed_regular;
			}

			if ( $fixed_sale > 0 ) {
				return $fixed_sale;
			}

			return $fixed_legacy > 0 ? $fixed_legacy : null;
		}

		if ( 'percent' === $type ) {
			return $base_price + ( $base_price * ( (float) $rule['percent'] / 100 ) );
		}

		if ( 'amount' === $type ) {
			return $base_price + (float) $rule['amount'];
		}

		return null;
	}

	private function round_price( float $price ): float {
		$mode = Settings::get_rounding_mode();
		if ( '2' === $mode ) {
			return round( $price, 2 );
		}

		if ( '0' === $mode ) {
			return round( $price, 0 );
		}

		return $price;
	}
}
