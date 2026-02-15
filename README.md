# WB Role Based Pricing for WooCommerce

## Overview

Current release: `1.0.0` (Phase 1 complete)

Role/group-based dynamic pricing for WooCommerce with support for:

- Pricing Groups (priority + default rule)
- Role to Pricing Group eligibility mapping (MVP)
- Product-level rules
- Variation-level rules
- Category-level rules
- Frontend display pricing filters
- Cart/checkout recalculation
- Order line item audit meta locking
- Membership eligibility (Paid Memberships Pro)
- Subscription eligibility (WooCommerce Subscriptions)
- Source priority resolution (Subscription/Membership/Role)
- Frontend debug panel for admins

## Installation

1. Place plugin folder at `wp-content/plugins/wb-role-based-pricing-for-woocommerce`.
2. Activate WooCommerce.
3. Activate **WB Role Based Pricing for WooCommerce**.
4. Go to `WooCommerce -> Settings -> WB Role Based Pricing` and configure global behavior.

## Admin Areas

- `WooCommerce -> Pricing Groups`
- Product edit: `Product Data -> Pricing Groups`
- Product Category edit: `Pricing Group Category Rules`
- Debug panel for admins: append `?wbrbpw_debug=1` to storefront URLs

## Stored Meta Keys

- Product/Variation: `_wb_pricing_group_prices`
- Category term: `_wb_pricing_group_category_rules`
- Order item audit:
  - `_wb_applied_pricing_group`
  - `_wb_eligibility_source`
  - `_wb_pricing_source`
  - `_wb_base_price`
  - `_wb_final_price`
  - `_wb_adjustment_details`

## Notes

- HPOS compatibility is declared.
- Membership and subscription eligibility are intentionally left for Phase 2 via extension filters.

## Phase 1 Complete

- Role-based eligibility mapping
- Pricing Group CRUD + priorities + default group rules
- Product and variation-level pricing rules
- Category-level pricing rules with behavior control
- Frontend + cart + checkout pricing integration
- Order line item price-lock audit metadata
- Plugin list quick links: `Settings`, `Pricing Groups`

## Phase 2 (Current)

- Paid Memberships Pro level mapping per Pricing Group
- WooCommerce Subscriptions mapping per Pricing Group
  - Product IDs filter (optional)
  - Status filter (`active`, `on-hold`, `pending-cancel`)
- Eligibility source priority setting:
  - `Subscription > Membership > Role` (default)
  - `Membership > Subscription > Role`
  - `Role > Membership > Subscription`
  - `Role > Subscription > Membership`
- Eligibility debug panel for admins using query param `?wbrbpw_debug=1`

## QA Documentation

- Playwright test report and screenshot index:
  - `docs/PHASE1-PLAYWRIGHT-TEST-REPORT.md`
