#!/usr/bin/env bash
#
# Install a pinned Composer release after verifying its official SHA-256 digest.
#
# Composer was the last unpinned toolchain input. The wp-env WordPress image
# ships whatever Composer it was built with (2.7.6 at time of writing) while
# developers typically have a much newer one, so `composer.lock` metadata and
# dependency resolution depended on who ran the command. Everything else in the
# parity lane is pinned — Node (bin/ci/node.sh), WP-CLI (bin/ci/install-wp-cli.sh),
# PHP, WordPress and WooCommerce (bin/ci/.wp-env.json) — so this closes the gap.
#
# Keep the version, URL, and checksum together so upgrades are explicit and
# reviewable. The digest is published at:
#   https://getcomposer.org/download/<version>/composer.phar.sha256sum
set -euo pipefail

readonly COMPOSER_VERSION='2.9.8'
readonly COMPOSER_SHA256='59b2c50e10cafa0d8efc19ede9a326d782f096c674a26baf98cf042ce23de890'
readonly COMPOSER_URL="https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
INSTALL_PATH="${COMPOSER_INSTALL_PATH:-${REPO_ROOT}/.cache/ci/composer.phar}"

if [[ -f "${INSTALL_PATH}" ]]; then
	existing_sha256="$(sha256sum "${INSTALL_PATH}" | awk '{print $1}')"
	if [[ "${existing_sha256}" == "${COMPOSER_SHA256}" ]]; then
		echo "Composer ${COMPOSER_VERSION} is already installed and verified."
		exit 0
	fi
fi

composer_temp="$(mktemp "${RUNNER_TEMP:-/tmp}/composer-${COMPOSER_VERSION}.XXXXXX.phar")"
readonly composer_temp

cleanup() {
	rm -f "${composer_temp}"
}
trap cleanup EXIT

echo "Downloading Composer ${COMPOSER_VERSION} from its official release…"
curl \
	--fail \
	--silent \
	--show-error \
	--location \
	--retry 3 \
	--retry-all-errors \
	--output "${composer_temp}" \
	"${COMPOSER_URL}"

actual_sha256="$(sha256sum "${composer_temp}" | awk '{print $1}')"
readonly actual_sha256

if [[ "${actual_sha256}" != "${COMPOSER_SHA256}" ]]; then
	echo "Composer checksum verification failed." >&2
	echo "Expected: ${COMPOSER_SHA256}" >&2
	echo "Actual:   ${actual_sha256}" >&2
	exit 1
fi

echo "Composer checksum verified."
mkdir -p "$(dirname "${INSTALL_PATH}")"
install -m 0755 "${composer_temp}" "${INSTALL_PATH}"
