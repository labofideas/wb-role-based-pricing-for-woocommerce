# Automated Regression Testing

Run the plugin smoke suite with:

```bash
wp eval-file wp-content/plugins/wb-role-based-pricing-for-woocommerce/scripts/regression-smoke.php --allow-root
```

## What this validates

1. Cart pricing idempotency (no stacked discounts on repeated recalculation).
2. Variation category fallback (variation inherits parent product category rule).
3. Settings allowlist fallback for invalid option values.
4. Product rule save nonce protection.

## Output

The script prints JSON with:

- `pass`: overall boolean.
- `tests`: array of named checks with `pass` and `details`.

The script creates temporary products/groups/categories and auto-cleans them.
