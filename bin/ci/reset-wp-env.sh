#!/usr/bin/env bash

# Reset only the isolated CI parity databases and reapply their pinned config.
# This never addresses WordPress Studio or its SQLite database and uploads.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
install_path="$(bash "${SCRIPT_DIR}/wp-env.sh" install-path)"
xdebug_mode="${AA_CI_XDEBUG_MODE:-}"

case "${xdebug_mode}" in
	"" | coverage) ;;
	*)
		echo "AA_CI_XDEBUG_MODE must be empty or coverage." >&2
		exit 2
		;;
esac

start_wp_env() {
	if [[ -n "${xdebug_mode}" ]]; then
		bash "${SCRIPT_DIR}/wp-env.sh" start --xdebug="${xdebug_mode}"
	else
		bash "${SCRIPT_DIR}/wp-env.sh" start
	fi
}

if [[ -f "${install_path}/docker-compose.yml" ]]; then
	# An interrupted wp-env clone can leave a non-empty directory with an
	# incomplete .git directory. Quarantine only those generated checkouts so
	# wp-env can recreate them; never mutate a valid checkout.
	quarantine_root=""
	for checkout_name in WordPress-PHPUnit tests-WordPress-PHPUnit; do
		checkout="${install_path}/${checkout_name}"
		if [[ -d "${checkout}" && ! -f "${checkout}/.git/HEAD" ]]; then
			if [[ -z "${quarantine_root}" ]]; then
				quarantine_root="$(mktemp -d "${TMPDIR:-/tmp}/aa-wp-env-incomplete.XXXXXX")"
			fi
			mv "${checkout}" "${quarantine_root}/${checkout_name}"
			echo "Quarantined incomplete wp-env checkout: ${checkout_name}"
		fi
	done

	# Docker can create nested mapping placeholders as a remapped user. Repair
	# only the generated parity wp-content tree through its existing Compose
	# image before wp-env reconciles mappings. Repository sources are mounted
	# elsewhere and are never exposed at /repair.
	wordpress_root="$(
		find "${install_path}" -mindepth 1 -maxdepth 1 -type d \
			-name 'wordpress-*' -print -quit
	)"
	wp_content="${wordpress_root}/wp-content"
	unwritable_path=""
	if [[ -n "${wordpress_root}" && -d "${wp_content}" ]]; then
		unwritable_path="$(
			find "${wp_content}" ! -writable -print -quit
		)"
	fi
	if [[ -n "${unwritable_path}" ]]; then
		docker compose --file "${install_path}/docker-compose.yml" \
			run --rm --no-deps --volume "${wordpress_root}:/repair" wordpress \
			chmod -R a+rwX /repair/wp-content
	fi

	start_wp_env
	bash "${SCRIPT_DIR}/wp-env.sh" clean all --no-scripts
else
	start_wp_env
fi

bash "${SCRIPT_DIR}/wp-env.sh" run cli wp --info
