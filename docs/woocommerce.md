# WooCommerce integration

Conditional WooCommerce loading, templates, size guides and specs, the colour
swatch system, and the caching/scale prerequisites the catalog assumes.

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

- **Persistent object cache (Redis/Memcached).** Facet counts, the rate
  limiter and the search cache fall back to transients without one, writing to
  `wp_options` on anonymous requests.
- **Full-page cache (Varnish/Batcache/WP Rocket/edge).** Every product grid is
  a real archive page — first paint, each Load More page (`/shop/page/N/`) and
  each filtered URL — so a page cache serves all of them. Without one, every
  catalog page renders uncached and that is the catalog's load ceiling.
- **Real client IP.** Public endpoints (search, catalog facets, back-in-stock
  signup) rate-limit per IP via `Client_IP`, which trusts
  `REMOTE_ADDR`, plus `CF-Connecting-IP` only from Cloudflare's published
  ranges. Behind any other proxy or load balancer, have the server rewrite
  `REMOTE_ADDR` to the visitor (nginx `real_ip`, Apache `mod_remoteip`) or
  return it from the `aggressive_apparel_client_ip` filter; otherwise every
  visitor shares one bucket and shoppers get 429s at peak.

**Full-page-cache correctness rule:** never bake a per-user/per-session _value_
into server HTML or `wp_interactivity_state` and trust it — a page cache serves
the priming visitor's copy to everyone. Personalized fragments must rehydrate
client-side (cart count → Store API `refreshCartCount()` on load, gated on the
`woocommerce_items_in_cart` cookie; guest wishlist → localStorage; account wishlist → Shopper Lists Store API). Seeding **config,
i18n, and default flags** into interactivity state is fine; seeding a live count,
geo, or membership value is not. Nonces baked into cached HTML expire after
12–24h, so nothing may depend on one: guests get no REST nonce (the catalog
endpoints are public, and WordPress 403s any request carrying an expired one),
Store API cart calls go through `storeApiPost()` (adopts the fresh nonce the
Store API returns and retries once), and guest back-in-stock signups skip the
nonce check.

**Design edits purge the page cache.** Templates, template parts, global
styles, navigation and synced patterns have no URL of their own, so a page
cache would keep serving the old design on cached pages while uncached ones
render the new one — and Load More would append one to the other. `Page_Cache_Purge` fires
Breeze's full purge (`breeze_clear_all_cache`: local, Varnish, Cloudflare) once
per request when any of them is saved or reset; Breeze itself skips these
updates. Another page cache needs the equivalent hook.

**International stores:** the page cache must vary on the active currency
(e.g. WooPayments' currency cookie) and on geolocation (WooCommerce → General →
_Geolocate (with page caching support)_). Product prices need this anyway, and
the free-shipping blocks rely on it for empty-cart visitors (below).

## Catalog pagination, filters and sorting

Product grids only ever come from a real render of the archive page, so the
cards Load More appends or a filter shows always match the page — template,
prices and currency included.

- **URL contract.** Filters and sorts live in the query string: `cat`, `color`,
  `size`, `collection`, `min_price`, `max_price`, `stock=instock`, `on_sale=1`
  and WooCommerce's `orderby`. `Catalog_Url_Filters` parses them (bounded and
  validated); its parameter map mirrors `ATTRIBUTE_FILTERS` in
  `src/interactivity/product-filters/types.ts`, and a PHPUnit test keeps the
  two (and the sort lists) in sync.
- **Server-side filtering.** `Catalog_Archive_Filters` applies them to the
  archive's own query: the main query for inherited Product Collections, the
  template's main collection (matched by `queryId`) for explicit ones. A
  filtered URL therefore renders correctly on first load and without
  JavaScript. Rank Math canonicalises it to the unfiltered archive.
