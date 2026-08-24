#!/usr/bin/env bash
# Fetch pinned WordPress and WooCommerce sources for native PHPUnit runs.

set -euo pipefail

cd "$(dirname "$0")/../.."

WP_DIR="${AA_TESTS_WP_DIR:-.cache/local/wordpress}"
WP_CONFIG="bin/ci/.wp-env.json"
WP_URL="$(node -p "JSON.parse(require('fs').readFileSync('${WP_CONFIG}')).core")"
WOO_URL="$(node -p "JSON.parse(require('fs').readFileSync('${WP_CONFIG}')).env.tests.plugins[0]")"
WP_VERSION="${WP_URL##*wordpress-}"
WP_VERSION="${WP_VERSION%.zip}"
WOO_VERSION="${WOO_URL##*woocommerce.}"
WOO_VERSION="${WOO_VERSION%.zip}"

WP_CLI_INSTALL_PATH="$(pwd -P)/.cache/ci/wp" \
	WP_CLI_SKIP_INFO=1 \
	bash bin/ci/install-wp-cli.sh

wp_cli() {
	php -d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' \
		.cache/ci/wp --path="${WP_DIR}" "$@"
}

installed_version() {
	[ -f "${WP_DIR}/wp-includes/version.php" ] || return 1
	grep -m1 -oE "\\\$wp_version = '[^']+'" "${WP_DIR}/wp-includes/version.php" | cut -d"'" -f2
}

if [ "$(installed_version || true)" != "${WP_VERSION}" ]; then
	echo "wp-core: downloading WordPress ${WP_VERSION}"
	mkdir -p "${WP_DIR}"
	wp_cli core download --version="${WP_VERSION}" --skip-content --force
fi

wp_cli core verify-checksums >/dev/null

theme_link="${WP_DIR}/wp-content/themes/aggressive-apparel"
theme_root="$(pwd -P)"
mkdir -p "$(dirname "${theme_link}")"
if [ -e "${theme_link}" ] || [ -L "${theme_link}" ]; then
	if [ "$(realpath "${theme_link}" 2>/dev/null || true)" != "${theme_root}" ]; then
		echo "wp-core: refusing to replace ${theme_link}; it is not this checkout." >&2
		exit 1
	fi
else
	ln -s "${theme_root}" "${theme_link}"
fi

woo_file="${WP_DIR}/wp-content/plugins/woocommerce/woocommerce.php"
installed_woo="$(grep -m1 -E '^ \* Version:' "${woo_file}" 2>/dev/null | sed -E 's/^ \* Version:[[:space:]]*//' || true)"
if [ "${installed_woo}" != "${WOO_VERSION}" ]; then
	echo "wp-core: downloading WooCommerce ${WOO_VERSION}"
	archive=".cache/local/woocommerce-${WOO_VERSION}.zip"
	staging=".cache/local/woocommerce-${WOO_VERSION}.staging"
	mkdir -p ".cache/local" "${WP_DIR}/wp-content/plugins"
	curl --fail --location --silent --show-error "${WOO_URL}" --output "${archive}"
	rm -rf "${staging}"
	mkdir -p "${staging}"
	unzip -q "${archive}" -d "${staging}"
	rm -rf "${WP_DIR}/wp-content/plugins/woocommerce"
	mv "${staging}/woocommerce" "${WP_DIR}/wp-content/plugins/woocommerce"
	rmdir "${staging}"
fi

echo "wp-core: WordPress ${WP_VERSION}, WooCommerce ${WOO_VERSION}"
