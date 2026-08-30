# WooCommerce integration

Conditional WooCommerce loading, templates, the colour swatch system, and the
caching/scale prerequisites the catalog assumes.

---

WooCommerce features are **conditionally loaded** only when WooCommerce is active:

- Product gallery support (zoom, lightbox, slider)
- Custom product loop (3 columns, 12 products default)
- Color attribute/swatch management
- Cart and checkout templates

**WooCommerce Templates:**

- [archive-product.html](../templates/archive-product.html)
- [single-product.html](../templates/single-product.html)
- [page-cart.html](../templates/page-cart.html)
- [page-checkout.html](../templates/page-checkout.html)
- [taxonomy-product_cat.html](../templates/taxonomy-product_cat.html)

## Caching & scale prerequisites

The catalog is engineered to scale, but two infrastructure pieces are
**assumed, not optional**, at real traffic:

- **Persistent object cache (Redis/Memcached).** `Rendered_Product_Cache`
  hard-requires `wp_using_ext_object_cache()` — without one, the anonymous
  rendered-fragment cache (keyset pagination + load-more, stale-while-revalidate
  with a regeneration lock) **silently no-ops** and every REST page recomputes.
- **Full-page cache (Varnish/Batcache/WP Rocket/edge).** Only the REST
  load-more path goes through `Rendered_Product_Cache`; the **initial** anonymous
  archive/Product Collection paint recomputes on every request unless a page
  cache sits in front. Without one, first paint is the catalog's load ceiling.

**Full-page-cache correctness rule:** never bake a per-user/per-session _value_
into server HTML or `wp_interactivity_state` and trust it — a page cache serves
the priming visitor's copy to everyone. Personalized fragments must rehydrate
client-side (cart count → Store API `refreshCartCount()` on load, gated on the
`woocommerce_items_in_cart` cookie; wishlist → localStorage). Seeding **config,
i18n, and default flags** into interactivity state is fine; seeding a live count,
geo, or membership value is not. Nonces baked into cached HTML are a known,
separate WP-wide staleness class (12h tick) — handle via an uncached refresh, not
by baking them longer.

## Color Swatch System

The theme includes a comprehensive color attribute system for product variations:

| Class                        | Purpose                              |
| ---------------------------- | ------------------------------------ |
| `Color_Attribute_Manager`    | Manages WooCommerce color attributes |
| `Color_Data_Manager`         | Handles color data persistence       |
| `Color_Block_Swatch_Manager` | Renders swatches in blocks           |
| `Color_Pattern_Admin`        | Admin UI for color patterns          |
| `Color_Admin_UI`             | Color swatch admin interface         |