- **Load More / infinite scroll** fetch the native "next page" link the page
  rendered, parse it and append its cards; the seed (totals and the URL of the
  page after it) comes from that page too. Per-request class names
  (`wp-elements-N`, style variations) are renamed for the appended page and
  its block-support rules copied with them. Cards are never repeated, and the
  order always ends on the post ID so pages never overlap.
  Requesting, prefetching and retrying live in `load-more/pager.ts`
  (unit-tested); failures are retried after `Retry-After` or a backoff and,
  when retries run out, the next page is offered as a link. The appended
  pages are saved in the history entry, so Back rebuilds the grid and scroll
  position when the browser reloads the page instead of restoring it from
  its back/forward cache.
- **Filters and sorts** navigate to the new URL with WordPress's Interactivity
  router, which swaps the collection's router region; WooCommerce's Catalog
  Sorting block does this itself. Changing category is a full navigation
  (title, breadcrumbs, canonical). A filtered selection with no matches gets a
  No Results block when the template has none — WooCommerce otherwise renders
  nothing, not even the region.
- **Card features on appended cards.** Load More inserts cards into a grid
  the Interactivity API has already hydrated, and it never hydrates them:
  `data-wp-on--*` and other directives inside a card do nothing on page 2
  onwards. Anything interactive on a product card must listen at the
  document level and identify its card from the DOM (Quick View and the
  wishlist delegate their clicks; `aa:cards-rendered` fires after each
  append for per-card setup). `catalog-load-more.spec.ts` checks both on an
  appended card.
- **Facets** (which options still have matches) are the one JSON endpoint:
  `aggressive-apparel/v1/catalog/facets`, data only.

**Prerequisite for explicit collections:** they paginate with
`?query-<id>-page=N`. Some hosts block that parameter (production returns 403),
which breaks native pagination and Load More alike. Set the archive template's
Product Collection to _Sync with current query_ (pages become `/shop/page/N/`),
or have the host allow the parameter.

## Product cards on phones and touch devices

Every behavior below is a Store Enhancements option, so nothing is fixed in
code.

- **Card Buttons on Touch Devices** (Quick View sub-option): devices that
  can't hover would otherwise show Quick View and Wishlist on every image.
  The default shows only the wishlist heart: tapping the image already opens
  the product, so Quick View adds little on a phone, and saving takes one tap.
  CSS hides the Quick View trigger on touch; the markup is the same for every
  device, so pages stay cache-safe. The "behind one chip" option opens the
  buttons as a native popover
  (`popover="auto"`, CSS anchor positioning), so the browser handles
  tap-outside, Escape, one-open-at-a-time and the expanded state, with no
  JavaScript. Quick View closes the popover before opening (`view.ts`), since
  the top layer would float it above the modal. Hover devices, and browsers
  without anchor positioning, get the buttons inline as before. Popover ids
  include the product id because each Load More page is a separate render.
  The other options are "always visible" and "hidden".
- **Wishlist Heart with a Mouse** (Quick View sub-option): by default the heart
  stays visible on hover devices (`--wishlist-pinned`) and Quick View appears
  on hover; "on hover" reveals both together.
- **Badges on phones** (Product Badges sub-options, under 600px wide): hide the
  sale badge (the crossed-out price already shows it), cap the badges per
  product by badge priority across every position, and scale them with
  `zoom` (a transform would break glass badges). The server marks the
  badges; CSS applies the breakpoint, so pages stay cache-safe.

`card-touch-actions.spec.ts` covers the chip (including on appended cards);
`TestCardDisplaySettings` covers the options and badge ranking.

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

The waitlist is **WooCommerce's own Back in Stock Notifications** (11.2+):
storage, double opt-in, the verify / restock / unsubscribe emails, Action
Scheduler sending, the admin list (WooCommerce → Notifications), My Account
management, retention and erasure. The theme's **Back in Stock** toggle adds
only what a block product page lacks. Setup takes both switches:

1. WooCommerce → Settings → Advanced → Features → _Back in Stock
   Notifications_ (experimental, off by default).
2. WooCommerce → Settings → Products → Customer stock notifications → allow
   signups (and pick double opt-in / account-only there).

Until both are on, the theme renders nothing and shows an admin notice.

