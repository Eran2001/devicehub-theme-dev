# Pricing Rules Guide

This theme integrates with the `aco-woo-dynamic-pricing` plugin. The important thing to understand is that pricing behavior comes from two layers:

1. Theme display logic
2. Plugin runtime calculation

The theme controls what users see on the product page.  
The plugin controls the final discount calculation in cart and checkout.

## 1. Core Concepts

Every pricing rule goes through two checks:

1. Match
2. Eligibility

### Match
A rule must target the current product, variation, category, list, or cart context.

Examples:
- a rule targets one product only
- a rule targets a category
- a rule targets the whole cart

### Eligibility
Even if a rule matches, it may not be active yet.

Examples:
- a quantity rule for `2-5` is not eligible at quantity `1`
- a scheduled rule is not eligible before its start date
- a cart rule may depend on cart contents or cart amount

Only rules that are both matched and eligible should be considered active.

## 2. Priority

Priority is only meaningful among eligible rules.

- lower number = higher priority
- priority `1` beats priority `2`
- but an ineligible priority `1` rule does not block an eligible priority `2` rule

### Example

- Rule A: quantity `2-5`, `40%`, priority `1`
- Rule B: product price `50%`, priority `2`

Behavior:
- quantity `1` -> Rule A is not eligible -> Rule B applies
- quantity `2-5` -> Rule A becomes eligible -> Rule A applies
- quantity `6` -> Rule A is no longer eligible -> Rule B applies again

This is the expected industry-standard behavior.

## 3. Rule Types

These are the main rule types we have dealt with so far.

### Product price rules
These directly discount the product price itself.

Examples:
- percentage of product price
- fixed amount from product price

Typical behavior:
- applies directly to the product unit price
- product page can preview this accurately
- cart and checkout usually follow the same discounted unit price

### Quantity rules
These depend on quantity ranges.

Examples:
- `2-5` items -> `40%` off
- `6+` items -> another value

Typical behavior:
- inactive outside the configured quantity range
- active only when the current quantity falls inside a configured range
- can override lower-priority product rules while eligible

### Cart total rules
These discount the cart total, not just the product page unit price.

Examples:
- `10%` off cart total
- fixed amount off cart total

Typical behavior:
- product page may show a preview badge
- actual money calculation happens in cart/checkout by the plugin
- this means product-page preview and final cart calculation are not exactly the same layer

## 4. Product Page vs Cart/Checkout

This is the most important distinction.

### Product page
The theme resolves and displays:
- pricing badge
- displayed discounted price
- quantity discount table visibility

The current theme logic reads plugin rule metadata and:
- checks which rules match the product
- checks whether a rule is eligible for the current quantity
- picks the highest-priority eligible rule
- updates the product-page UI accordingly

### Cart and checkout
The plugin calculates:
- line-item discounts
- cart-level discounts
- final totals

Cart and checkout are the real financial source of truth.

If product page and cart page ever disagree, the cart/checkout plugin calculation wins.

## 5. What The Theme Currently Handles Correctly

The single-product page logic has been aligned to the plugin rule model and now behaves correctly for:

- product-price rules
- quantity rules
- mixed quantity + product-price rules
- quantity-based rule switching by current quantity
- quantity table visibility for winning quantity rules
- variable products with native sale pricing when no matching pricing rule exists
- unrelated active rules no longer stripping variation sale display

### Current product-page rule resolution

The theme now follows this logic:

1. Find all matching pricing rules for the product
2. Sort by priority ascending
3. Check eligibility for the current quantity and rule type
4. Pick the highest-priority eligible rule
5. Use that single winning rule for:
   - badge
   - displayed price
   - quantity table visibility

## 6. Variable Products

Variable products add one more layer: the selected variation's native pricing.

The theme now preserves:
- native variation sale price
- native variation original price
- pricing updates per selected variation

Important behavior:
- if no matching pricing rule exists for the product, the theme preserves WooCommerce's native variation `sale + struck regular` display
- unrelated active plugin rules should no longer overwrite that display on the product page

## 7. Cart Rule Reality

Cart rules are more complex because the plugin calculates them server-side.

This means:
- product page can preview a cart rule badge
- cart page shows the real plugin outcome

So a cart rule can appear simple in configuration but still behave unexpectedly if the plugin applies it in a special way internally.

### Practical rule

Treat product page as a preview.  
Treat cart and checkout as the final truth.

## 8. What We Observed About Cart Rules

We tested cart total rules and saw that plugin behavior can become inconsistent depending on rule type and targeting.

For example:
- a cart total percentage rule may show the correct percentage at quantity `1`
- but at higher quantities the plugin may calculate a lower effective discount than the configured percentage

If that happens:
- it is not automatically a theme display bug
- it usually means the plugin runtime is discounting a different base than expected
- or the rule type is interacting strangely with product filters or sale pricing

## 9. Recommended Mental Model

Use this model when reasoning about rules:

### Product page
- theme interpretation
- UI preview
- best effort based on plugin rule metadata

### Cart and checkout
- plugin runtime calculation
- real totals
- final source of truth

### Priority
- applies only among eligible rules
- ineligible higher-priority rules do not block eligible lower-priority rules

## 10. Safe Testing Pattern

When testing pricing behavior, avoid creating many overlapping rules at once.

Use this order:

1. One rule only
2. Verify product page
3. Verify cart
4. Verify checkout
5. Add a second rule only after the first is understood

### Best test sequence

1. Product-price-only rule
2. Quantity-only rule
3. Mixed quantity + product-price rule
4. Cart-total-only rule
5. Mixed cart rule + product rule

This makes it much easier to tell whether a problem is:
- theme display logic
- plugin runtime logic
- rule overlap
- targeting/filter configuration

## 11. Recommended Usage Guidelines

To keep behavior predictable:

- avoid overlapping WooCommerce sale pricing and multiple dynamic rules unless necessary
- prefer one clear business rule per product scenario
- use priority only to break ties between rules that can become eligible in the same context
- be careful with cart-total rules plus product filtering
- always test product page and cart page separately

## 12. Current Caveats

These are the current known caveats:

- product-page pricing is now much more reliable than before, but cart and checkout still depend on plugin runtime behavior
- cart-total rules can behave differently from product-price rules because the plugin calculates them on the server side
- plugin behavior may differ when:
  - product filters are used
  - products are already on sale
  - multiple rules overlap
  - cart-level rules and product-level rules are active together

## 13. Short Reference

If you only remember a few things, remember these:

1. Match first, then eligibility
2. Priority matters only among eligible rules
3. Product page is a preview layer
4. Cart and checkout are the final truth
5. Quantity rules can switch the winning rule as quantity changes
6. Cart-total rules need separate testing because plugin runtime can behave differently
