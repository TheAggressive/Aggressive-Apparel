# Release runbook

How a release is cut, and what to do when one goes wrong.

Releases are **automatic**: merging a conventional commit to `main`/`master`
publishes a GitHub Release, and `Core\Theme_Updates` offers it to every install
that has the theme. There is no manual publish step, so the recovery paths below
matter more than they would for a manually shipped theme.

## How a release is produced

| Stage          | Where                                                  | What it does                                                               |
| -------------- | ------------------------------------------------------ | -------------------------------------------------------------------------- |
| Plan           | `release-plan` job → `bin/release/plan.mjs`            | semantic-release dry run; decides whether a release is due                 |
| Build + verify | `build` / `test` / `e2e` jobs                          | Compiles assets, runs PHP + browser suites                                 |
| Package        | `package` job → `pnpm ci:package`                      | Compiles catalogs, builds the ZIP from an allowlist, verifies its contents |
| Publish        | `release` job → `pnpm semantic-release` → `prepare.sh` | Stamps the version, re-verifies, checksums, tags, uploads assets           |
| Post-check     | `verify-assets.sh` + `attest-build-provenance`         | Re-uploads any missing asset, fails loudly, attests provenance             |

The distributable ZIP is defined by an **allowlist** in
[`bin/release/lib.sh`](../bin/release/lib.sh). Nothing ships unless it is listed
there. To change what ships, edit that file — never the workflow.

### Rehearse the whole thing locally

```bash
pnpm qa                          # every required lane, incl. packaging
pnpm ci:build && pnpm ci:package # package + verify the ZIP on its own
```

`ci:package` compiles catalogs, then packages and verifies **whatever `build/`
already contains** — it does not compile assets. Actions relies on that: the
`package` job downloads the `build` job's artifact rather than rebuilding it.
Run `pnpm ci:build` first when invoking it standalone, or you will package a
stale tree.

`pnpm qa` runs the identical commands Actions runs. `bin/ci/contracts.mjs`
fails the build if the two ever diverge, so a green local run is a real
prediction of CI rather than an approximation.

## Rollback: pulling a bad release

**This is the fastest lever and it is already supported by the updater.**
`Theme_Update_Release_Repository::select_latest_stable_release()` skips any
release marked `draft` or `prerelease`, so flagging a release as a prerelease
immediately stops it being offered, and sites fall back to the previous stable
release.

```bash
# Stop a bad release reaching any further installs (takes effect on the next
# update check; the transient cache is short-lived).
gh release edit v1.2.3 --prerelease

# Confirm the updater now resolves the previous version.
gh release list --limit 5
```

Notes:

- **Do not delete the release or the tag.** semantic-release derives the next
  version from tag history; deleting a tag makes it recompute a version that has
  already been published. Marking it prerelease is non-destructive and reversible
  (`gh release edit v1.2.3 --latest` restores it).
- Sites that already updated are **not** rolled back automatically. Ship a fix
  forward — that is what the next release is for.
- Then land the actual fix as a normal `fix:` commit. The next release supersedes
  the bad one.

## Failure modes and what they mean

### `verify-assets.sh` fails

The release was published but an asset is missing. Without the `.sha256`
sidecar, `Theme_Update_Package_Verifier` refuses the package and **no site is
offered the update** — a silent stall, not a visible error.

**Re-running the release job does not repair this.** The tag already exists, so
semantic-release will not publish again, `prepare.sh` never re-runs, and
`verify-assets.sh` exits 0 early because the versioned ZIP is not present in the
workspace. Rebuild the assets locally and upload them:

```bash
git checkout v1.2.3
pnpm install --frozen-lockfile
pnpm ci:build                       # ci:package will not compile for you
pnpm ci:package                     # -> aggressive-apparel.zip
bash bin/release/prepare.sh 1.2.3   # -> aggressive-apparel-1.2.3.zip(+.sha256)

gh release upload v1.2.3 \
  aggressive-apparel-1.2.3.zip.sha256 --clobber
```

`prepare.sh` re-verifies the package and asserts the stamped version, so a
hand-built replacement is held to the same standard as the original. It also
rewrites `style.css`/`package.json` in your working tree — discard those local
edits afterwards (`git checkout style.css package.json`).

### Package verification fails

`bin/release/verify-package.sh` rejected the artifact — a required file is
missing, a forbidden path leaked in, a `.po` has no compiled `.mo`, or the
stamped version does not match the release. **Nothing was published.** Reproduce
locally with `pnpm ci:package`; the failure output names the exact path.

### A release run was cancelled

Release-branch runs are deliberately **not** cancellable
(`cancel-in-progress` is true only for pull requests), because semantic-release
publishes non-atomically. If a release run is cancelled anyway — for example
manually — check the release for missing assets and repair with the
`verify-assets.sh` steps above. semantic-release cannot recover on a re-run,
because the tag already exists.

### Release planning fails

`bin/release/plan.mjs` exits non-zero, which blocks packaging and release by
design. Usually a token or history problem, not a code problem. `fetch-depth: 0`
is required for semantic-release to see tag history.

## Version stamping

The version lives in `style.css` and is the value WordPress compares against the
release tag. `bin/release/prepare.sh` stamps it and **asserts the result** in
three places — the staged package, the committed `style.css`, and `package.json`.

If a stamp ever silently failed, every site would update, still read the old
version, and be offered the same update forever. That is why the assertions
exist; do not remove them, and do not change `STYLE_SED` without re-running
`bin/release/prepare.sh` against a scratch copy.

## Changing the supported PHP version

The floor appears as six values across five files, all asserted together by
`bin/ci/contracts.mjs`:

- `style.css` → `Requires PHP:`
- `composer.json` → `require.php` **and** `config.platform.php`
- `phpstan.neon` → `phpVersion`
- `bin/ci/.wp-env.json` → `phpVersion` (the gate)
- `.wp-env.json` → `phpVersion` (development)

Change all of them in the same commit, run `composer update --lock`, and let the
contract confirm they agree.

Forward compatibility with newer PHP is covered by
`.github/workflows/php-forward-compatibility.yml`, which runs the same PHP lane
against versions above the floor on a weekly schedule, in an isolated wp-env home
and on separate ports. Reproduce a failure locally with:

```bash
pnpm ci:build
pnpm ci:php:forward 8.4
```

It is deliberately **not** a release gate: a deprecation on a version the theme
does not claim to support is information, not a defect. `bin/ci/contracts.mjs`
asserts the job exists and tests above the floor, because holding development and
the gate on the same PHP is only defensible while something else looks ahead.
