#!/usr/bin/env bash
#
# Post-release asset check, run after `semantic-release` in the release job.
#
# A flaky GitHub upload (502 "Error creating policy") can publish a release with
# the tag, the commit, and the ZIP all in place but the SHA-256 sidecar missing.
# That failure is worse than it looks: Core\Theme_Updates requires a
# `<zip>.sha256` asset and returns early without one, so every existing install
# silently stops being offered the update. semantic-release cannot recover on a
# re-run either — the tag already exists, so it never reaches the upload again.
#
# This re-uploads whatever is missing and fails loudly if it still is, so a
# broken release surfaces as a red build instead of a silent no-op.
#
# Usage: bin/release/verify-assets.sh
#
# Runs from the repo root, after prepare.sh has produced the versioned assets.
# Requires `gh` (preinstalled on GitHub runners) with GH_TOKEN in the env.
set -euo pipefail

SLUG="aggressive-apparel"

VERSION="$(node -p "require('./package.json').version")"
ZIP="${SLUG}-${VERSION}.zip"
TAG="v${VERSION}"

# prepare.sh only builds the versioned ZIP when a release is actually being cut.
# Its absence means this run published nothing (no releasable commits) — the
# version in package.json is a previous release we must not touch.
if [[ ! -f "${ZIP}" ]]; then
	echo "No release prepared in this run — nothing to verify."
	exit 0
fi

echo "=== Verifying assets on ${TAG} ==="

# Upload anything the release is missing. --clobber keeps this idempotent, so a
# re-run repairs a partial upload rather than erroring on a name collision.
for asset in "${ZIP}" "${ZIP}.sha256"; do
	if [[ ! -f "${asset}" ]]; then
		echo "❌ Expected local asset '${asset}' not found in $(pwd)" >&2
		exit 1
	fi

	if gh release view "${TAG}" --json assets \
		--jq '.assets[].name' | grep -qxF "${asset}"; then
		echo "✅ ${asset} already attached"
		continue
	fi

	echo "⚠️  ${asset} missing from ${TAG} — uploading…"
	gh release upload "${TAG}" "${asset}" --clobber
done

# Re-read from the API rather than trusting the upload calls above: a silent
# partial failure here is exactly the bug this script exists to catch.
attached="$(gh release view "${TAG}" --json assets --jq '.assets[].name')"
missing=0

for asset in "${ZIP}" "${ZIP}.sha256"; do
	if ! grep -qxF "${asset}" <<<"${attached}"; then
		echo "❌ ${asset} is still missing from ${TAG}" >&2
		missing=1
	fi
done

if [[ "${missing}" -ne 0 ]]; then
	echo "" >&2
	echo "Release ${TAG} is published but incomplete. Without the .sha256" >&2
	echo "sidecar the theme updater will not offer this version to any site." >&2
	echo "Attach it manually, then re-run this job:" >&2
	echo "  gh release upload ${TAG} ${ZIP}.sha256" >&2
	exit 1
fi

echo "✅ ${TAG} has both release assets"
