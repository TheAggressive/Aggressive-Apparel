#!/usr/bin/env bash
#
# Guard: the tracked theme version must match the latest published release.
#
# The release stamps its version into the ZIP only, so nothing about publishing
# keeps style.css honest on its own. The version-sync job opens a pull request
# to do that, but automation can open a pull request and cannot make anyone
# merge it — delivery is best-effort by construction. This is the enforcement
# half: an unmerged sync fails the build instead of drifting quietly, which is
# how the header reached 1.181.4 while releases had climbed to 1.183.2.
#
# It is not cosmetic. WordPress reads style.css as the authoritative theme
# version, and AGGRESSIVE_APPAREL_VERSION is a cache-invalidation key in
# Rendered_Product_Cache, the Product Collection style fingerprint, and five
# asset enqueues.
#
# Fails closed. No reachable tags means the answer is unknown, not "fine": a
# shallow clone reports that it cannot verify rather than passing vacuously.
# The CI lane that runs this fetches tags for exactly that reason.
#
# Fix a failure with:  bash bin/release/sync-version.sh <version>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# shellcheck source=release/lib.sh
source "${SCRIPT_DIR}/release/lib.sh"

cd "${REPO_ROOT}"

if ! git rev-parse --git-dir > /dev/null 2>&1; then
	echo "❌ Not a git repository — cannot determine the released version." >&2
	exit 1
fi

# Sorted by version, not by date: a patch published after a later minor must
# not be mistaken for the newest release.
latest_tag="$(git tag --list 'v[0-9]*' --sort=-v:refname | head -n 1)"

if [[ -z "${latest_tag}" ]]; then
	echo "❌ No release tags are reachable, so the released version is unknown." >&2
	echo "   This check fails rather than passes on missing data." >&2
	echo "   Shallow clone? Run: git fetch --tags --force" >&2
	exit 1
fi

released="${latest_tag#v}"

if [[ ! "${released}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "❌ Latest tag '${latest_tag}' is not a bare semantic version." >&2
	exit 1
fi

tracked="$(aa_release_style_version "${REPO_ROOT}/style.css")"

if [[ -z "${tracked}" ]]; then
	echo "❌ style.css has no parseable 'Version:' header." >&2
	exit 1
fi

if [[ "${tracked}" != "${released}" ]]; then
	cat >&2 <<-MESSAGE
		❌ The tracked theme version is behind the latest release.

		     style.css: ${tracked}
		     released:  ${released}  (${latest_tag})

		   Merge the open chore/version-sync pull request, or run:

		     bash bin/release/sync-version.sh ${released}

		   Do not edit the header by hand — sync-version.sh is the one place
		   that writes it, and it verifies the stamp applied.
	MESSAGE
	exit 1
fi

echo "Version sync check passed (style.css and ${latest_tag} both ${released})."