- **The form.** WooCommerce's form skips `woocommerce/add-to-cart-with-options`,
  so the theme renders one: in place of add-to-cart when the whole product is
  sold out, and after it for a sold-out **size** of an in-stock variable
  product. The variation form follows the shopper's choice through the same
  `woocommerce/products::state.productVariationInContext` bindings WooCommerce's
  own markup uses (hidden until a variation is chosen, shown only while it is
  out of stock, posting its ID). Theme JS never reads WooCommerce's private
  store. If the store hides out-of-stock items, sold-out sizes can't be chosen,
  so only whole-product signups remain.
- **One adapter.** Every call into WooCommerce's `Internal` namespace goes
  through `Back_In_Stock_Native`. That namespace has no compatibility promise:
  its surface is listed in `stubs/woocommerce-stock-notifications.php` (for
  PHPStan) and `TestBackInStockNativeContract` checks every symbol against the
  pinned WooCommerce, so an upstream rename fails CI, not checkout. Bump the
  CI pin (`bin/ci/.wp-env.json`) before trusting a new WooCommerce release.
- **Email links act on POST only.** WooCommerce's verify and unsubscribe links
  act on GET, and mail security scanners (Safe Links, Mimecast) fetch every
  link — they would confirm a double opt-in, or cancel a waitlist entry, for
  the reader. `Back_In_Stock_Links` answers that GET with a page whose button
  POSTs to the same URL, which WooCommerce then processes. Restock emails gain
  RFC 8058 `List-Unsubscribe` / `List-Unsubscribe-Post` headers built from the
  body's own single-use link. PHPMailer's `mail()` transport RFC 2047-encodes
  headers over ~45 characters, which clients don't decode there; send through
  SMTP or a mail service for one-click unsubscribe to work.
- **Throttling.** Guests need no nonce (a page-cached one expires); logged-in
  signups are nonce-checked. Guests are limited per real client IP by the
  theme's `Rate_Limiter`; WooCommerce's own per-client delay keys on raw
  `REMOTE_ADDR` (one shared address behind a proxy), so it is turned off for
  the theme's endpoint only, while its per-email and per-account delays stay.
  An address can hold at most three open signups.
- **Privacy.** WooCommerce registers an eraser and deletes unverified signups
  after its configured days; the theme adds the personal-data exporter and
  the suggested privacy-policy text. The consent checkbox is required to
  submit but isn't stored per signup — with double opt-in, the verified
  address is the consent record.
- **Migration.** Waitlists from the theme's old `aa_stock_subscribers` table
  move into WooCommerce in Action Scheduler batches from an admin visit, once
  WooCommerce's feature is on (`Back_In_Stock_Migration`). Only confirmed rows
  move; the table is dropped after every row moved, and kept (retrying hourly)
  if any failed.

## Collection vs Fit

Two separate concepts, two separate systems:

- **Collection** — who the garment is cut for: global attribute
  `pa_collection` (Men's `mens`, Women's `womens`, Unisex `unisex`). It drives
  the product-filter **Collection** control and the `?collection=` URL param
  (`/shop/t-shirts/?collection=mens`). Nothing else in the filter stack is
  Collection-specific: `ATTRIBUTE_FILTERS` (TS) and the facet taxonomy list
  (PHP) name it, and the query layer accepts any registered `pa_*`.
