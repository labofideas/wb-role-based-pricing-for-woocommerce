# WB Role Based Pricing - QA Checklist

## Setup

1. Ensure WooCommerce is active.
2. Create at least 2 pricing groups with different priorities.
3. Create products for each type: simple, variable (with variations), and sale product.
4. Create at least 2 product categories.
5. Create users covering all target roles.

## Core Pricing

1. Verify plugin on/off toggle changes pricing behavior immediately.
2. Verify base price source modes: `regular`, `sale`, `lowest`.
3. Verify sale interaction modes: `respect_sale`, `override_sale`.
4. Verify rounding modes: `none`, `2`, `0`.

## Eligibility Sources

1. Role mapping: each mapped role gets correct group.
2. Multiple eligible groups: lower priority number wins.
3. Source priority orders are respected.
4. Guest mode `default`: no group pricing for guests.
5. Guest mode `group`: guest gets configured group pricing.

## Rule Precedence

1. Product rule applies over category `if_no_product_rule`.
2. Category `override_product_rules` overrides product rules.
3. Variation rule overrides parent product rule.
4. Variation without own categories still receives parent category rule.
5. Group default rule applies when product/category rules are absent.

## Cart + Checkout

1. Add product to cart and confirm adjusted line price.
2. Update quantity and confirm no double-discount stacking.
3. Recalculate cart totals multiple times and confirm stable final price.
4. Checkout order and confirm pricing metadata is saved on line items:
   - `_wb_applied_pricing_group`
   - `_wb_eligibility_source`
   - `_wb_pricing_source`
   - `_wb_base_price`
   - `_wb_final_price`
   - `_wb_adjustment_details`

## Admin UX + Security

1. Pricing Group, Product, and Category forms save with valid nonce/capability.
2. Invalid/empty values do not break frontend pricing.
3. Product variation bulk actions work:
   - copy parent to all
   - clear all
   - apply/clear selected groups
4. Guest hidden price text displays correctly when configured.

## Integrations

1. Paid Memberships Pro mapping resolves expected groups (if active).
2. WooCommerce Subscriptions mapping resolves expected groups by status/product (if active).

## Debug + Compatibility

1. Debug panel appears only for users with `manage_woocommerce` and `?wbrbpw_debug=1`.
2. Plugin activation/deactivation completes without fatal errors.
3. HPOS-enabled checkout works with order metadata audit.

## Regression Smoke (WP-CLI)

1. `wp plugin status wb-role-based-pricing-for-woocommerce`
2. Lint all PHP files: `find . -name '*.php' -print0 | xargs -0 -n1 php -n -l`
