#!/usr/bin/env bash

# Create a verified development database and wp-content recovery point.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${1:-}" == "--container" ]]; then
	destination="${2:?Container backup destination is required.}"

	case "${destination}" in
		.wp-env-backup-staging/*) ;;
		*)
			echo "wp-env: refusing to write outside the backup staging directory." >&2
			exit 1
			;;
	esac

	umask 077
	mkdir -p "${destination}"

	wp db export "${destination}/database.sql" --add-drop-table --quiet

	tar \
		--exclude="wp-content/themes/aggressive-apparel" \
		--exclude="wp-content/themes/Aggressive-Apparel" \
		--exclude="wp-content/plugins/woocommerce" \
		--exclude="wp-content/plugins/wordpress-beta-tester" \
		--exclude="wp-content/mu-plugins" \
		--exclude="wp-content/debug.log" \
		-C /var/www/html \
		-czf "${destination}/wp-content.tar.gz" \
		wp-content

	{
		printf "created_utc=%s\n" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
		printf "site_url=%s\n" "$(wp option get siteurl)"
		printf "wordpress_version=%s\n" "$(wp core version)"
		printf "php_version=%s\n" "$(php -r 'echo PHP_VERSION;')"
		printf "theme=%s\n" "$(wp theme list --status=active --field=name | head -n 1)"
		printf "woocommerce_version=%s\n" "$(wp plugin get woocommerce --field=version 2>/dev/null || echo "not installed")"
		printf "attachment_count=%s\n" "$(wp post list --post_type=attachment --post_status=any --format=count)"
	} >"${destination}/metadata.env"

	(
		cd "${destination}"
		sha256sum database.sql wp-content.tar.gz metadata.env >SHA256SUMS
	)

	exit 0
fi

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

aa_require_development_site

backup_id="$(date -u +%Y%m%dT%H%M%SZ)-$$"
staging_directory="${AA_WP_ENV_STAGING_ROOT}/${backup_id}"
destination_directory="${AA_WP_ENV_BACKUP_ROOT}/${backup_id}"
destination_partial="${AA_WP_ENV_BACKUP_ROOT}/.${backup_id}.partial"
container_destination=".wp-env-backup-staging/${backup_id}"

cleanup() {
	rm -rf -- "${staging_directory}"
	rm -rf -- "${destination_partial}"
}
trap cleanup EXIT

umask 077
mkdir -p "${staging_directory}"
if [[ ! -d "${AA_WP_ENV_BACKUP_ROOT}" ]]; then
	# SC2174 is about `-m` applying only to the deepest directory. Splitting
	# this into mkdir + chmod does NOT fix that — parents still take the umask
	# either way — and it adds a window where the directory exists at the
	# umask's mode. The `umask 077` set immediately above already covers every
	# parent, so keep the atomic form.
	# shellcheck disable=SC2174
	mkdir -m 700 -p "${AA_WP_ENV_BACKUP_ROOT}"
fi
chmod 700 "${AA_WP_ENV_STAGING_ROOT}" "${staging_directory}"

echo "wp-env: creating recovery point ${backup_id}..."
aa_wp_env run cli \
	--env-cwd="${AA_WP_ENV_THEME_CWD}" \
	-- bash bin/wp-env/backup.sh --container "${container_destination}"

if [[ ! -s "${staging_directory}/database.sql" || ! -s "${staging_directory}/wp-content.tar.gz" ]]; then
	echo "wp-env: backup validation failed because an expected artifact is empty." >&2
	exit 1
fi

(
	cd "${staging_directory}"
	sha256sum --check --strict SHA256SUMS
	tar -tzf wp-content.tar.gz >/dev/null
)

if [[ -e "${destination_directory}" ]]; then
	echo "wp-env: refusing to overwrite existing backup ${destination_directory}." >&2
	exit 1
fi

if [[ "$(stat -c '%d' "${staging_directory}")" == "$(stat -c '%d' "${AA_WP_ENV_BACKUP_ROOT}")" ]]; then
	mv -- "${staging_directory}" "${destination_directory}"
else
	cp -a -- "${staging_directory}" "${destination_partial}"
	(
		cd "${destination_partial}"
		sha256sum --check --strict SHA256SUMS
		tar -tzf wp-content.tar.gz >/dev/null
	)
	mv -- "${destination_partial}" "${destination_directory}"
	rm -rf -- "${staging_directory}"
fi
trap - EXIT

echo "wp-env: verified recovery point created:"
echo "  ${destination_directory}"

retained=0
while IFS= read -r retained_backup_id; do
	((retained += 1))
	if ((retained <= AA_WP_ENV_BACKUP_RETENTION)); then
		continue
	fi

	aa_validate_backup_id "${retained_backup_id}"
	rm -rf -- "${AA_WP_ENV_BACKUP_ROOT:?}/${retained_backup_id}"
	echo "wp-env: pruned expired recovery point ${retained_backup_id}."
done < <(
	aa_list_backup_ids "${AA_WP_ENV_BACKUP_ROOT}"
)
