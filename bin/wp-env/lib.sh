#!/usr/bin/env bash

# Shared, host-side helpers for the repository's wp-env lifecycle commands.

set -euo pipefail

AA_WP_ENV_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AA_WP_ENV_REPO_ROOT="$(cd "${AA_WP_ENV_SCRIPT_DIR}/../.." && pwd)"
AA_WP_ENV_THEME_CWD="wp-content/themes/aggressive-apparel"
AA_WP_ENV_LOCAL_BACKUP_ROOT="${AA_WP_ENV_REPO_ROOT}/.wp-env-backups"
AA_WP_ENV_BACKUP_ROOT="${WP_ENV_BACKUP_DIR:-${AA_WP_ENV_LOCAL_BACKUP_ROOT}}"
AA_WP_ENV_STAGING_ROOT="${AA_WP_ENV_REPO_ROOT}/.wp-env-backup-staging"
AA_WP_ENV_BACKUP_RETENTION="${WP_ENV_BACKUP_RETENTION:-5}"

if [[ "${AA_WP_ENV_BACKUP_ROOT}" != /* ]]; then
	echo "WP_ENV_BACKUP_DIR must be an absolute path." >&2
	exit 2
fi

case "${AA_WP_ENV_BACKUP_ROOT}" in
	/ | "${AA_WP_ENV_REPO_ROOT}" | "${AA_WP_ENV_STAGING_ROOT}" | "${HOME:-/nonexistent}")
		echo "WP_ENV_BACKUP_DIR must be a dedicated backup directory." >&2
		exit 2
		;;
esac

if [[ ! "${AA_WP_ENV_BACKUP_RETENTION}" =~ ^[1-9][0-9]*$ ]]; then
	echo "WP_ENV_BACKUP_RETENTION must be a positive integer." >&2
	exit 2
fi

aa_wp_env() {
	(
		cd "${AA_WP_ENV_REPO_ROOT}"
		pnpm wp-env "$@"
	)
}

aa_require_development_site() {
	if ! aa_wp_env run cli wp core is-installed --quiet >/dev/null 2>&1; then
		echo "wp-env: the development site is not running or WordPress is not installed." >&2
		echo "Start it with: pnpm env:start" >&2
		return 1
	fi
}

aa_confirm_destructive_action() {
	local action="$1"
	local confirmation="$2"
	local assume_yes="${3:-0}"
	local answer

	if [[ "${assume_yes}" == "1" || "${WP_ENV_CONFIRM_DESTRUCTIVE:-0}" == "1" ]]; then
		return 0
	fi

	if [[ ! -t 0 ]]; then
		echo "wp-env: refusing to ${action} without interactive confirmation." >&2
		echo "For intentional automation, pass --yes or set WP_ENV_CONFIRM_DESTRUCTIVE=1." >&2
		return 1
	fi

	echo "This will ${action}."
	printf "Type %s to continue: " "${confirmation}"
	read -r answer

	if [[ "${answer}" != "${confirmation}" ]]; then
		echo "wp-env: cancelled."
		return 1
	fi
}

aa_parse_yes_flag() {
	local argument

	for argument in "$@"; do
		case "${argument}" in
			--)
				;;
			--yes)
				echo "1"
				return 0
				;;
			*)
				echo "Unknown option: ${argument}" >&2
				return 2
				;;
		esac
	done

	echo "0"
}

aa_validate_backup_id() {
	local backup_id="$1"

	if [[ ! "${backup_id}" =~ ^[0-9]{8}T[0-9]{6}Z-[0-9]+$ ]]; then
		echo "wp-env: invalid backup identifier: ${backup_id}" >&2
		return 1
	fi
}

aa_list_backup_ids() {
	local backup_root="$1"

	find "${backup_root}" \
		-regextype posix-extended \
		-mindepth 1 \
		-maxdepth 1 \
		-type d \
		-regex '.*/[0-9]{8}T[0-9]{6}Z-[0-9]+' \
		-printf '%f\n' |
		sort --reverse
}