- **Fit / sizing** — garment silhouette and true-to-size advice: the
  Product Specs **Fit** scale and its per-step advice
  ([Size Guides & Specs](#size-guides--specs)). It reads no attribute.

The audience filter used to read `pa_fit` / `?fit=`; it now reads only
`?collection=`. Nothing redirects `?fit=` any more, so stored links (Site
Editor navigation, external links) must use `?collection=`.

### Men's / Women's landing pages

Indexable audience pages are **native product subcategories** (e.g. *Men's
T-Shirts* under *T-Shirts* → `/shop/t-shirts/mens-t-shirts/`), so Rank Math,
sitemaps, breadcrumbs, menus and product feeds handle them with no theme code.
Assign products to the subcategory as well as the Collection attribute.
Category slugs are unique site-wide, hence `mens-t-shirts`, not `mens`.

`?collection=` stays a **browsing filter**: applied by the product-filters
script, not the server, and canonicalised to the category by Rank Math. A
parent category's archive, facets and custom sorts include its subcategories'
products.

### Migrating `pa_fit` → `pa_collection`

`wp aggressive-apparel collection-migrate` (`Collection_Attribute_Migration`).
Never runs automatically. Dry run unless `--apply`.

- Creates the Collection attribute and its three terms if missing.
- For every product (any status) with an audience term on `pa_fit`, adds the
  matching `pa_collection` term through WooCommerce CRUD. Only `mens`, `men`,
  `womens`, `women`, `unisex` count as audience; any other `pa_fit` term is
  ignored. Collection is never a variation attribute.
- Idempotent: re-runs report `unchanged`, never duplicate terms.
- `--remove-source` additionally drops the migrated terms from `pa_fit`
  (removing the attribute only when nothing else is left on it). It skips
  products where `pa_fit` is used for variations, since their
  `attribute_pa_fit` values would be orphaned. Those are reported `skipped`
  with a note and a closing warning — fix them by hand.
- Aborts before touching products if a Collection term cannot be created,
  and flushes only the runtime object cache between batches (never a shared
  persistent cache).

Production runbook:

1. Back up the database.
2. Deploy the theme, then immediately run
   `wp aggressive-apparel collection-migrate` and review the report.
   Until step 3, Collection links show unfiltered results.
3. `wp aggressive-apparel collection-migrate --apply --format=json > collection-migration.json`
   (keep the output — it is the per-product change record).
4. If **WooCommerce → Settings → Advanced → Features → attribute lookup
   direct updates** is off, let Action Scheduler drain (or run
   **WooCommerce → Status → Tools → Regenerate the product attributes lookup
   table**) — facets read the lookup table; filtering itself does not.
5. Purge the page cache, then verify: `/shop/?collection=mens`, `womens`,
   `unisex` each list the expected products; re-running step 2 reports zero
   `would-update`.
6. Update the header and mobile-nav template parts in the Site Editor to use
   `?collection=` (or reset them to the theme file if they carry no other
   customisations).
7. Optional, later: `--apply --remove-source` once nothing reads `pa_fit` as
   an audience.

Rollback: before `--remove-source`, `pa_fit` is untouched, so reverting the
theme restores the old behaviour; the extra `pa_collection` terms are inert
and can be removed in **Products → Attributes**. After `--remove-source`,
restore the backup or re-add the terms listed under `remove` in the step 3
output.

## Size Guides & Specs

One feature (Store Enhancements → **Size Guides & Specs**, key `size_guide`),
managed on **Products → Size & Specs** (`Product_Specs_Settings`, React app in
`src/scripts/admin/product-specs/`). A size guide is one garment blank: its
size chart, notes, measuring diagram, and spec values. Products pick a guide,
or get one from their category or the store-wide default
(`Size_Guide::candidate_guide_ids()`; assignments in
`Size_Guide_Assignments`).

The page has three tabs:

- **Size guides** — list plus editor (`Size_Guide_Rest`). The chart is a
  spreadsheet-style grid: paste a table from Excel/Sheets into the first Size
  cell (its header row becomes the columns), Enter moves down and adds a size
  after the last, and header/row menus insert, move, duplicate, or delete.
  Columns are measurements (converted in ↔ cm) or text (size systems like
  "US" stay as typed). Templates: Tops, Bottoms, Hats. Duplicate copies chart,
  notes, diagram, and values (not assignments), saving pending edits first.
- **Specs** — define spec items: a **scale** (picked step), a **scale from a
  number** (step from boundaries, optionally per category), or a **text
  detail**. Add from presets, from suggestions (attributes products already
  use, `Product_Specs_Suggestions`, cached until a product or attribute
  changes), or from scratch. A spec whose attribute names match no product is
  flagged. Stored in `aggressive_apparel_spec_items`, validated whole by
  `Product_Specs_Registry::validate()`.
- **Products** — every product's own values in a searchable, filterable grid
  with bulk set and an "only products with gaps" filter (`Product_Specs_Grid`).

The product's **Size & specs** tab picks its guide and overrides, hides, or
rewords specs for that product only. Saving it rewrites only the specs it
showed, so overrides for specs that apply elsewhere are kept.

### Old guides

Free-form guides are converted by `Size_Chart_Import`: the first table
becomes the chart, text becomes notes in document order (links keep their
address), and one media-library image becomes the measuring diagram. A later
table is dropped only when it is the first one in the other unit (same sizes,
every number ×2.54 apart); any other table is kept as lines in the notes. The
unit comes from the column headers ("Chest (cm)"), then unambiguous words
("inches", "centimetres" — never a bare "in"), then the numbers (median above
45 = cm).

The automatic pass (first admin load; batched; flag
`aggressive_apparel_size_charts_migrated` set only once every guide is done)
converts only guides that lose nothing shoppers see. Richer guides (more
images, embeds, extra tables) keep rendering as they are until converted from
the editor, which previews the result and warns about what didn't carry over.
The original content always stays on the post.

### Storefront

- **Dialog:** `Size_Guide::resolved()` picks the guide (product → category →
  default, first with content) and returns `{id, html, chart, unit, measures}`,
  cached per product and keyed by `Size_Guide::MARKUP_VERSION` (bump it when
  the markup changes). The Size Guide block renders the button and dialog;
  without the block, product pages with a guide get the dialog in the footer
  with a hidden trigger, so the links below still open it.
- **Chart:** measurements are stored as entered; both units are in the markup
  and the in/cm switch beside the dialog heading only flips `data-unit`. Each
  value shows its unit ("30 cm"). The guide's name is admin-only: the table is
  named "Size chart" for screen readers. The first visit defaults to inches in
  the US, Liberia, and Myanmar and centimetres elsewhere, then remembers the
  choice in `localStorage`.
- **Picked size:** highlighted when the guide opens — classic selects, block
  pills (ARIA radios and their hidden input), and radios, from attributes
  named like "size" (else any picked attribute). Spelled-out names fold to
  short ones, so "Large" matches an "L" row
  (`src/interactivity/size-guide/sizes.ts`).
- **"(Guide)" link:** `Size_Guide_Label_Link` adds it beside a size
  attribute's name (also Taille, Talla, Größe…) when the product has a guide.
  The name itself stays plain text because WooCommerce uses it as the size
  options' accessible name; screen readers hear the link as "Size guide".
- **Product Specs block:** _full_ (scales with end labels, optional details
  list) or _compact_ (a card per scale, with its advice). Both can end with
  the picked size's measurements ("Size M · Chest 42 in", in the shopper's
  unit; "Pick a size to see its measurements." until one is picked) and a
  **Size guide** link. The server prints every size's line in both
  units; `src/interactivity/size-guide/specs.ts` picks one in the browser. The
  block may sit in product tab content, which kses filters, so pending parts
  hide by class (not `hidden`) and clicks are wired from the init callback
  (kses strips `data-wp-on--*`); the tabs' kses list allows the meter's
  `aria-value*` attributes.
- **Additional information:** `Product_Specs_Table` adds rows, skipped when a
  visible attribute already shows the value; the tab is added for products
  with specs but no attributes, and shipping weight/dimensions rows can be
  hidden.

### Data

- **Chart:** `_aa_size_chart` (unit, columns, rows), `_aa_size_notes`,
  `_aa_size_diagram` on the guide (`Size_Chart`).
- **Spec values:** product's own value → a product attribute named in the
  item's attribute list (a picked scale matches the text to a step name;
  `g/m²` also reads ounces) → the product's size guide. One scalar meta per
  item, `_aa_spec_{key}`, registered with `show_in_rest`, so it round-trips
  through WooCommerce CSV import/export as `Meta: _aa_spec_{key}`.
  `Product_Specs::for_product()` is memoized per request and forgotten on any
  post/term meta, term, or watched option change.
