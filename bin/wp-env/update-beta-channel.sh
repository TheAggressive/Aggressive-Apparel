#!/usr/bin/env bash

set -euo pipefail

if [[ "${WP_ENV_SKIP_BETA_UPDATE:-0}" == "1" ]]; then
	echo "wp-env: skipping the WordPress Beta/RC update."
	exit 0
fi

echo "wp-env: selecting the WordPress Bleeding Edge Beta/RC Only channel..."

pnpm wp-env run cli bash -c '
	set -euo pipefail

	wp option update wp_beta_tester \
		"{\"channel\":\"development\",\"stream-option\":\"beta\"}" \
		--format=json
	wp transient delete update_core --network

	update_count=$(wp core check-update --format=count)
	if [[ "$update_count" == "0" ]]; then
		echo "WordPress is already on the latest Beta/RC."
		exit 0
	fi

	backup_archive=$(mktemp /tmp/wp-content-before-core-update.XXXXXX.tar)

	tar \
		--exclude="wp-content/themes/aggressive-apparel" \
		--exclude="wp-content/plugins/woocommerce" \
		--exclude="wp-content/plugins/wordpress-beta-tester" \
		--exclude="wp-content/mu-plugins" \
		-C /var/www/html \
		-cf "$backup_archive" \
		wp-content

	restore_wp_content() {
		mkdir -p /var/www/html/wp-content
		tar -C /var/www/html -xf "$backup_archive"
	}

	trap restore_wp_content EXIT
	wp core update
	restore_wp_content
	trap - EXIT

	wp core update-db
'

echo "wp-env: development site is on the latest available WordPress Beta/RC."
