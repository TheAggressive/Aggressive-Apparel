# Versioning & release mechanics

Semantic-release wiring, what is and is not auto-updated, the `version-sync`
pull request, and the self-updater. Day-to-day flow lives in
[`workflow.md`](workflow.md); the release procedure in
[`release-runbook.md`](release-runbook.md).

---

## Commit Convention

Uses [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New feature
- `fix:` Bug fix
- `refactor:` Code refactoring
- `docs:` Documentation
- `test:` Tests
- `chore:` Maintenance

## Git Hooks

Husky splits checks across two hooks so commits stay fast and the heavy
gate runs before code leaves the machine:

- **`pre-commit`** (fast, every commit): `lint-staged` runs Prettier,
  Stylelint, and ESLint autofixes only on staged files.
- **`commit-msg`**: commitlint validation (Conventional Commits).
- **`pre-push`** (heavy, before push): `pnpm qa:fast` runs the Docker-free
  frontend/build/static-analysis checks plus native PHPUnit unit coverage.
  Use `pnpm qa:ci` explicitly before a release for every canonical container
  lane. Neither local command touches the Studio database with PHPUnit.

  **These are the same commands Actions runs**, and `bin/ci/contracts.mjs`
  fails the build if the two lists ever diverge in either direction — a
  workflow job may only invoke a canonical lane, never inline shell. Adding a
  step to CI without making it runnable locally is a build failure, by design.

  `ci:package` builds the distributable ZIP from the allowlist in
  `bin/release/lib.sh` and verifies its contents, so the release artifact is
  validated before anything is pushed. See
  [`release-runbook.md`](release-runbook.md).

  Local `pnpm test:e2e` builds before running, so manual browser runs always
  validate current source instead of a stale `build/`. It writes fixtures only
  to a Studio site that has explicitly opted in with `.aa-e2e-site`.

## Semantic Release

Automated versioning via semantic-release (`.releaserc.json`). It runs three
plugins only — `commit-analyzer`, `release-notes-generator`, `@semantic-release/github`
— so it **tags the reviewed commit and writes no release commit**. The
`changelog` / `exec` / `git` plugins were removed in `384a111` so the publishing
bot needs no branch-protection bypass; see `.github/rulesets/README.md`.

**Releasing is manual on purpose.** Merging to `master` runs the full pipeline
and stops; publishing requires a deliberate run:

```bash
gh workflow run "CI/CD Pipeline" --ref master -f publish=true
```

Everything merged since the last tag ships as one release, so several fixes reach
customer sites as a single update instead of one each.

**Auto-updated on release:** the packaged theme ZIP. `bin/release/package.sh`
stamps `AA_RELEASE_VERSION` into the **staged** `style.css` at package time and
`bin/release/verify-package.sh` asserts it; nothing in the checkout is mutated.
The `version-sync` job then opens `chore/version-sync` to put that version back
into the tracked `style.css`; policy recognizes and auto-merges that exact
machine PR. Change the tracked version only via
`bash bin/release/sync-version.sh <version>`, never by editing the header.

**Not auto-updated:**

| File                             | State                                                   |
| -------------------------------- | ------------------------------------------------------- |
| `style.css` (`Version:`)         | Synced back by the `version-sync` PR, enforced by a guard |
| `package.json` (`version`)       | Permanently `0.0.0-development` — private, never on npm  |
| `CHANGELOG.md`                   | Frozen at 1.181.4; GitHub Release notes superseded it    |
| `languages/*.po` `Project-Id-Version` | Stale (1.164.0). Nothing reads it; leave it alone  |
| `README.md` / `CLAUDE.md` / docs | Manual, in the same PR as the change                     |
| Per-block `block.json` `version` | Independent of theme releases                            |

The released version lives in the **git tags**. Read it from `git tag` or the
Releases page; never hardcode it in docs. Inventory counts (blocks, patterns,
features) belong in the same PR that changes them.

Why it matters: `AGGRESSIVE_APPAREL_VERSION` is a cache-invalidation key in
`Rendered_Product_Cache`, the Product Collection style fingerprint, and five
asset enqueues, so a header that never moves is a set of caches that never rotate
in development. WordPress also reads `style.css` as the authoritative theme
version.

The sync pull request **merges itself**: `version-sync` enables auto-merge on it,
and the classifier recognises it by branch AND author together
(`chore/version-sync` + `aggressive-ci[bot]`) and skips every lane. Only the
trivial required checks run, so a release costs no runner time for a header.

Enforcement is the release run itself — the summary gate requires the
`version-sync` job to succeed whenever a release is planned, so a sync that
fails to open fails the release loudly. There is deliberately no separate drift
guard; it would only fire during the couple of minutes before auto-merge lands,
where it produces false failures on unrelated pull requests.

`style.css` is the ONLY version synced. `@since` tags are historical (they record
when an API appeared, not the current release) and per-block `block.json`
versions track blocks, not the theme. The catalog `Project-Id-Version` headers
are left alone: nothing reads them, `aa_i18n_normalize_pot` strips that header
before the drift comparison, and touching the POT would trigger the machine
translation workflow on every release.

**Why not release-please:** it would put the bump in the release commit itself,
but its generated commits are not signature-verified
(googleapis/release-please-action#1124, open), and the `release-branches` ruleset
requires signed commits with no bypass actors. `create-pull-request` with
`sign-commits: true` creates commits through the GitHub API, which GitHub signs —
so the sync PR satisfies the ruleset without weakening it.

**Bumping `style.css` does NOT break `ci:i18n`.** `aa_i18n_normalize_pot`
(`bin/i18n/lib.sh`) deliberately strips `Project-Id-Version` before the drift
comparison, precisely so the catalog may lag the theme version. What *does* break
that lane is far easier to trip: the POT records **source line numbers**, so any
line-count change to a file containing a translatable string invalidates it, with
no other symptom. `ci:i18n` is deliberately out of the `pre-push` gate (see
`bin/ci/verify-fast.sh`), so it surfaces in CI. The fix is always `pnpm i18n:pot`.

The self-updater (`Core\Theme_Updates`) refuses to run on a checkout or a
local/development install; staging and production keep it. A theme update clears
the theme directory and unpacks the allowlisted ZIP over it, which would destroy
a working copy. Detection is layered on purpose: `WP_ENVIRONMENT_TYPE` is the
WordPress-native signal but defaults to `production` when unset, while the `.git`
marker needs no configuration but only exists on a checkout. Tested with
`file_exists`, not `is_dir` — a worktree or submodule stores `.git` as a file.
`Theme_Updates::should_enable()` is the pure policy function; override the result
with `aggressive_apparel_enable_theme_updates`.
