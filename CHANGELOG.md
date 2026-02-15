# Changelog

## 1.1.0 - 2026-02-15

- Added Phase 2 eligibility source integrations:
  - Paid Memberships Pro membership-level mapping to Pricing Groups.
  - WooCommerce Subscriptions mapping to Pricing Groups (product IDs + status filters).
- Added settings option for eligibility source priority resolution.
- Added resolver debug data and frontend admin debug panel (`?wbrbpw_debug=1`).
- Added order item audit key `_wb_eligibility_source`.

## 1.0.0 - 2026-02-15

- Finalized Phase 1 feature set for role-based pricing.
- Added pricing groups CPT with priority, default rules, and role mapping.
- Added plugin settings under WooCommerce settings tab.
- Added product and variation pricing rule UIs and storage.
- Added variation bulk actions: copy parent, clear all, group apply/clear.
- Added category-level pricing rules with behavior controls.
- Added pricing engine integration for product pricing hooks and variation hash.
- Added cart recalculation pricing integration.
- Added order item pricing audit metadata lock.
- Added plugin action links: `Settings` and `Pricing Groups`.
- Declared WooCommerce HPOS compatibility.