- **Translation:** guide names, chart labels, and spec names are stored text,
  not POT strings — translate them with a multilingual plugin.
- **Filter:** `aggressive_apparel_product_specs_attribute_names( $names, $key )`.

All of it is product data, never per-visitor, so it is safe in a full-page
cache: the shopper's unit and picked size are applied in the browser.

## Color Swatch System

Swatches are **WooCommerce's own visual attributes**: `pa_color` is a
"Color / image" attribute (`wc-visual`) and each term carries WooCommerce's
`color` (hex) or `image` (attachment) meta, edited on the term screen with
WooCommerce's colour picker / image field (which also adds the list column).
WooCommerce draws them in the product page's variation selector; the theme
keeps their look and supplies every other surface.

| Class                        | Purpose                                                                        |
| ---------------------------- | ------------------------------------------------------------------------------ |
| `Color_Attribute_Manager`    | Provisions `pa_color` and its default terms                                    |
| `Color_Data_Manager`         | Reads/writes swatch meta for cards, Quick View, sticky add-to-cart and filters |
| `Color_Block_Swatch_Manager` | Decorates WooCommerce's swatch chips so `color-swatches.css` styles them       |
| `Color_Swatch_Migration`     | One-off move from the theme's old meta; enables the WooCommerce feature        |
| `Swatch_Tooltips`            | Adds fabric name / composition (term fields) to the hover tooltip              |

