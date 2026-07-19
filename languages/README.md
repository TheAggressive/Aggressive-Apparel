# Theme translations

Text domain: `aggressive-apparel`  
Domain Path: `/languages`

## Who does what

| Role | Action |
|------|--------|
| Developers | Wrap UI strings in `__()` / `@wordpress/i18n`; run `pnpm i18n:pot` when strings change |
| MT + you | CI fills `.po` drafts; you review the PR (never edit `.mo` / `.json` by hand) |
| Release CI | Runs `pnpm i18n:compile` before the theme ZIP |
| Deploy / runtime | WordPress only **loads** compiled catalogs for the site language |

`pnpm build` is asset-only and does **not** run i18n.

## Commands

```bash
pnpm i18n:pot                 # Regenerate aggressive-apparel.pot from source
pnpm i18n:locale -- fr_FR     # Scaffold a new locale .po from the pot
pnpm i18n:sync                # Merge pot into every .po (fuzzy-safe)
pnpm i18n:compile             # Build .mo + Jed .json for classic scripts
pnpm i18n:status              # Coverage table (optional: --fail-under=80)
pnpm i18n:check               # CI gate: pot drift + PO validity + translators lint
pnpm i18n:translate           # Sync + MT empty/fuzzy (MyMemory; DeepL backup if key)
pnpm i18n                     # pot → sync → compile → status
```

Requires WP-CLI (`wp`) with the i18n package, **or** a running `wp-env` (scripts auto-detect).

**You do not need Poedit.** Machine translation fills `.po` files; you only review the GitHub PR.

## Happy path (new locale) — no manual typing

1. `pnpm i18n:locale -- fr_FR` and commit the empty `.po`
2. (Optional) Add GitHub secret `DEEPL_AUTH_KEY` for backup when MyMemory rate-limits
3. Push a pot change (or run **Actions → 🌐 i18n MT Drafts**)
4. Merge the draft PR after a quick skim of cart / nav / shipping
5. Release compiles catalogs (`pnpm i18n:compile`); set **Site Language** in WordPress

## Automated MT drafts (MyMemory default, PR-only)

| Order | Provider | When |
|-------|----------|------|
| 1 (default) | **MyMemory** | Always tried first — free, no key |
| 2 (backup) | **DeepL** | Only if MyMemory fails **and** `DEEPL_AUTH_KEY` is set |

| Secret | Purpose |
|--------|---------|
| `DEEPL_AUTH_KEY` | Optional backup (or force with `I18N_MT_PROVIDER=deepl`) |
| `I18N_MT_EMAIL` | Optional MyMemory daily-quota bump |

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

## Runtime notes

- **PHP + Interactivity modules:** gettext via `load_theme_textdomain` and PHP-seeded `i18n` bags (script modules cannot use `wp_set_script_translations`).
- **Classic block/admin JS:** Jed JSON from `i18n:compile` + `Asset_Loader::set_script_translations()`.
- Site Editor customizations of patterns/templates become **content** and are not re-applied from theme language packs after you edit them in the editor.

## Files

| File | Purpose |
|------|---------|
| `aggressive-apparel.pot` | Source catalog (committed; keep in sync with code) |
| `aggressive-apparel-<locale>.po` | Locale drafts / reviewed strings (committed) |
| `aggressive-apparel-<locale>.mo` | Compiled PHP catalog (**gitignored**; `i18n:compile`) |
| `aggressive-apparel-<locale>-*.json` | Classic JS translations (**gitignored**; `i18n:compile`) |

Never hand-edit `.mo` / `.json`. MT never runs at deploy — only via `i18n:translate` / the draft PR workflow. Deploy only **loads** compiled catalogs from the release package.
