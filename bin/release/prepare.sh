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
# Usage: bin/release/prepare.sh <version>
#   e.g. bin/release/prepare.sh 1.166.0
#
# Runs from the repo root (semantic-release's cwd), where the build job has
# placed aggressive-apparel.zip.
set -euo pipefail

VERSION="${1:?Usage: prepare.sh <version> (release version is required)}"

SLUG="aggressive-apparel"
SRC_ZIP="${SLUG}.zip"
OUT_ZIP="${SLUG}-${VERSION}.zip"

# Rewrites the "Version: X.Y.Z" line of a WordPress style.css header.
STYLE_SED="s/Version: [0-9]\\+\\.[0-9]\\+\\.[0-9]\\+/Version: ${VERSION}/g"

echo "=== Preparing release ${VERSION} ==="

# --- 1. Repackage + checksum the release ZIP ---------------------------------
# The build artifact must exist; a release without its ZIP asset is broken, so
# fail loudly rather than silently continue (the previous inline command did).
if [[ ! -f "${SRC_ZIP}" ]]; then
	echo "❌ Expected build artifact '${SRC_ZIP}' not found in $(pwd)" >&2
	ls -la >&2
	exit 1
fi

echo "Stamping version into ${SRC_ZIP} → ${OUT_ZIP}…"
rm -rf temp_zip
mkdir -p temp_zip
(cd temp_zip && unzip -q "../${SRC_ZIP}")
sed -i "${STYLE_SED}" "temp_zip/${SLUG}/style.css"
(cd temp_zip && zip -qr "../${OUT_ZIP}" .)
rm -rf temp_zip
rm -f "${SRC_ZIP}"
sha256sum "${OUT_ZIP}" > "${OUT_ZIP}.sha256"
echo "✅ ${OUT_ZIP} built and checksummed"

# --- 2. Bump version in committed sources ------------------------------------
# @semantic-release/git commits style.css + package.json (see .releaserc.json).
echo "Updating version in style.css and package.json…"
sed -i "${STYLE_SED}" style.css
npm version "${VERSION}" --no-git-tag-version
echo "Version updated to ${VERSION}"
