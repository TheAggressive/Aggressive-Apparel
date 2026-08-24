# Release runbook

The version is calculated automatically from the conventional commits merged to
`master`, but **cutting a release is a deliberate act**. Merging runs the full
quality pipeline and stops there; publishing happens only when someone runs the
pipeline manually with the `publish` input set. The production environment
accepts deployments only from `master`, and no operator constructs or edits a
release artifact manually.

## Cutting a release

```bash
gh workflow run "CI/CD Pipeline" --ref master -f publish=true
```

Or use *Actions → CI/CD Pipeline → Run workflow* and tick **publish**.

Everything merged since the last tag ships as one release. If those commits
contain nothing release-worthy, planning reports no release and the run stops
without publishing — running this when nothing is pending is harmless.

## Release path

| Stage          | Control                                                                                                                                                |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Plan           | `semantic-release` dry run calculates the next version. Runs only when `publish` was requested.                                                       |
| Build and test | Frontend, PHP, browser, i18n, and dependency gates run independently.                                                                                  |
| Package        | `ci:package` stamps a staged tree, builds it twice, requires identical hashes, and verifies the ZIP.                                                   |
| Accept         | `ci:artifact` installs the ZIP into clean WordPress without a source mapping, activates it, and runs browser smoke tests.                              |
| Draft          | `semantic-release` tags the reviewed commit and uploads the accepted assets to a draft GitHub Release. It does not commit generated files to `master`. |
| Attest         | GitHub records build provenance for the final ZIP.                                                                                                     |
| Publish        | `verify-assets.sh` repairs the draft, downloads both assets, checks byte equality, checksum, package structure and provenance, then publishes it.      |
| Sync           | `version-sync` stamps the shipped version into `style.css` and opens `chore/version-sync` for review. Merge it to keep the checkout honest.            |

The release invariant is:

> The ZIP accepted in clean WordPress is the same ZIP checksummed, attested,
> remotely verified, and published.

Draft and prerelease entries are ignored by `Theme_Update_Release_Repository`,
so a failed upload or verification never becomes visible to installed sites.

## Local rehearsal

```bash
pnpm qa:ci
```

The full rehearsal builds an unversioned `aggressive-apparel.zip` and runs the
same artifact-acceptance lane. To rehearse a specific release version:

```bash
pnpm ci:build
AA_RELEASE_VERSION=1.2.3 pnpm ci:package
AA_RELEASE_VERSION=1.2.3 pnpm ci:artifact
```

`ci:package` consumes the existing `build/`; it does not compile source assets.

## Artifact controls

The distribution allowlist lives in `bin/release/lib.sh`. Packaging occurs in a
temporary directory and never edits the checkout. Archive order, timestamps and
modes are normalized, and the canonical package lane requires two consecutive
builds to produce the same SHA-256.

The checksum and provenance answer different questions:

- The `.sha256` sidecar detects corruption and is enforced by every theme update.
- The GitHub attestation proves the ZIP came from an authorized Actions workflow.

`verify-assets.sh` enforces both before changing a draft to a public release.

## Recover an interrupted release

If the tag or draft exists but the publish job failed, use **Actions → Release
Recovery** and enter the version without its `v` prefix. The recovery workflow:

1. Checks out the existing tag.
2. Rebuilds the normalized package.
3. Repeats clean-install artifact acceptance.
4. Creates fresh provenance.
5. Repairs and verifies the existing draft before publishing it.

Do not delete or recreate the tag. Semantic-release derives future versions from
tag history, and a deleted tag can cause version reuse.

If no draft exists, investigate why semantic-release failed before draft
creation. The recovery workflow intentionally fails rather than inventing a
release body or tag outside semantic-release.

## Stop distribution of a bad release

```bash
gh release edit v1.2.3 --prerelease
gh release list --limit 5
```

The updater skips the prerelease and resolves the previous stable release on its
next cache refresh. Sites that already installed the bad version are not
downgraded automatically; ship a forward fix.

Restore distribution only after the incident is resolved:

```bash
gh release edit v1.2.3 --latest
```

## Branch and production controls

The committed branch rules are in `.github/rulesets/`. They require PR-only
squash history, current CI and security checks, resolved threads, and signed
commits. They are not applied automatically; follow the ruleset README to apply
and audit them.

The `production` environment is restricted to `master`. With one maintainer,
branch rules require zero approvals instead of creating a bypass or an
impossible self-review gate. When a second active maintainer exists, require
code-owner and last-push approval, then add that maintainer as a production
reviewer and prevent self-review.

## Version ownership

Git tags and GitHub Releases are the source of truth for published versions.
`style.css` in the checkout is not modified by the release bot; the package lane
stamps the planned version only into the staged artifact and asserts it after
remote download. GitHub release notes replace the previously committed generated
changelog for each release.

## Changing supported PHP

Update these declarations together:

- `style.css` (`Requires PHP`)
- `composer.json` (`require.php` and `config.platform.php`)
- `phpstan.neon` (`phpVersion`)
- `bin/ci/.wp-env.json`
- `bin/ci/artifact/.wp-env.json`

Then update `composer.lock`. The contracts require every declaration and all
release environments to agree. Newer PHP versions remain covered by the weekly
forward-compatibility workflow rather than changing the release floor.
