# Saved site content that overrides the theme

Templates and template parts edited in the Site Editor are saved in the
database (`wp_template` / `wp_template_part` posts) and **override the theme's
files** of the same slug. Deploying the theme does not change them. This is
why the live header still says "Trends die. Attitude doesn't." although the
theme's `parts/header.html` no longer does.

Changing saved content is an editorial change made in WordPress, never by a
theme release. This page says what is saved, what to change,
and how.

## What is saved

Find the saved entries on any environment (read-only):

```bash
wp post list --post_type=wp_template,wp_template_part --post_status=any \
  --fields=ID,post_name,post_status,post_modified
```

Locally (Studio, 21 Nov 2026) this lists the entries below. Production very
likely has the same set; confirm with the command before editing.

| Slug                                      | Kind          | What differs from the theme file                                                                                         | Change it by     |
| ----------------------------------------- | ------------- | ------------------------------------------------------------------------------------------------------------------------ | ---------------- |
| `header`                                  | template part | Announcement ticker copy (below)                                                                                         | Editing in place |
| `footer`                                  | template part | Trust badge and newsletter copy (below)                                                                                  | Editing in place |
| `single-product`                          | template      | Adds the **PayPal smart buttons** block; lacks the theme's live free-shipping line under Add to cart                     | Editing in place |
| `home`                                    | template      | The old customised homepage (hero "CAMO + SKULLS", "For the Unruled"); **draft locally** so the editorial homepage shows | Reset (adopt)    |
| `checkout-header`, `mini-cart`            | template part | Saved, no copy issues found                                                                                              | Leave            |
| `simple-product-add-to-cart-with-options` | template part | Saved (two entries locally), no copy issues found                                                                        | Leave            |
| `page-no-title`                           | template      | Saved, no copy issues found                                                                                              | Leave            |

**Edit in place, don't reset**, wherever the saved version holds something
the theme file doesn't: resetting `single-product` would silently remove the
PayPal smart buttons, and resetting `header` / `footer` would drop any
merchant edits to menus and links.

## Copy to change

Following [`brand-voice.md`](brand-voice.md):

| Where                             | Saved text now                                        | Change to                                                                                                              |
| --------------------------------- | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Header ticker                     | LATEST DROP LIVE NOW                                  | EST. 2010 (approved)                                                                                                   |
| Header ticker                     | TRENDS DIE. ATTITUDE DOESN'T.                         | Remove the item and its icon (a retired phrase; "BLACK. AGAIN." was declined as a permanent announcement)              |
| Header ticker                     | Free-shipping message block                           | Keep: its amount comes from the shipping settings                                                                      |
| Footer trust badge                | INDEPENDENT BY DESIGN / Premium, confident, authentic | EST. 2010 / Independent label (approved)                                                                               |
| Footer newsletter                 | NEW RELEASES. RESTOCKS. EXCLUSIVES.                   | Keep until confirmed against what subscribers actually receive                                                         |
| Footer newsletter                 | NO SPAM. JUST WHAT'S WORTH WEARING.                   | Keep                                                                                                                   |
| Footer badge                      | WRONG OR DAMAGED? / We'll make it right.              | Keep (approved, within the published returns policy)                                                                   |
| Footer badge                      | FREE SHIPPING OVER $75                                | Keep: $75 is the intended minimum. Confirm WooCommerce → Shipping on production says $75 (local sample data says $150) |
| Single product, under Add to cart | (nothing)                                             | Insert the **Free Shipping Message** block (live "$X away from free shipping")                                         |
| Home                              | Old customised homepage                               | Reset to the theme (adopts the 12-section editorial homepage), or insert the Editorial Homepage pattern                |

The theme's own `parts/header.html` and `parts/footer.html` already carry the
new defaults, so a fresh install or a reset part shows them.

## Layout to change

| Where                               | Saved setting now                                 | Change to                                                                                     |
| ----------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| Single product, Related products    | Product Collection **Columns 5**                  | Columns **4** (four related products then fill the row instead of leaving a fifth slot empty) |
| Single product, the group around it | Padding top and bottom only                       | Add left and right padding **Gutter**, so the first card doesn't touch the screen edge        |
| Single product, wishlist heart      | On its own row under Add to cart, with a 32px gap | Leave until the summary column is reviewed with production products                           |

The theme's `templates/single-product.html` already has 4 columns and the
gutter, so a reset would fix both, but it would also remove the PayPal smart
buttons: edit in place.

