#!/usr/bin/env bash
#
# Activate the mounted project theme if it is not already active.
#
# wp-env used to do this: a `"themes": ["."]` entry both mounts and activates.
# That entry had to go, because wp-env names the mount after the checkout
# directory (path.basename of the resolved source), so the theme installed as
# "aggressive-apparel" locally and "Aggressive-Apparel" on a GitHub runner —
# which broke every path keyed to the canonical slug. An explicit mapping fixes
# the name but does not activate anything, so a fresh environment would come up
# on a default WordPress theme with no indication why.
#
# Idempotent: safe to run on every start, and silent when nothing changes.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${1:-}" == "--container" ]]; then
	if ! wp core is-installed --quiet 2>/dev/null; then
		echo "wp-env: WordPress is not installed yet; skipping theme activation."
		exit 0
	fi

	active="$(wp option get stylesheet 2>/dev/null || true)"
	if [[ "${active}" == "aggressive-apparel" ]]; then
		exit 0
	fi

	if ! wp theme is-installed aggressive-apparel 2>/dev/null; then
		echo "wp-env: aggressive-apparel is not mounted; cannot activate it." >&2
		echo "wp-env: check the mappings in .wp-env.json." >&2
		exit 1
	fi

	wp theme activate aggressive-apparel
	exit 0
fi

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

aa_wp_env run cli \
	--env-cwd="${AA_WP_ENV_THEME_CWD}" \
	-- bash bin/wp-env/ensure-theme.sh --container
