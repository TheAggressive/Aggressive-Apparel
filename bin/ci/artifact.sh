#!/usr/bin/env bash

# Install and exercise the distributable ZIP in a WordPress environment that has
# no source-tree theme mapping. This proves the artifact customers receive can be
# installed and activated on its own.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
CONFIG_DIR="${SCRIPT_DIR}/artifact"
WP_ENV_EXECUTABLE="${REPO_ROOT}/node_modules/.bin/wp-env"
PLAYWRIGHT_EXECUTABLE="${REPO_ROOT}/node_modules/.bin/playwright"
ARTIFACT_HOME="${REPO_ROOT}/.cache/ci/wp-env-artifact"
ARTIFACT_FILES="${REPO_ROOT}/.cache/ci/artifact-files"
release_version="${AA_RELEASE_VERSION:-}"

if [[ -n "${release_version}" ]]; then
	package_name="aggressive-apparel-${release_version}.zip"
else
	package_name="aggressive-apparel.zip"
fi
package_path="${REPO_ROOT}/${package_name}"

if [[ ! -f "${package_path}" ]]; then
	echo "Artifact acceptance expected ${package_name} in the repository root." >&2
	exit 1
fi

if [[ ! -x "${WP_ENV_EXECUTABLE}" || ! -x "${PLAYWRIGHT_EXECUTABLE}" ]]; then
	echo "wp-env or Playwright is not installed. Run pnpm install --frozen-lockfile." >&2
	exit 1
fi

# Read the version from the package, rather than trusting the workflow output.
expected_version="$({
	unzip -p "${package_path}" aggressive-apparel/style.css
} | sed -n 's/^Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' | head -n 1)"
if [[ -z "${expected_version}" ]]; then
	echo "Could not read the packaged style.css version." >&2
	exit 1
fi
if [[ -n "${release_version}" && "${expected_version}" != "${release_version}" ]]; then
	echo "Packaged version ${expected_version} does not match ${release_version}." >&2
	exit 1
fi

mkdir -p "${ARTIFACT_FILES}"
find "${ARTIFACT_FILES}" -mindepth 1 -maxdepth 1 -type f -name '*.zip' -delete
cp "${package_path}" "${ARTIFACT_FILES}/${package_name}"

artifact_wp_env() {
	(
		cd "${CONFIG_DIR}"
		WP_ENV_HOME="${ARTIFACT_HOME}" CI=true "${WP_ENV_EXECUTABLE}" "$@"
	)
}

cleanup() {
	if ! artifact_wp_env stop; then
		echo "Warning: artifact-acceptance containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

artifact_wp_env start
artifact_wp_env clean all --no-scripts
artifact_wp_env run cli wp plugin activate woocommerce
artifact_wp_env run cli wp theme install \
	"/var/www/html/wp-content/aa-artifacts/${package_name}" --activate --force

actual_version="$(artifact_wp_env run cli wp theme get aggressive-apparel --field=version | tail -n 1)"
if [[ "${actual_version}" != "${expected_version}" ]]; then
	echo "Installed version ${actual_version} does not match ${expected_version}." >&2
	exit 1
fi

cd "${REPO_ROOT}"
CI=1 \
	WP_BASE_URL=http://localhost:9940 \
	WP_ENV_CONFIG_DIR=bin/ci/artifact \
	WP_ENV_HOME="${ARTIFACT_HOME}" \
	"${PLAYWRIGHT_EXECUTABLE}" test tests/e2e/artifact-smoke.spec.ts

# The single-quoted body must expand $log inside the wp-env container, not here.
# shellcheck disable=SC2016
artifact_wp_env run cli bash -c '
	log=/var/www/html/wp-content/debug.log
	if [[ -f "$log" ]] && grep -E "PHP (Fatal error|Parse error)" "$log"; then
		echo "Fatal PHP error found in artifact smoke-test log." >&2
		exit 1
	fi
'

echo "Artifact acceptance passed for aggressive-apparel ${expected_version}."
