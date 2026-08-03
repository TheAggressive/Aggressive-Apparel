#!/usr/bin/env bash
#
# Move the running wp-env site onto the WordPress Bleeding Edge Beta/RC channel.
#
# DESTRUCTIVE. `wp core update` replaces wp-content with the one from the
# WordPress package, so this backs wp-content up first and restores it after.
# Run it only against a disposable environment — the beta workflow's runner.
#
# This was previously wired into `.wp-env.json` as an afterStart hook, which
# meant every `pnpm env:start` anywhere ran it. It aborted at the mktemp call on
# every invocation, so nobody noticed until that bug was fixed; its first real
# execution replaced a development site's wp-content and its restore did not
# bring the uploads back. It is now an explicit step (`pnpm env:beta`), and the
# restore is verified rather than assumed: losing files fails the run loudly
# instead of leaving a site that merely looks fine.

set -euo pipefail

if [[ "${WP_ENV_SKIP_BETA_UPDATE:-0}" == "1" ]]; then
	echo "wp-env: skipping the WordPress Beta/RC update."
	exit 0
fi

echo "wp-env: selecting the WordPress Bleeding Edge Beta/RC Only channel..."

pnpm wp-env run cli bash -c '
	set -euo pipefail

	# Everything the WordPress package cannot supply, and therefore everything
	# the restore has to bring back. Bind-mounted paths are excluded from the
	# archive because Docker owns them; core update leaves them alone.
	count_wp_content() {
		find /var/www/html/wp-content \
			-path /var/www/html/wp-content/themes/aggressive-apparel -prune -o \
			-path /var/www/html/wp-content/plugins/woocommerce -prune -o \
			-path /var/www/html/wp-content/plugins/wordpress-beta-tester -prune -o \
			-path /var/www/html/wp-content/mu-plugins -prune -o \
			-type f -print 2>/dev/null | wc -l
	}

	# Installed here rather than in .wp-env.json. Listing it as a development
	# plugin put it on every developer machine, where — with this repository’s
	# cron loopback mu-plugin — WordPress quietly auto-updated the site to a
	# beta in the background. Core then no longer matched the pinned version, so
	# the next `wp-env start` re-provisioned /var/www/html and took wp-content,
	# uploads included, with it.
	wp plugin install wordpress-beta-tester --activate --force

	wp option update wp_beta_tester \
		"{\"channel\":\"development\",\"stream-option\":\"beta\"}" \
		--format=json
	wp transient delete update_core --network

	update_count=$(wp core check-update --format=count)
	if [[ "$update_count" == "0" ]]; then
		echo "WordPress is already on the latest Beta/RC."
		exit 0
	fi

	files_before=$(count_wp_content)
	echo "wp-content files before core update: ${files_before}"

	# No .tar suffix: the cli container is Alpine, and BusyBox mktemp requires
	# the template to END in X characters.
	backup_archive=$(mktemp /tmp/wp-content-before-core-update.XXXXXX)

	tar \
		--exclude="wp-content/themes/aggressive-apparel" \
		--exclude="wp-content/plugins/woocommerce" \
		--exclude="wp-content/plugins/wordpress-beta-tester" \
		--exclude="wp-content/mu-plugins" \
		-C /var/www/html \
		-cf "$backup_archive" \
		wp-content

	# An empty or truncated archive is indistinguishable from a good one at
	# restore time, and restoring from it silently destroys the site content.
	archived=$(tar -tf "$backup_archive" | grep -c "^wp-content/" || true)
	if [[ "$archived" -eq 0 ]]; then
		echo "wp-env: the wp-content archive is empty; refusing to run core update." >&2
		exit 1
	fi
	echo "Archived ${archived} wp-content entries."

	restore_wp_content() {
		mkdir -p /var/www/html/wp-content
		tar -C /var/www/html -xf "$backup_archive"
	}

	trap restore_wp_content EXIT
	wp core update
	restore_wp_content
	trap - EXIT

	# The restore is the step that failed silently before. Assert it worked:
	# core update replaces wp-content wholesale, so a shortfall here means real
	# site content (uploads above all) did not come back.
	files_after=$(count_wp_content)
	echo "wp-content files after restore: ${files_after}"

	if [[ "$files_after" -lt "$files_before" ]]; then
		echo "wp-env: wp-content restore lost $((files_before - files_after)) file(s)." >&2
		echo "wp-env: archive kept at ${backup_archive} inside the cli container." >&2
		exit 1
	fi

	rm -f "$backup_archive"
	wp core update-db
'

echo "wp-env: site is on the latest available WordPress Beta/RC."
