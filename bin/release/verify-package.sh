#!/usr/bin/env bash
#
# Assert the built theme ZIP is complete, clean, and correctly versioned.
#
# The previous packaging step only checked that forbidden paths were ABSENT.
# That is the weaker half of the problem: an over-eager clean step, a partially
# uploaded build/ artifact, or a webpack config change can produce a ZIP that
# is missing load-bearing files, and an exclusion-only check passes it happily.
# Because Core\Theme_Updates auto-pushes releases to every install, a package
# that is missing style.css or build/blocks-manifest.php is a site-wide outage
# shipped by a green pipeline.
#
# Usage: bin/release/verify-package.sh [zip-path] [expected-version]
#
#   zip-path         defaults to <repo>/aggressive-apparel.zip
#   expected-version optional; when given, the style.css header inside the ZIP
#                    must match it exactly.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "${SCRIPT_DIR}/lib.sh"

REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

ZIP_PATH="${1:-${REPO_ROOT}/${AA_THEME_SLUG}.zip}"
EXPECTED_VERSION="${2:-}"

if [[ ! -f "${ZIP_PATH}" ]]; then
	echo "❌ Package '${ZIP_PATH}' not found." >&2
	exit 1
fi

echo "=== Verifying $(basename "${ZIP_PATH}") ==="

# `unzip -Z1` lists one entry per line with no header/footer noise, which is
# what makes the exact-match assertions below reliable.
ENTRIES="$(unzip -Z1 "${ZIP_PATH}")"
FAILED=0

fail() {
	echo "❌ $1" >&2
	FAILED=1
}

# --- 1. Every entry lives under the theme directory --------------------------
# WordPress derives the installed directory name from the archive's top level.
# A stray sibling entry installs a second bogus "theme".
while IFS= read -r entry; do
	[[ -n "${entry}" ]] || continue
	if [[ "${entry}" != "${AA_THEME_SLUG}/"* && "${entry}" != "${AA_THEME_SLUG}" ]]; then
		fail "Entry outside the theme directory: ${entry}"
	fi
done <<<"${ENTRIES}"

# --- 2. Required files are present -------------------------------------------
for required in "${AA_PACKAGE_REQUIRED[@]}"; do
	if ! grep -qxF "${AA_THEME_SLUG}/${required}" <<<"${ENTRIES}"; then
		fail "Required file missing from package: ${required}"
	fi
done

# --- 3. Required directories contain at least one FILE ------------------------
# Directory entries themselves end in "/", so they are excluded: a build that
# emitted only empty subdirectories would otherwise satisfy the very check meant
# to catch a partially-populated build/ tree.
for required_dir in "${AA_PACKAGE_REQUIRED_NONEMPTY[@]}"; do
	if ! grep -E "^${AA_THEME_SLUG}/${required_dir}/" <<<"${ENTRIES}" |
		grep -qv '/$'; then
		fail "Required directory contains no files: ${required_dir}/"
	fi
done

# --- 4. Forbidden paths are absent -------------------------------------------
for pattern in "${AA_PACKAGE_FORBIDDEN[@]}"; do
	matches="$(grep -E "${pattern}" <<<"${ENTRIES}" || true)"
	if [[ -n "${matches}" ]]; then
		fail "Forbidden path matching /${pattern}/ is in the package:"
		sed 's/^/     /' <<<"${matches}" >&2
	fi
done

# --- 5. Every .po has a compiled .mo -----------------------------------------
# A .po without its .mo means bin/i18n/compile.sh did not run, and the release
# ships untranslated for that locale while still advertising the catalog.
while IFS= read -r po_entry; do
	[[ -n "${po_entry}" ]] || continue
	mo_entry="${po_entry%.po}.mo"
	if ! grep -qxF "${mo_entry}" <<<"${ENTRIES}"; then
		fail "Locale catalog ${po_entry##*/} has no compiled ${mo_entry##*/}"
	fi
done <<<"$(grep -E "^${AA_THEME_SLUG}/languages/.+\.po$" <<<"${ENTRIES}" || true)"

# --- 6. style.css carries a well-formed, expected version ---------------------
# Core\Theme_Updates compares this header against the release tag. If it is
# stale, every site updates, still reads the old version, and is offered the
# same update forever.
STYLE_CSS="$(unzip -p "${ZIP_PATH}" "${AA_THEME_SLUG}/style.css" 2>/dev/null || true)"
if [[ -z "${STYLE_CSS}" ]]; then
	fail "style.css could not be read from the package"
else
	PACKAGED_VERSION="$(
		sed -n 's/^Version:[[:space:]]*\([0-9][^[:space:]]*\).*$/\1/p' <<<"${STYLE_CSS}" | head -n 1
	)"

	if [[ -z "${PACKAGED_VERSION}" ]]; then
		fail "style.css in the package has no parseable 'Version:' header"
	elif [[ -n "${EXPECTED_VERSION}" && "${PACKAGED_VERSION}" != "${EXPECTED_VERSION}" ]]; then
		fail "Packaged version '${PACKAGED_VERSION}' does not match expected '${EXPECTED_VERSION}'"
	else
		echo "✅ Packaged version: ${PACKAGED_VERSION}"
	fi

	for header in 'Theme Name:' 'Requires at least:' 'Requires PHP:' 'Text Domain:'; do
		if ! grep -qF "${header}" <<<"${STYLE_CSS}"; then
			fail "style.css in the package is missing the '${header}' header"
		fi
	done
fi

if [[ "${FAILED}" -ne 0 ]]; then
	echo "" >&2
	echo "Package verification FAILED. This artifact must not be released." >&2
	exit 1
fi

echo "✅ Package contents, exclusions, catalogs and headers verified"
