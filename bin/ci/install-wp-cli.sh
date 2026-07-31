#!/usr/bin/env bash
#
# Install a pinned WP-CLI release after verifying its official SHA-256 digest.
# Keep the version, URL, and checksum together so CI upgrades are explicit and
# reviewable instead of following the moving gh-pages PHAR.
set -euo pipefail

readonly WP_CLI_VERSION='2.12.0'
readonly WP_CLI_SHA256='ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c'
readonly WP_CLI_URL="https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"
readonly WP_CLI_INSTALL_PATH="${WP_CLI_INSTALL_PATH:-/usr/local/bin/wp}"

if [[ -f "${WP_CLI_INSTALL_PATH}" ]]; then
	existing_sha256="$(sha256sum "${WP_CLI_INSTALL_PATH}" | awk '{print $1}')"
	if [[ "${existing_sha256}" == "${WP_CLI_SHA256}" ]]; then
		echo "WP-CLI ${WP_CLI_VERSION} is already installed and verified."
		if [[ "${WP_CLI_SKIP_INFO:-0}" != "1" ]]; then
			"${WP_CLI_INSTALL_PATH}" --info --allow-root
		fi
		exit 0
	fi
fi

wp_cli_temp="$(mktemp "${RUNNER_TEMP:-/tmp}/wp-cli-${WP_CLI_VERSION}.XXXXXX.phar")"
readonly wp_cli_temp

cleanup() {
	rm -f "${wp_cli_temp}"
}
trap cleanup EXIT

echo "Downloading WP-CLI ${WP_CLI_VERSION} from its official release…"
curl \
	--fail \
	--silent \
	--show-error \
	--location \
	--retry 3 \
	--retry-all-errors \
	--output "${wp_cli_temp}" \
	"${WP_CLI_URL}"

actual_sha256="$(sha256sum "${wp_cli_temp}" | awk '{print $1}')"
readonly actual_sha256

if [[ "${actual_sha256}" != "${WP_CLI_SHA256}" ]]; then
	echo "WP-CLI checksum verification failed." >&2
	echo "Expected: ${WP_CLI_SHA256}" >&2
	echo "Actual:   ${actual_sha256}" >&2
	exit 1
fi

echo "WP-CLI checksum verified."
install_dir="$(dirname "${WP_CLI_INSTALL_PATH}")"
readonly install_dir

if [[ ! -d "${install_dir}" ]]; then
	mkdir -p "${install_dir}"
fi

if [[ -w "${install_dir}" ]]; then
	install -m 0755 "${wp_cli_temp}" "${WP_CLI_INSTALL_PATH}"
else
	sudo install -m 0755 "${wp_cli_temp}" "${WP_CLI_INSTALL_PATH}"
fi

if [[ "${WP_CLI_SKIP_INFO:-0}" != "1" ]]; then
	"${WP_CLI_INSTALL_PATH}" --info --allow-root
fi
