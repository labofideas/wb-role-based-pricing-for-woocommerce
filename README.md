# WB Role Based Pricing for WooCommerce

Dynamic WooCommerce pricing by **Pricing Group** with eligibility from:

- WordPress roles
- Paid Memberships Pro levels
- WooCommerce Subscriptions status/product mapping

Author: **Wbcom Designs**  
Website: `https://wbcomdesigns.com/`

## What it does

- Create Pricing Groups (Wholesale, Dealer, VIP, etc.) with priority.
- Define default group rules:
  - Fixed price
  - Percentage adjustment
  - Fixed amount adjustment
- Override by scope:
  - Variation level
  - Product level
  - Category level
  - Global/default fallback
- Apply prices consistently on:
  - Shop/archive/single product
  - Cart/checkout/mini-cart
  - Order totals and order item audit meta (locked at purchase time)

## Eligibility resolution

Supported sources:

- Role
- Membership (Paid Memberships Pro)
- Subscription (WooCommerce Subscriptions)

Priority is configurable in settings:

- `Subscription > Membership > Role` (default)
- `Membership > Subscription > Role`
- `Role > Membership > Subscription`
- `Role > Subscription > Membership`

## Requirements

- WordPress (latest stable recommended)
- WooCommerce (latest stable recommended)
- PHP 8.0+
- Optional integrations:
  - Paid Memberships Pro
  - WooCommerce Subscriptions

## Installation

1. Copy plugin folder to `wp-content/plugins/wb-role-based-pricing-for-woocommerce`.
2. Activate WooCommerce.
3. Activate **WB Role Based Pricing for WooCommerce**.
4. Open `WooCommerce -> Settings -> WB Role Based Pricing`.
5. Create groups in `WooCommerce -> Pricing Groups`.

## Main admin screens

- `WooCommerce -> Settings -> WB Role Based Pricing`
- `WooCommerce -> Pricing Groups`
- Product edit -> `Product Data -> Pricing Groups`
- Product category edit -> `Pricing Group Category Rules`

Debug aid for admins:

- Append `?wbrbpw_debug=1` on frontend pages to inspect resolver details.

## Data keys

- Product/Variation meta: `_wb_pricing_group_prices`
- Category term meta: `_wb_pricing_group_category_rules`
- Order item audit meta:
  - `_wb_applied_pricing_group`
  - `_wb_eligibility_source`
  - `_wb_pricing_source`
  - `_wb_base_price`
  - `_wb_final_price`
  - `_wb_adjustment_details`

## QA and test evidence

- Playwright report and screenshot index:
  - `docs/PHASE1-PLAYWRIGHT-TEST-REPORT.md`

## Support

- Website: `https://wbcomdesigns.com/`