## Procedure

`bin/site-content/reconcile.php` makes the layout and copy changes above
**in place** with WP-CLI. It finds each saved entry by slug (IDs differ
between sites), never resets one, and refuses to write a template whose
PayPal smart buttons or Add to cart block count would change. It is a dry run
until given `apply`, writes a backup of every entry before changing it, and a
second run reports "already reconciled".

1. **Dry run** and read what it would change:

    ```bash
    wp eval-file wp-content/themes/aggressive-apparel/bin/site-content/reconcile.php copy
    ```

2. **Apply** the approved groups. Layout (single product: four related
   columns, gutter padding, the free-shipping line after the purchase
   buttons) always runs; header and footer copy only with `copy`; the
   homepage reset only with `adopt-home` (moves the saved home template to
   the trash, so the editorial homepage shows):

    ```bash
    wp eval-file …/reconcile.php apply            # layout only
    wp eval-file …/reconcile.php apply copy       # + approved copy
    wp eval-file …/reconcile.php apply adopt-home # + editorial homepage
    ```

    Backups land in `./site-content-backup-<time>/<slug>-<ID>.html`. The
    Site Editor also keeps revisions (template ⋮ → Revisions).

3. **Verify** with the storefront audit (below) and by eye: header ticker and
   footer on three pages, a product page (free-shipping line, PayPal buttons
   still present, Add to cart works), cart and checkout totals.

**Rollback:** `wp post update <ID> site-content-backup-<time>/<slug>-<ID>.html`;
for the homepage, `wp post update <ID> --post_status=publish`.

Editing by hand instead: Site Editor → Templates → Single Product, select the
related products collection (Columns 4) and its group (padding left/right
Gutter), insert **Free Shipping Message** after the PayPal buttons, save; the
header and footer copy is retyped in Patterns → Template parts.

## After a release

Publishing a release is the deploy: each site's theme updater
(`Core\Theme_Updates`) installs it on its next check, so there is no
separate install step. The update replaces the theme's files only. Once it
lands:

1. **Saved content:** make the changes above in WordPress, in the Site
   Editor or with `reconcile.php` where WP-CLI is available. Until the saved
   Home template is reset, the old homepage keeps overriding the new one.
   Then set the campaign photos: Patterns → Template parts → **Homepage
   campaign image**, Replace with an approved campaign photo, drag the focal
   point onto the figure, add alt text, put the content position (and the
   overlay's side) on the photo's open side, Save; tick _Homepage cover_ on
   the garment it shows. Check phones in the editor's mobile preview; if one
   crop can't serve both, add a second Cover below the first for phones.
   **Featured drop campaign image** and **Defiance Division campaign image**
   work the same way once approved photography exists. Until a photo is
   set, the hero is the slogan alone and the collections keep their bands.

2. **Audit** every page type from the live catalog (Store API discovery, no
   fixtures) at 375 / 768 / 1440 / 1920 in light and dark: HTTP errors,
   sideways scroll, broken or placeholder images, script errors, WCAG 2.2 AA
   (axe), PayPal smart buttons on product pages, and with `--cart` a real add
   to cart through to the checkout page (never submitted):

    ```bash
    node bin/audit/storefront.mjs https://<site> --cart
    ```

    Full-page screenshots and `report.md` land in `./storefront-audit-<time>/`.
    The script exits 1 on any failure. Locally, where PayPal is inactive, add
    `--no-paypal`.

3. **By eye**, from the screenshots: photo crops, the homepage sections that
   depend on merchandise, wishlist, and the product summary column.

What production merchandise changes, and what to look at:

- **Product photos are designed square** (2000 × 2000). Cards and category
  tiles are square and show the whole image; the product gallery frame
  follows the main photo.
- **No photo is on the homepage until it is approved.** The catalog's
  on-figure photos only appear once approved under Media Library → Homepage
  use (see the merchant controls in `design-system.md`). Until then the
  photo-led sections fall back: Women's shows copy and products without a
  photo, and
  the lookbook is omitted below three approved photos. Shop by line lists
  every stocked standard line until categories are ticked "Show in Shop by
  line".
- **Defiance Division exists** (three hats), so section 09 renders as a title
  row over a three-product grid.
- Check every page type at 375 / 768 / 1440 / 1920, light and dark: home,
  shop, a category, a simple and a variable product, cart, checkout (to the
  payment step only), account, search, wishlist.
