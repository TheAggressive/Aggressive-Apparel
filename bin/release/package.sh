#!/usr/bin/env bash
#
# Build the distributable theme ZIP from the allowlist in bin/release/lib.sh.
#
# This stages into a temporary directory and NEVER mutates the working tree.
# The previous implementation lived inline in the release workflow and built the
# package by `rm -rf`-ing development files out of the checkout, which made it
# unrunnable locally (it would have deleted src/, tests/ and node_modules from a
# developer's repository) and therefore impossible to verify before a release
# was already being cut. Staging makes the exact same command safe everywhere,
# which is what keeps local and CI from drifting.
#
# Usage: bin/release/package.sh
#
# Requires: build/ (produced by `pnpm ci:build`) and compiled language catalogs
# (produced by bin/i18n/compile.sh). Both are checked before anything is staged.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "${SCRIPT_DIR}/lib.sh"

REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${REPO_ROOT}"

OUT_ZIP="${REPO_ROOT}/${AA_THEME_SLUG}.zip"

echo "=== Packaging ${AA_THEME_SLUG} ==="

# --- Preconditions -----------------------------------------------------------
if [[ ! -d "${REPO_ROOT}/build" ]]; then
	echo "❌ build/ is missing. Run: pnpm ci:build" >&2
	exit 1
fi

missing_inputs=0
for entry in "${AA_PACKAGE_INCLUDE[@]}"; do
	if [[ ! -e "${REPO_ROOT}/${entry}" ]]; then
		echo "❌ Allowlisted path '${entry}' does not exist in the repository." >&2
		missing_inputs=1
	fi
done
if [[ "${missing_inputs}" -ne 0 ]]; then
	echo "" >&2
	echo "Either the working tree is incomplete or bin/release/lib.sh lists a" >&2
	echo "path that no longer exists. Fix one or the other — do not ship a" >&2
	echo "package that silently dropped a shipping path." >&2
	exit 1
fi

# --- Stage -------------------------------------------------------------------
STAGE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/aa-package.XXXXXX")"
cleanup() {
	rm -rf "${STAGE_ROOT}"
}
trap cleanup EXIT

THEME_DIR="${STAGE_ROOT}/${AA_THEME_SLUG}"
mkdir -p "${THEME_DIR}"

echo "Staging $(printf '%s' "${#AA_PACKAGE_INCLUDE[@]}") allowlisted paths…"
for entry in "${AA_PACKAGE_INCLUDE[@]}"; do
	# -a preserves the tree; the trailing slash-free form copies the directory
	# itself so nested layout is retained.
	cp -a "${REPO_ROOT}/${entry}" "${THEME_DIR}/"
done

echo "Pruning development files from shipping directories…"
for prune in "${AA_PACKAGE_PRUNE[@]}"; do
	if [[ -e "${THEME_DIR}/${prune}" ]]; then
		rm -rf "${THEME_DIR:?}/${prune}"
		echo "  pruned ${prune}"
	fi
done

# Compiled test bundles, wherever webpack emitted them. This is a pattern, not
# a named path, because a named path is what failed: AA_PACKAGE_PRUNE listed
# build/scripts/__tests__, and a test added under build/scripts/admin/__tests__
# sailed straight past it into the package — the same way compiled Jest output
# shipped in real releases before the allowlist existed. Any depth, any name.
while IFS= read -r -d '' tests_dir; do
	rm -rf "${tests_dir}"
	echo "  pruned ${tests_dir#"${THEME_DIR}/"}"
done < <(find "${THEME_DIR}" -type d -name '__tests__' -print0)

# Strip repository and OS metadata that has no meaning inside a distributed
# theme. Handled generically rather than as named prune entries so a new
# .gitignore anywhere in a shipping directory cannot start leaking.
find "${THEME_DIR}" \
	\( -name '.gitignore' -o -name '.gitattributes' -o -name '.DS_Store' \
	-o -name 'Thumbs.db' -o -name '*.map' \) -delete

# --- Zip ---------------------------------------------------------------------
rm -f "${OUT_ZIP}"
(
	cd "${STAGE_ROOT}"
	# -X drops extra file attributes (uid/gid, extended attrs) so the archive
	# is not machine-specific.
	zip -qrX "${OUT_ZIP}" "${AA_THEME_SLUG}"
)

# One known, quoted filename - there is no glob for `find` to handle better.
# shellcheck disable=SC2012
echo "✅ Package created: $(ls -lh "${OUT_ZIP}" | awk '{print $5}') at ${OUT_ZIP}"
