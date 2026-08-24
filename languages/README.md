# Theme translations

Text domain: `aggressive-apparel`  
Domain Path: `/languages`

## Who does what

| Role             | Action                                                                                 |
| ---------------- | -------------------------------------------------------------------------------------- |
| Developers       | Wrap UI strings in `__()` / `@wordpress/i18n`; run `pnpm i18n:pot` when strings change |
| MT + you         | CI fills `.po` drafts; you review the PR (never edit `.mo` / `.json` by hand)          |
| Release CI       | Runs `pnpm i18n:compile` before the theme ZIP                                          |
| Deploy / runtime | WordPress only **loads** compiled catalogs for the site language                       |

`pnpm build` is asset-only and does **not** run i18n.

## Commands

```bash
pnpm i18n:pot                 # Regenerate aggressive-apparel.pot from source
pnpm i18n:locale -- fr_FR     # Scaffold a new locale .po from the pot
pnpm i18n:sync                # Merge pot into every .po (fuzzy-safe)
pnpm i18n:compile             # Build .mo + Jed .json for classic scripts
pnpm i18n:status              # Coverage table (optional: --fail-under=80)
pnpm i18n:check               # CI gate: pot drift + PO validity + translators lint
pnpm i18n:translate           # Sync + MT empty/fuzzy (DeepL; MyMemory fallback)
pnpm i18n                     # pot → sync → compile → status
```

Local commands use WordPress Studio's WP-CLI. CI uses its isolated wp-env CLI;
both require the `i18n` command package.

Catalog validation additionally requires **gettext** (`msgfmt`) — `apt install gettext`,
`brew install gettext`. This is a hard requirement on purpose. It used to fall back to
`wp i18n make-mo`, which reports success on an unterminated `msgid` and on a
`msgid`/`msgstr` placeholder mismatch alike, so the gate passed unconditionally in CI.
A missing tool now fails loudly instead of quietly validating nothing.

`AA_I18N_PO_VALIDATOR` selects the mode: `auto` (default — `msgfmt -c`) or `skip`
(explicit, announces itself). `bin/ci/i18n.sh` uses `skip` for the in-container half of
the gate, because the wp-env cli image is Alpine and ships no `msgfmt`, then validates
catalogs on the host where it does exist.

**You do not need Poedit.** Machine translation fills `.po` files; you only review the GitHub PR.

## Happy path (new locale) — no manual typing

1. `pnpm i18n:locale -- fr_FR` and commit the empty `.po`
2. (Recommended) Add GitHub secret `DEEPL_AUTH_KEY` — without it MT falls back to MyMemory, which is noticeably rougher
3. Push a pot change (or run **Actions → 🌐 i18n MT Drafts**)
4. Merge the draft PR after a quick skim of cart / nav / shipping
5. Release compiles catalogs (`pnpm i18n:compile`); set **Site Language** in WordPress

## Automated MT drafts (DeepL default, PR-only)

Default mode is `auto`:

| Order        | Provider     | When                                                      |
| ------------ | ------------ | --------------------------------------------------------- |
| 1 (default)  | **DeepL**    | Whenever `DEEPL_AUTH_KEY` is set                          |
| 2 (fallback) | **MyMemory** | If DeepL fails (bad key, quota, outage), or no key is set |

DeepL leads on quality because MyMemory is a translation-memory _aggregator_: it
returns whole-segment matches from unrelated corpora, so short UI labels come
back with punctuation and phrasing the source never had (a bare
`Measurements in inches` returned as `Le misure sono rappresentate in pollici.`).
Always skim MT output regardless of provider — every filled entry is tagged
`#, aa-mt` with a `review before release` comment.

Override with `I18N_MT_PROVIDER`:

| Value              | Behavior                                                      |
| ------------------ | ------------------------------------------------------------- |
| `auto` _(default)_ | DeepL first, MyMemory fallback; MyMemory-only with no key     |
| `mymemory`         | MyMemory first, DeepL only if MyMemory fails and a key exists |
| `deepl`            | DeepL only — hard-fails without `DEEPL_AUTH_KEY`, no fallback |

| Secret           | Purpose                                                        |
| ---------------- | -------------------------------------------------------------- |
| `DEEPL_AUTH_KEY` | Enables DeepL (primary in `auto`); free-tier keys end in `:fx` |
| `I18N_MT_EMAIL`  | Optional MyMemory daily-quota bump (fallback path)             |

**Local secrets (gitignored):**

