#!/usr/bin/env bash

# Report the development environment's versions and fail on missing media.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${1:-}" == "--container" ]]; then
	core_version="$(wp core version)"
	php_version="$(php -r 'echo PHP_VERSION;')"
	site_url="$(wp option get siteurl)"
	active_theme="$(wp theme list --status=active --field=name | head -n 1)"
	woocommerce_version="$(wp plugin get woocommerce --field=version 2>/dev/null || echo "not installed")"
	beta_tester_status="$(wp plugin list --name=wordpress-beta-tester --field=status 2>/dev/null || true)"
	beta_tester_status="${beta_tester_status:-not installed}"
	beta_channel="$(wp option get wp_beta_tester --format=json 2>/dev/null || echo "not configured")"

	read -r attachment_count missing_count <<EOF
$(wp eval '
	$total   = 0;
	$missing = 0;
	$page    = 1;

	do {
		$query = new WP_Query(
			array(
				"post_type"      => "attachment",
				"post_status"    => "any",
				"fields"         => "ids",
				"posts_per_page" => 200,
				"paged"          => $page,
				"orderby"        => "ID",
				"order"          => "ASC",
			)
		);

		foreach ( $query->posts as $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! is_file( $file ) ) {
				++$missing;
			}
			++$total;
		}

		++$page;
	} while ( $page <= $query->max_num_pages );

	echo $total . " " . $missing;
')
EOF

	cat <<EOF
wp-env development health
  Site URL:              ${site_url}
  WordPress:             ${core_version}
  PHP:                   ${php_version}
  Active theme:          ${active_theme:-none}
  WooCommerce:           ${woocommerce_version}
  Beta Tester:           ${beta_tester_status}
  Beta channel:          ${beta_channel}
  Media attachments:     ${attachment_count}
  Missing media files:   ${missing_count}
EOF

	if [[ "${missing_count}" != "0" ]]; then
		echo "wp-env: ${missing_count} attachment file(s) are missing." >&2
		exit 1
	fi

	exit 0
fi

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

aa_require_development_site
aa_wp_env run cli \
	--env-cwd="${AA_WP_ENV_THEME_CWD}" \
	-- bash bin/wp-env/check.sh --container
