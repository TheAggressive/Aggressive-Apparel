#!/usr/bin/env bash
#
# Canonical release-packaging lane.
#
# Actions and `pnpm qa:ci` both run exactly this script, so the artifact that gets
# published is the same artifact a developer can build and inspect locally
# before pushing. Previously this logic lived inline in the release workflow's
# `package` job and had no local execution path at all, which made it the
# largest drift surface in the pipeline — and the place two shipping defects
# went unnoticed.
#
# Language catalogs are compiled inside the pinned parity container rather than
# against host PHP, so the .mo/.json artifacts are byte-identical regardless of
# what the developer happens to have installed.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
TOOL_DIR="${REPO_ROOT}/.cache/ci"
WP_CLI_PATH="${TOOL_DIR}/wp"
THEME_CWD="wp-content/themes/aggressive-apparel"

mkdir -p "${TOOL_DIR}"
WP_CLI_INSTALL_PATH="${WP_CLI_PATH}" \
	WP_CLI_SKIP_INFO=1 \
	bash "${SCRIPT_DIR}/install-wp-cli.sh"

cleanup() {
	if ! bash "${SCRIPT_DIR}/stop-wp-env.sh"; then
		echo "Warning: CI parity containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

bash "${SCRIPT_DIR}/reset-wp-env.sh"

# Compile .po → .mo + Jed JSON with the pinned WP-CLI inside the parity image.
bash "${SCRIPT_DIR}/wp-env.sh" run cli \
	--env-cwd="${THEME_CWD}" \
	-- bash -c 'PATH="$PWD/bin/ci:$PATH" bash bin/i18n/compile.sh'

cd "${REPO_ROOT}"
release_version="${AA_RELEASE_VERSION:-}"
if [[ -n "${release_version}" ]]; then
	package_name="aggressive-apparel-${release_version}.zip"
else
	package_name="aggressive-apparel.zip"
fi

bash bin/release/package.sh "${release_version}"
bash bin/release/verify-package.sh "${package_name}" "${release_version}"

# Rebuild once and require an identical digest. This is intentionally in the
# canonical package lane so reproducibility is enforced locally and in Actions.
first_digest="$(sha256sum "${package_name}" | awk '{print $1}')"
bash bin/release/package.sh "${release_version}"
second_digest="$(sha256sum "${package_name}" | awk '{print $1}')"
if [[ "${first_digest}" != "${second_digest}" ]]; then
	echo "Package is not reproducible: ${first_digest} != ${second_digest}" >&2
	exit 1
fi

bash bin/release/verify-package.sh "${package_name}" "${release_version}"

if [[ -n "${release_version}" ]]; then
	sha256sum "${package_name}" >"${package_name}.sha256"
	sha256sum --check "${package_name}.sha256"
fi
