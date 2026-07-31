#!/usr/bin/env bash
#
# Release "prepare" step, invoked by @semantic-release/exec (.releaserc.json).
#
# 1. Repackages the built theme ZIP with the release version stamped into
#    style.css, renames it to the versioned asset name, and writes a SHA-256
#    sidecar (both are uploaded as GitHub Release assets).
# 2. Bumps the version in the committed style.css + package.json, which
#    @semantic-release/git then commits back to the release branch.
#
# Every stamp is asserted rather than assumed. `sed` exits 0 whether or not it
# matched anything, so an unverified substitution fails silently — and a ZIP
# carrying a stale version is not a cosmetic bug: Core\Theme_Updates compares
# the installed style.css header against the release tag, so every site would
# update, still read the old version, and be offered the same update forever.
#
# Usage: bin/release/prepare.sh <version>
#   e.g. bin/release/prepare.sh 1.166.0
#
# Runs from the repo root (semantic-release's cwd), where the package lane has
# placed aggressive-apparel.zip.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "${SCRIPT_DIR}/lib.sh"

VERSION="${1:?Usage: prepare.sh <version> (release version is required)}"

SRC_ZIP="${AA_THEME_SLUG}.zip"
OUT_ZIP="${AA_THEME_SLUG}-${VERSION}.zip"

# Matches the full version token rather than a fixed three-part number, so
# prereleases (1.2.0-rc.1) and build metadata stamp correctly instead of
# silently not matching.
STYLE_SED="s/^Version:[[:space:]].*$/Version: ${VERSION}/"

echo "=== Preparing release ${VERSION} ==="

# --- 1. Repackage + checksum the release ZIP ---------------------------------
if [[ ! -f "${SRC_ZIP}" ]]; then
	echo "❌ Expected build artifact '${SRC_ZIP}' not found in $(pwd)" >&2
	ls -la >&2
	exit 1
fi

echo "Stamping version into ${SRC_ZIP} → ${OUT_ZIP}…"
rm -rf temp_zip
mkdir -p temp_zip
(cd temp_zip && unzip -q "../${SRC_ZIP}")

STAGED_STYLE="temp_zip/${AA_THEME_SLUG}/style.css"
if [[ ! -f "${STAGED_STYLE}" ]]; then
	echo "❌ ${STAGED_STYLE} is missing — the package is malformed." >&2
	exit 1
fi

sed -i "${STYLE_SED}" "${STAGED_STYLE}"

staged_version="$(aa_release_style_version "${STAGED_STYLE}")"
if [[ "${staged_version}" != "${VERSION}" ]]; then
	echo "❌ Version stamp did not apply to the packaged style.css." >&2
	echo "   Expected '${VERSION}', found '${staged_version:-<none>}'." >&2
	echo "   The 'Version:' header format has changed — fix STYLE_SED." >&2
	exit 1
fi

# Zip the theme directory by name rather than `.`, so the archive can never
# gain a stray top-level entry that would install as a second theme.
(cd temp_zip && zip -qrX "../${OUT_ZIP}" "${AA_THEME_SLUG}")
rm -rf temp_zip
rm -f "${SRC_ZIP}"

# Re-verify the artifact that will actually be uploaded, not the staging tree
# it was built from. This re-runs the full contents/exclusions check with the
# release version pinned, so a stamp or repack that corrupted the package fails
# the release instead of shipping.
bash "${SCRIPT_DIR}/verify-package.sh" "${OUT_ZIP}" "${VERSION}"

sha256sum "${OUT_ZIP}" >"${OUT_ZIP}.sha256"
echo "✅ ${OUT_ZIP} built, verified and checksummed"

# --- 2. Bump version in committed sources ------------------------------------
# @semantic-release/git commits style.css + package.json (see .releaserc.json).
echo "Updating version in style.css and package.json…"
sed -i "${STYLE_SED}" style.css

committed_version="$(aa_release_style_version style.css)"
if [[ "${committed_version}" != "${VERSION}" ]]; then
	echo "❌ Version stamp did not apply to the committed style.css." >&2
	echo "   Expected '${VERSION}', found '${committed_version:-<none>}'." >&2
	exit 1
fi

npm version "${VERSION}" --no-git-tag-version --allow-same-version >/dev/null

package_version="$(node -p "require('./package.json').version")"
if [[ "${package_version}" != "${VERSION}" ]]; then
	echo "❌ package.json version is '${package_version}', expected '${VERSION}'." >&2
	exit 1
fi

echo "✅ Version updated to ${VERSION} in style.css and package.json"