```bash
cp .env.example .env.local   # already gitignored (.env / .env.local)
# Put DEEPL_AUTH_KEY=… in .env.local — never commit it
pnpm i18n:translate -- --locale=fr_FR --limit=50
I18N_MT_PROVIDER=deepl pnpm i18n:translate -- --locale=fr_FR   # DeepL-only
```

Shell / CI env still wins over `.env.local`. For Actions, set the `DEEPL_AUTH_KEY` repo secret.

Only **empty** or **fuzzy** strings are filled. Entries get an `aa-mt` flag.

**CI:** [`.github/workflows/i18n-translate.yml`](../.github/workflows/i18n-translate.yml)

- On pot push to `main`, or **workflow_dispatch**
- Opens a **PR** — never pushes translations to `main`
- No-ops until a locale `.po` exists

## When translations reach users

Translations are **not** their own release. The MT PR merges as `chore(i18n)`,
so it does not bump the version — and the release pipeline skips lint + the
wp-env test suite on a translations-only merge (its `changes` gate sees nothing
outside `languages/`), so that merge is cheap. The reviewed `.po` simply lands
on `main`.

The shipped `.mo` is compiled fresh at **release** time (`i18n:compile` in the
`package` job) from whatever `.po` is on `main`. So merged translations ride
the **next** `feat`/`fix` release automatically — no extra version bump.

**Trade-off:** a feature's new strings show in English for translated locales
until that next release. To ship a release **already translated** (a big
feature, a launch, a new locale), fill + review the strings on the feature
branch _before_ merging, so the `.po` ships with the feature in one release:

```bash
pnpm i18n:translate            # fill empty/fuzzy for all locales
# skim the aa-mt entries: placeholders (%s/%d), brand terms, register
git add languages/*.po         # commit alongside the feature
```

Do **not** move MT into `pnpm build` / the release job — that would ship
unreviewed machine output straight to customers. The review gate (PR or
pre-merge skim) is deliberate.

## The compiled `.mo` drops the domain prefix — on purpose

`wp i18n make-mo` names its output `aggressive-apparel-de_DE.mo`. That is the
convention for `wp-content/languages/themes/`, and it is the wrong one for a
catalog the theme ships itself. `compile.sh` renames it to `de_DE.mo`.

`_load_textdomain_just_in_time()` chooses the filename from where the
registered path points:

```php
if ( str_starts_with( $path, $template_directory ) || … ) {
    $mofile = "{$path}{$locale}.mo";            // de_DE.mo
} else {
    $mofile = "{$path}{$domain}-{$locale}.mo";  // aggressive-apparel-de_DE.mo
}
```

There is no fallback — it returns `load_textdomain()` on that one path.
`Theme_Support` registers `get_template_directory() . '/languages'`, so only
the first branch is ever taken.

This shipped broken. All four locales were compiled, packaged and never
loaded, and nothing reported it: since WordPress 6.7
`load_theme_textdomain()` only records the path and returns `true`
unconditionally, so a wrong filename looks exactly like success while every
string falls through to English. The POT was current, the catalogs were valid
and the placeholders matched the whole time.

`tests/Integration/TestTranslationLoading.php` now asserts on `__()` output
per locale. Verify translations that way — never from the return value of
`load_theme_textdomain()`.

The JSON catalogs **keep** the prefix: `_load_script_textdomain_from_src()`
builds `{$domain}-{$locale}-{$md5}.json` with no equivalent branch, which is
why script translations were unaffected.

## Runtime notes

- **PHP + Interactivity modules:** gettext via `load_theme_textdomain` and PHP-seeded `i18n` bags (script modules cannot use `wp_set_script_translations`).
- **Classic block/admin JS:** Jed JSON from `i18n:compile` + `Asset_Loader::set_script_translations()`.
- Site Editor customizations of patterns/templates become **content** and are not re-applied from theme language packs after you edit them in the editor.

## Files

| File                                 | Purpose                                                  |
| ------------------------------------ | -------------------------------------------------------- |
| `aggressive-apparel.pot`             | Source catalog (committed; keep in sync with code)       |
| `aggressive-apparel-<locale>.po`     | Locale drafts / reviewed strings (committed)             |
| `<locale>.mo`                        | Compiled PHP catalog (**gitignored**; `i18n:compile`)    |
| `aggressive-apparel-<locale>-*.json` | Classic JS translations (**gitignored**; `i18n:compile`) |

Never hand-edit `.mo` / `.json`. MT never runs at deploy — only via `i18n:translate` / the draft PR workflow. Deploy only **loads** compiled catalogs from the release package.
