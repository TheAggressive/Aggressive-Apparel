#!/usr/bin/env bash
#
# Write a released version into the tracked style.css header.
#
# The package step stamps the version into the staged tree only, so the checkout
# never learns what shipped and drifts further from every release. That matters
# beyond tidiness: WordPress reads style.css as the authoritative theme version,
# and AGGRESSIVE_APPAREL_VERSION is a cache-invalidation key in
# Rendered_Product_Cache, in the Product Collection style fingerprint, and in
# five asset enqueues.
#
# Only style.css is touched. languages/aggressive-apparel.pot also embeds the
# version in Project-Id-Version, but aa_i18n_normalize_pot (bin/i18n/lib.sh)
# strips that header before the drift comparison precisely so the catalog may
# lag — so regenerating it here would be churn, not correctness. What does break
# ci:i18n is a change in source LINE NUMBERS, which this script cannot cause.
#
# Idempotent: re-running with the version already in place exits 0 having
# written nothing, so the release workflow can call it unconditionally.
#
# Usage:
#   bash bin/release/sync-version.sh 1.2.3
#   AA_RELEASE_VERSION=1.2.3 bash bin/release/sync-version.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# shellcheck source=lib.sh
source "${SCRIPT_DIR}/lib.sh"

VERSION="${1:-${AA_RELEASE_VERSION:-}}"

if [[ -z "${VERSION}" ]]; then
	echo "❌ A version is required: bin/release/sync-version.sh <version>" >&2
	exit 2
fi

# The value reaches a sed replacement and a committed file header, so anything
# that is not a bare semantic version must fail here rather than be written.
if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "❌ Not a bare semantic version: '${VERSION}' (expected e.g. 1.2.3)." >&2
	exit 2
fi

STYLE_CSS="${REPO_ROOT}/style.css"
current="$(aa_release_style_version "${STYLE_CSS}")"

if [[ -z "${current}" ]]; then
	echo "❌ style.css has no parseable 'Version:' header." >&2
	exit 1
fi

if [[ "${current}" == "${VERSION}" ]]; then
	echo "style.css is already at ${VERSION}; nothing to sync."
	exit 0
fi

sed -i "s/^Version:[[:space:]].*$/Version: ${VERSION}/" "${STYLE_CSS}"

stamped="$(aa_release_style_version "${STYLE_CSS}")"
if [[ "${stamped}" != "${VERSION}" ]]; then
	echo "❌ Version stamp did not apply to style.css." >&2
	exit 1
fi

echo "✅ style.css: ${current} → ${VERSION}"
