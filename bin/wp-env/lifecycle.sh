#!/usr/bin/env bash

# Guard destructive wp-env lifecycle operations with confirmation and backup.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=bin/wp-env/lib.sh
source "${SCRIPT_DIR}/lib.sh"

operation="${1:-}"
shift || true
assume_yes="$(aa_parse_yes_flag "$@")"

case "${operation}" in
	clean)
		action_description="erase both wp-env databases"
		confirmation="CLEAN"
		;;
	destroy)
		action_description="delete wp-env containers, databases, and local environment files"
		confirmation="DESTROY"
		;;
	reset)
		action_description="erase both wp-env databases and recreate the sites"
		confirmation="RESET"
		;;
	*)
		echo "Usage: $0 {clean|destroy|reset} [--yes]" >&2
		exit 2
		;;
esac

aa_confirm_destructive_action "${action_description}" "${confirmation}" "${assume_yes}"
aa_require_development_site

# A destructive operation must never begin unless its recovery point verifies.
bash "${SCRIPT_DIR}/backup.sh"

case "${operation}" in
	clean)
		aa_wp_env clean all --no-scripts
		bash "${SCRIPT_DIR}/check.sh"
		echo "wp-env: both databases were reset, reconfigured, and verified."
		;;
	destroy)
		aa_wp_env destroy --no-scripts
		echo "wp-env: environment destroyed. The verified recovery point was retained."
		;;
	reset)
		aa_wp_env clean all --no-scripts
		aa_wp_env start
		bash "${SCRIPT_DIR}/check.sh"
		;;
esac
