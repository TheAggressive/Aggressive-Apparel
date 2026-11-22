# Saved site content that overrides the theme

Templates and template parts edited in the Site Editor are saved in the
database (`wp_template` / `wp_template_part` posts) and **override the theme's
files** of the same slug. Deploying the theme does not change them. This is
why the live header still says "Trends die. Attitude doesn't." although the
theme's `parts/header.html` no longer does.

Changing saved content is an editorial change made in WordPress, on staging
first, never by a theme deploy. This page says what is saved, what to change,
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

| Where                             | Saved text now                                        | Change to                                                                                               |
| --------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Header ticker                     | LATEST DROP LIVE NOW                                  | EST. 2010 (or name the current product: "NEW: SKULL CAMO HOODIES")                                      |
| Header ticker                     | TRENDS DIE. ATTITUDE DOESN'T.                         | BLACK. AGAIN.                                                                                           |
| Header ticker                     | Free-shipping message block                           | Keep: its amount comes from the shipping settings                                                       |
| Footer trust badge                | INDEPENDENT BY DESIGN / Premium, confident, authentic | EST. 2010 / Independent label                                                                           |
| Footer newsletter                 | NEW RELEASES. RESTOCKS. EXCLUSIVES.                   | NEW RELEASES. RESTOCKS. NOTHING ELSE.                                                                   |
| Footer newsletter                 | NO SPAM. JUST WHAT'S WORTH WEARING.                   | Keep                                                                                                    |
| Footer badge                      | WRONG OR DAMAGED? / We'll make it right.              | Keep only if the returns policy says so                                                                 |
| Single product, under Add to cart | (nothing)                                             | Insert the **Free Shipping Message** block (live "$X away from free shipping")                          |
| Home                              | Old customised homepage                               | Reset to the theme (adopts the 12-section editorial homepage), or insert the Editorial Homepage pattern |

The theme's own `parts/header.html` and `parts/footer.html` already carry the
new defaults, so a fresh install or a reset part shows them.

## Layout to change

| Where                               | Saved setting now                                 | Change to                                                                                     |
| ----------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| Single product, Related products    | Product Collection **Columns 5**                  | Columns **4** (four related products then fill the row instead of leaving a fifth slot empty) |
| Single product, the group around it | Padding top and bottom only                       | Add left and right padding **Gutter**, so the first card doesn't touch the screen edge        |
| Single product, wishlist heart      | On its own row under Add to cart, with a 32px gap | Leave until the summary column is reviewed on staging with production products                |

The theme's `templates/single-product.html` already has 4 columns and the
gutter, so a reset would fix both, but it would also remove the PayPal smart
buttons: edit in place.

## Procedure (staging, then production)

1. **Back up** each entry you will touch:

    ```bash
    wp post get <ID> --field=post_content > backup-<slug>.html
    ```

    The Site Editor also keeps revisions (template ⋮ → Revisions).

2. **Edit in place** in Site Editor → Patterns → Template parts → Header /
   Footer: select the ticker or footer text and retype it. For the product
   page: Site Editor → Templates → Single Product, select Add to cart with
   Options, insert **Free Shipping Message** after it, save. Keep the PayPal
   buttons block where it is.

3. **Adopt the homepage** (only when the editorial homepage is approved):
   Site Editor → Templates → Blog Home → ⋮ → **Reset**. This discards the
   saved homepage; the backup from step 1 restores it if needed.

4. **Verify** on staging: header ticker and footer on three pages, a product
   page (free-shipping line, PayPal buttons still present, Add to cart
   works), cart and checkout totals, light and dark modes, phone width.

5. **Repeat on production** after approval. Template content does not travel
   with a database-less theme deploy, so repeat steps 1–4 there (or apply the
   approved staging content with `wp post update <ID> backup-<slug>.html`
   after checking the IDs match).

**Rollback:** Site Editor revisions, or
`wp post update <ID> backup-<slug>.html` from step 1.

## Final review with production merchandise

The visual review of the finished design needs real products and photos.
Local development uses WooCommerce sample data on purpose, so the review runs
on **staging**: a copy of the production database and uploads, the
`feat/shopping-experience` theme branch deployed there, and payments in test
mode. Nothing is written to production.

What production merchandise changes, and what to look at:

- **Product photos are designed square** (2000 × 2000). Cards and category
  tiles are square and show the whole image; the product gallery frame
  follows the main photo.
- **There is no portrait photography** in the catalog. The photo-led homepage
  sections fall back to their type and grid layouts until campaign or
  lifestyle photos are added: the hero becomes the type-only slogan, Women's
  shows copy and products without a photo, and the lookbook is omitted.
  Setting the hero tee shot (or any campaign photo) in the editor restores
  the photo layouts.
- **Defiance Division exists** (three hats), so section 09 renders as a title
  row over a three-product grid.
- Check every page type at 375 / 768 / 1440 / 1920, light and dark: home,
  shop, a category, a simple and a variable product, cart, checkout (to the
  payment step only), account, search, wishlist.
