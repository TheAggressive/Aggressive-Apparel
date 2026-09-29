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

**International stores:** the page cache must vary on the active currency
(e.g. WooPayments' currency cookie) and on geolocation (WooCommerce → General →
_Geolocate (with page caching support)_). Product prices need this anyway, and
the free-shipping blocks rely on it for empty-cart visitors (below).

## Free shipping (international)

`free-shipping-bar` / `free-shipping-message` show progress toward the
**customer's** free-shipping minimum, in the **active currency**:

- **Rules** — `Free_Shipping_Rules` matches the customer's shipping package to a
  zone and reads each free-shipping method's `min_amount` property (the value
  WooCommerce tests, which multi-currency plugins convert). "Minimum AND coupon"
  counts only once a free-shipping coupon is applied.
- **Delivery** — server HTML carries a first-paint value; the live one rides on
  the Store API cart response (`extensions["aggressive-apparel/free-shipping"]`:
  `threshold`/`subtotal` in minor units, `rate`, `unlocked`). A zone without
  free shipping renders the block `hidden` (never absent), so a cached page can
  reveal it client-side.
- **No request for empty carts** — without the `woocommerce_items_in_cart`
  cookie the cart side is zero and the server-rendered threshold is trusted
  (hence the cache-variation requirement above).
- **Instant updates** — Store API cart mutations (including `/batch`) answer
  with the full cart, extensions included; the blocks apply that response
  directly, with no second read. Mutations are sequenced, so a late response
  to an older mutation is dropped. Only responses without a cart (classic
  `wc-ajax` add-to-cart) fall back to a debounced `/cart` read.
- **Wording** — `Free_Shipping_Message` resolves the copy in layers: the
  block's own wording ("Custom wording for this block"), then a legacy
  `emphasisText` phrase, then the site wording (two **Store Copy** fields,
  also editable inline in either block like Site Title), then the gettext
  default. Wording is plain text — `{amount}` for the amount, `*asterisks*`
  to highlight. A blank Store Copy field follows the translated default;
  saved wording goes to WPML/Polylang string translation, and per-block
  wording is declared in `wpml-config.xml`. Server HTML and live updates
  render the same segments (`Free_Shipping_Message::segments()` /
  `buildFreeShippingSegments()`); translators get `{em}`…`{em_end}` markers,
  which the i18n placeholder lint protects. Custom code can replace the
  default templates with `aggressive_apparel_free_shipping_message_i18n`,
  which supersedes the removed `aggressive_apparel_free_shipping_bar_message_i18n`.
- **Currency** — WooPayments multi-currency is supported built in. Other
  switchers: convert `min_amount` via `woocommerce_shipping_zone_shipping_methods`
  (as WooPayments does) and supply the rate for store-currency overrides through
  `aggressive_apparel_free_shipping_currency_rate`.

**Upgrade contract.** Whether free shipping is unlocked is always WooCommerce's
own verdict (`WC_Shipping_Free_Shipping::is_available()`, filters included) —
the theme never re-implements it. Only the displayed amount is derived, and
`TestFreeShipping`'s parity cases assert it agrees with that verdict at the
boundaries (discounts, `ignore_discounts`, tax-inclusive display, rounding).
WooCommerce upgrades go through the pinned version in `bin/ci/.wp-env.json`, so
a behaviour change fails CI on the pin bump rather than drifting in production.
WooPayments is not in CI: its rate is shape-checked and an unreadable rate or an
unconverted minimum logs a `WP_DEBUG` diagnostic instead of guessing.

## Back in Stock

Signups are **double opt-in**. A signup is stored as `pending` and emails a
confirm link (WooCommerce → Settings → Emails → _Back in Stock confirmation_);
only confirmed (`active`) rows receive the restock email. Unconfirmed rows are
deleted after 7 days, and pending rows count toward the per-address cap.

- **Mail must work.** If the confirmation email can't be sent, the signup is
  rolled back and the shopper sees an error, rather than being left pending
  with no way to confirm.
- **Disabling the confirmation email** makes signups single opt-in: they
  activate immediately.
- **Email links act on POST only.** Opening the confirm or unsubscribe link
  shows a page with a button. Mail security scanners (Safe Links, Mimecast)
  fetch every link, so a GET that changed state would confirm or unsubscribe on
  the reader's behalf. Restock emails also carry RFC 8058
  `List-Unsubscribe` / `List-Unsubscribe-Post` headers for the mail client's
  native one-click unsubscribe.
- **No nonce for guests.** A nonce baked into a page-cached copy expires, so
  guest signups are protected by rate limits and the consent box instead;
  logged-in signups are still nonce-checked.

## Color Swatch System

The theme includes a comprehensive color attribute system for product variations:

| Class                        | Purpose                              |
| ---------------------------- | ------------------------------------ |
| `Color_Attribute_Manager`    | Manages WooCommerce color attributes |
| `Color_Data_Manager`         | Handles color data persistence       |
| `Color_Block_Swatch_Manager` | Renders swatches in blocks           |
| `Color_Pattern_Admin`        | Admin UI for color patterns          |
| `Color_Admin_UI`             | Color swatch admin interface         |
