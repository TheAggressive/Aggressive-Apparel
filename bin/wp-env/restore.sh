#!/usr/bin/env bash

# Restore a verified recovery point after preserving the current environment.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

if [[ "${1:-}" == "--" ]]; then
	shift
fi

if [[ "${1:-}" == "--list" ]]; then
	if [[ ! -d "${AA_WP_ENV_BACKUP_ROOT}" ]]; then
		echo "No wp-env recovery points found."
		exit 0
	fi

	find "${AA_WP_ENV_BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' |
		sort --reverse
	exit 0
fi

if [[ "${1:-}" == "--container" ]]; then
	source_directory="${2:?Container restore source is required.}"

	case "${source_directory}" in
		.wp-env-backup-staging/*) ;;
		*)
			echo "wp-env: refusing to restore from outside the backup staging directory." >&2
			exit 1
			;;
	esac

	tar \
		--exclude="wp-content/mu-plugins/wp-env-cron-loopback.php" \
		--exclude="wp-content/mu-plugins/e2e-product-tabs-style.php" \
		-C /var/www/html \
		-xzf "${source_directory}/wp-content.tar.gz"
	wp db import "${source_directory}/database.sql"
	wp core update-db
	wp cache flush
	wp rewrite flush
	exit 0
fi

backup_id="${1:-}"
if [[ -z "${backup_id}" ]]; then
	echo "Usage: pnpm env:restore -- <backup-id> [--yes]" >&2
	echo "List recovery points with: pnpm env:backups" >&2
	exit 2
fi
shift

aa_validate_backup_id "${backup_id}"
assume_yes="$(aa_parse_yes_flag "$@")"
source_directory="${AA_WP_ENV_BACKUP_ROOT}/${backup_id}"

if [[ ! -d "${source_directory}" ]]; then
	echo "wp-env: recovery point not found: ${backup_id}" >&2
	exit 1
fi

(
	cd "${source_directory}"
	sha256sum --check --strict SHA256SUMS
	tar -tzf wp-content.tar.gz >/dev/null
	test -s database.sql
)

aa_confirm_destructive_action \
	"replace the development database and overlay wp-content from ${backup_id}" \
	"RESTORE" \
	"${assume_yes}"

aa_require_development_site

# Preserve the state being replaced so an interrupted or mistaken restore is
# itself reversible.
bash "${SCRIPT_DIR}/backup.sh"

restore_staging="${AA_WP_ENV_STAGING_ROOT}/restore-${backup_id}-$$"
cleanup() {
	rm -rf -- "${restore_staging}"
}
trap cleanup EXIT

mkdir -p "${restore_staging}"
chmod 700 "${restore_staging}"
cp -- \
	"${source_directory}/database.sql" \
	"${source_directory}/wp-content.tar.gz" \
	"${restore_staging}/"

aa_wp_env run cli \
	--env-cwd="${AA_WP_ENV_THEME_CWD}" \
	-- bash bin/wp-env/restore.sh \
	--container ".wp-env-backup-staging/$(basename "${restore_staging}")"

bash "${SCRIPT_DIR}/check.sh"
trap - EXIT
rm -rf -- "${restore_staging}"

echo "wp-env: restored recovery point ${backup_id}."
