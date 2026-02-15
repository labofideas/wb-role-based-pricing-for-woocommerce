# Changelog

## 1.1.0 - Unreleased

- Added Paid Memberships Pro level mapping to Pricing Groups.
- Added WooCommerce Subscriptions mapping to Pricing Groups.
- Added eligibility source priority setting (role/membership/subscription).
- Added `_wb_eligibility_source` in order item audit metadata.
- Added frontend debug panel for admins with `?wbrbpw_debug=1`.

## 1.0.0 - 2026-02-15

- Initial stable release (Phase 1).
- Added Pricing Groups CRUD with priority and role mapping.
- Added global settings under `WooCommerce -> Settings -> WB Role Based Pricing`.
- Added product and variation rule UI (including variation bulk actions).
- Added category-level rules and precedence handling.
- Added frontend pricing filters for product/shop/variation contexts.
- Added cart and checkout recalculation integration.
- Added order item audit metadata lock:
  - `_wb_applied_pricing_group`
  - `_wb_pricing_source`
  - `_wb_base_price`
  - `_wb_final_price`
  - `_wb_adjustment_details`
- Added plugin action links: `Settings`, `Pricing Groups`.
- Declared WooCommerce HPOS compatibility.
