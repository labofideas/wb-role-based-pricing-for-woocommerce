# WB Role Based Pricing for WooCommerce

## Phase 1 Playwright Test Report and Usage Guide

Date: 2026-02-15  
Plugin version: 1.0.0

## 1. Environment

- WordPress local site: `http://wbrbpw.local`
- WooCommerce: active
- Payment method used for checkout test: Cash on Delivery (COD)
- Storefront lock disabled for public testing:
  - `woocommerce_coming_soon = no`
  - `woocommerce_store_pages_only = no`

## 2. Test Users Used

- `Steve` (Administrator)
- `wholesale_user` (Role: `wholesale_customer`)
- `dealer_user` (Role: `dealer_customer`)
- `vip_user` (Role: `vip_customer`)

## 3. What Was Verified (Phase 1)

- Plugin settings tab appears under WooCommerce settings.
- Plugin action links include direct links to:
  - `Settings`
  - `Pricing Groups`
- Pricing Groups CRUD and role mapping UI.
- Product-level and variation-level pricing UI.
- Category-level pricing rules UI.
- Frontend role-based price rendering on shop and product pages.
- Cart/checkout totals reflect adjusted prices.
- Order stores locked pricing audit metadata:
  - `_wb_applied_pricing_group`
  - `_wb_pricing_source`
  - `_wb_base_price`
  - `_wb_final_price`
  - `_wb_adjustment_details`

## 4. Live Playwright Flow Completed

1. Opened shop as guest and captured default pricing.
2. Logged in as `wholesale_user` and confirmed role pricing on shop page.
3. Opened variable product and confirmed variation-level price application.
4. Added product to cart and completed checkout.
5. Captured order-received page with locked total.
6. Opened admin order screen and confirmed audit metadata on order line item.

## 5. Screenshots Index

Core admin setup:

1. `docs/screenshots/01-plugins-list-settings-links.png`  
   Plugin list action links (`Settings`, `Pricing Groups`).
2. `docs/screenshots/02-settings-tab.png`  
   WooCommerce settings tab: WB Role Based Pricing.
3. `docs/screenshots/02-pricing-groups-list.png`  
   Pricing Groups list (admin).
4. `docs/screenshots/04-pricing-group-edit.png`  
   Pricing Group edit screen with default rule + role mapping.
5. `docs/screenshots/05-simple-product-pricing-tab.png`  
   Product-level pricing group tab.
6. `docs/screenshots/06-variable-product-pricing-and-bulk-actions.png`  
   Variation pricing + bulk actions.
7. `docs/screenshots/08-category-rule-edit.png`  
   Category-level pricing rule editor.

Frontend/cart/order proof:

8. `docs/screenshots/09-shop-guest-pricing.png`  
   Guest storefront pricing baseline.
9. `docs/screenshots/10-shop-wholesale-user-pricing.png`  
   Wholesale role pricing on shop page.
10. `docs/screenshots/11-variable-product-wholesale-variation-price.png`  
    Variation-level price in frontend product view.
11. `docs/screenshots/12-checkout-wholesale-discounted-total.png`  
    Checkout totals with discounted line item.
12. `docs/screenshots/13-order-received-wholesale-locked-price.png`  
    Order-received page with final locked total.
13. `docs/screenshots/14-admin-order-item-audit-meta.png`  
    Admin order item meta proving audit lock values.
14. `docs/screenshots/15-guest-hidden-price-text.png`  
    Guest hidden-price text behavior validation.
15. `docs/screenshots/16-shop-guest-grouped-external-pricing.png`  
    Guest pricing for grouped and external product types.
16. `docs/screenshots/17-shop-wholesale-grouped-external-pricing.png`  
    Wholesale pricing for grouped and external product types.

## 6. Bug Found and Fixed During Testing

Issue: fatal error on shop/variation pricing filter path due to incorrect hook argument order in variation price callback.

Fix applied:

- File: `includes/class-pricing-engine.php`
- Method signature corrected to match WooCommerce hook args:
  - `filter_variation_price( $price, \WC_Product_Variation $variation, \WC_Product_Variable $product )`

Result: shop and variable pricing now load correctly for role-based prices.

## 7. Security Review Status (Phase 1)

A focused WordPress security pass was completed on key write paths:

- Nonces present on pricing group/product/category admin forms.
- Capability checks present on pricing group save path.
- Sanitization/normalization applied for rule fields before storage.
- Output escaping used in admin render templates.

No critical (blocker) security issue was identified in this Phase 1 review.

## 8. Phase 1 Conclusion

Phase 1 implementation is functionally complete and validated with end-to-end Playwright evidence for:

- Role eligibility mapping
- Dynamic pricing display
- Cart/checkout consistency
- Order audit locking
- Admin management flows

## 9. Additional QA (Items 1, 2, 3)

This section covers the expanded QA pass requested after initial Phase 1 completion.

### 9.1 Item 1: Broader Regression Scenarios

Additional product types were created and verified in frontend pricing output:

- `23` WB External Test Product (external)
- `24` WB Grouped Child A (simple)
- `25` WB Grouped Child B (simple)
- `26` WB Grouped Test Product (grouped)
- `27` WB Edge Decimal Product (simple, decimal edge-case)

Playwright validation confirmed:

- Guest prices:
  - External: `₹90.00`
  - Grouped child A: `₹50.00 -> ₹45.00`
  - Grouped child B: `₹60.00`
  - Grouped parent range: `₹45.00 – ₹60.00`
- Wholesale prices:
  - External: `₹70.00`
  - Grouped child A: `₹50.00 -> ₹70.00`
  - Grouped child B: `₹70.00`
  - Grouped parent: `₹70.00`

No fatal errors occurred in grouped/external rendering paths.

### 9.2 Item 2: Cache and User-Bleed Safety Checks

Session sequence executed:

- `guest_1` -> `wholesale_user` -> `guest_2_after_wholesale` -> `dealer_user` -> `vip_user`

Between runs, WordPress cache/transients were flushed. Result:

- Guest price before and after wholesale session remained identical.
- No cross-user pricing bleed was detected in this environment.

Observed matrix samples:

- Guest simple product: `₹120 -> ₹100`
- Wholesale simple product: `₹120 -> ₹80`
- Dealer simple product: `₹120 -> ₹80`
- VIP simple product: `₹120 -> ₹95`

### 9.3 Item 3: Edge-Case QA (Base Price, Sale Interaction, Rounding)

Dealer decimal rule test on product `27` (`-12.5%`) produced expected values:

- Base `lowest` + rounding `none`: `₹76.13` (from sale base `87`)
- Base `regular`: `₹86.63` (from regular base `99`)
- Rounding nearest integer: `₹87.00`

Sale interaction toggle validation:

- `respect_sale` and `override_sale` both tested.
- Pricing behavior switched correctly when setting changed.

Guest hidden price behavior validation:

- `wbrbpw_hide_guest_price=yes` and custom text tested.
- Frontend displayed configured custom guest message.

## 10. Post-QA State

After completing the expanded QA pass, plugin settings were restored to baseline:

- `wbrbpw_base_price=lowest`
- `wbrbpw_sale_interaction=respect_sale`
- `wbrbpw_rounding=none`
- `wbrbpw_hide_guest_price=no`
- `wbrbpw_guest_text=Login to see pricing`
