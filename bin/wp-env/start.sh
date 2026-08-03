#!/usr/bin/env bash
#
# Start the development environment without losing site content.
#
# wp-env re-provisions WordPress whenever .wp-env.json changes, and that
# re-provision destroys wp-content — uploaded media included. Nothing in this
# repository can prevent that; it is how wp-env applies a changed config. What
# it can do is stop the loss being permanent, and stop it depending on somebody
# remembering to run `pnpm env:backup` at the right moment.
#
# So: when the config has changed since the last successful start, take a
# recovery point BEFORE starting, and if the environment comes back missing
# media, restore it automatically. The restore only ever replays a snapshot
# taken seconds earlier in this same run.
#
# A normal start — no config change — skips all of it and just starts.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

CONFIG_FILE="${AA_WP_ENV_REPO_ROOT}/.wp-env.json"
STATE_FILE="${AA_WP_ENV_LOCAL_BACKUP_ROOT}/.last-config-hash"

config_hash() {
	sha256sum "${CONFIG_FILE}" | cut -d' ' -f1
}

current_hash="$(config_hash)"
previous_hash=""
if [[ -f "${STATE_FILE}" ]]; then
	previous_hash="$(cat "${STATE_FILE}")"
fi

pre_start_backup=""

# Only worth protecting when there is an installed site to lose. On a first-ever
# start there is nothing to back up, and `wp-env run` would fail anyway.
if [[ "${current_hash}" != "${previous_hash}" ]] &&
	aa_wp_env run cli wp core is-installed --quiet >/dev/null 2>&1; then
	echo "wp-env: .wp-env.json changed since the last start."
	echo "wp-env: wp-env will re-provision WordPress, which clears wp-content."
	echo "wp-env: creating a recovery point first..."

	bash "${SCRIPT_DIR}/backup.sh"

	# Ask restore.sh which recovery points exist rather than re-deriving the
	# listing here. Two copies of that logic could disagree about which snapshot
	# is newest, and this one picks the snapshot the site is rebuilt from.
	pre_start_backup="$(bash "${SCRIPT_DIR}/restore.sh" --list | head -n 1)"

	if [[ -z "${pre_start_backup}" ]]; then
		echo "wp-env: could not determine the recovery point just created." >&2
		echo "wp-env: refusing to start and risk unrecoverable content loss." >&2
		exit 1
	fi
fi

aa_wp_env start

# Restores the activation that wp-env's `"themes"` entry used to provide.
bash "${SCRIPT_DIR}/ensure-theme.sh"

if [[ -n "${pre_start_backup}" ]]; then
	# Restore unconditionally rather than trying to detect whether the
	# re-provision cleared anything. Detection was tried and is not reliable:
	# `wp-env start` returns before the re-provision has finished settling, so a
	# check run immediately afterwards reported healthy content that a later
	# start then found missing. Replaying a snapshot taken seconds ago in this
	# same run is idempotent — it costs a minute and cannot lose data, whereas
	# guessing wrong loses the site.
	echo ""
	echo "wp-env: restoring ${pre_start_backup} over the re-provisioned site..."
	bash "${SCRIPT_DIR}/restore.sh" "${pre_start_backup}" --yes
fi

mkdir -p "$(dirname "${STATE_FILE}")"
printf '%s' "${current_hash}" >"${STATE_FILE}"