- **Requires WooCommerce's "Color swatches for attributes"** (Settings →
  Advanced → Features; experimental, block themes only). The migration turns
  it on, sets the type, and records itself done only once WooCommerce reports
  the type changed; otherwise it retries hourly. Whenever product pages can't
  get swatches (feature off, type not "Color / image", or a WooCommerce
  without the feature) an admin notice says so and why.
- **Nothing depends on the migration having run.** Colours resolve
  WooCommerce's `image` / `color`; a term with neither stored falls back to
  the theme's old meta (`color_type`, `color_pattern_id`, `color_value`), then
  a colour guessed from the name (`Color_Data_Manager::resolve_swatch()`). The
  old meta is a rollback copy, never consulted once WooCommerce meta exists.
  Theme surfaces read that directly; WooCommerce's own storefront reads get it
  through `default_term_metadata`. Guesses are never stored, so they follow
  renames and a cleared swatch stays cleared; admin screens see only what is
  stored.
- **Decorate, never inject — and only `pa_color`.** `Color_Block_Swatch_Manager`
  only adds attributes to WooCommerce's chips in the `attribute_pa_color`
  selector: the chip/swatch classes the stylesheet targets, `data-color-name`
  for the tooltip, and `--swatch-color` (on the button) for the selected ring.
  WooCommerce's chip script re-applies the chip `title` and the swatch
  `style` / `--no-color` class on hydration, so nothing depends on those.
- **Browser-rendered chips.** WooCommerce server-renders only the first 30
  options; the rest render on "+N more". The chip list carries
  `data-aa-color-swatches` (this attribute's swatch map, with
  `Swatch_Tooltips`' text via `aggressive_apparel_color_swatch_map`), and
  `color-swatch-chips.ts` decorates those chips the same way and drops every
  colour chip's `title` (the styled tooltip replaces it).
- **Shape only, at three classes.** WooCommerce marks a visual attribute's
  list `.is-style-swatch` and shapes its chips at two classes. The theme's
  chip and label rules go through `.wc-block-product-filter-chips` + the chip
  classes (three) so the look doesn't depend on stylesheet order, and set only
  structural properties (box, padding, border width, display) — chip colours
  stay WooCommerce's and the editor's.
- **Hex only.** WooCommerce stores hex; the migration converts `oklch()`
  (detected from the value, whatever `color_format` says) to the matching
  sRGB hex. The old meta is left as a rollback copy.
