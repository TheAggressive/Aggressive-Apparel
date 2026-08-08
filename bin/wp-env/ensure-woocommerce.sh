#!/usr/bin/env bash

# Seed WooCommerce when missing and keep the installed version active.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXPECTED_VERSION="11.0.0"
ARCHIVE_URL="https://downloads.wordpress.org/plugin/woocommerce.11.0.0.zip"

if [[ "${1:-}" == "--container" ]]; then
	plugin_file="/var/www/html/wp-content/plugins/woocommerce/woocommerce.php"
	installed_version="$(wp plugin get woocommerce --field=version 2>/dev/null || true)"

	if [[ -f "${plugin_file}" && -n "${installed_version}" ]]; then
		if ! wp plugin is-active woocommerce; then
			echo "wp-env: activating WooCommerce ${installed_version}..."
			wp plugin activate woocommerce
		fi
	else
		echo "wp-env: WooCommerce is missing or incomplete."
		echo "wp-env: installing the ${EXPECTED_VERSION} seed release..."
		wp plugin install "${ARCHIVE_URL}" --force --activate
	fi

	installed_version="$(wp plugin get woocommerce --field=version 2>/dev/null || true)"
	if [[ ! -f "${plugin_file}" || -z "${installed_version}" ]]; then
		echo "wp-env: WooCommerce installation failed validation." >&2
		exit 1
	fi

	if ! wp plugin is-active woocommerce; then
		echo "wp-env: WooCommerce ${installed_version} is installed but could not be activated." >&2
		exit 1
	fi

	echo "wp-env: WooCommerce ${installed_version} is installed and active."
	exit 0
fi

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

aa_require_development_site

for container in cli tests-cli; do
	aa_wp_env run "${container}" \
		--env-cwd="${AA_WP_ENV_THEME_CWD}" \
		-- bash bin/wp-env/ensure-woocommerce.sh --container
done
