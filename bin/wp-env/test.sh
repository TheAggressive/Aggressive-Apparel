#!/usr/bin/env bash

# Fast, Docker-free contract tests for the wp-env safety tooling.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

scripts=(
	"${SCRIPT_DIR}/backup.sh"
	"${SCRIPT_DIR}/check.sh"
	"${SCRIPT_DIR}/lib.sh"
	"${SCRIPT_DIR}/lifecycle.sh"
	"${SCRIPT_DIR}/restore.sh"
	"${SCRIPT_DIR}/test.sh"
	"${SCRIPT_DIR}/update-beta-channel.sh"
	"${REPO_ROOT}/bin/ci/e2e.sh"
	"${REPO_ROOT}/bin/ci/i18n.sh"
	"${REPO_ROOT}/bin/ci/node.sh"
	"${REPO_ROOT}/bin/ci/php.sh"
	"${REPO_ROOT}/bin/ci/reset-wp-env.sh"
	"${REPO_ROOT}/bin/ci/stop-wp-env.sh"
	"${REPO_ROOT}/bin/ci/verify.sh"
	"${REPO_ROOT}/bin/ci/wp"
	"${REPO_ROOT}/bin/ci/wp-env.sh"
)

bash -n "${scripts[@]}"

(
	# shellcheck source=bin/wp-env/lib.sh
	source "${SCRIPT_DIR}/lib.sh"

	aa_validate_backup_id "20260731T035931Z-93078"
	if aa_validate_backup_id "../../outside" >/dev/null 2>&1; then
		echo "Unsafe backup identifiers must be rejected." >&2
		exit 1
	fi

	[[ "$(aa_parse_yes_flag)" == "0" ]]
	[[ "$(aa_parse_yes_flag -- --yes)" == "1" ]]

	if aa_confirm_destructive_action "test confirmation" "CONFIRM" "0" </dev/null 2>/dev/null; then
		echo "Non-interactive destructive operations must fail closed." >&2
		exit 1
	fi

	selector_fixture="$(mktemp -d)"
	trap 'rm -rf -- "${selector_fixture}"' EXIT
	mkdir \
		"${selector_fixture}/20260731T035931Z-93078" \
		"${selector_fixture}/20260731T040140Z-94354" \
		"${selector_fixture}/not-a-recovery-point"
	mapfile -t selected_ids < <(aa_list_backup_ids "${selector_fixture}")
	[[ "${#selected_ids[@]}" == "2" ]]
	[[ "${selected_ids[0]}" == "20260731T040140Z-94354" ]]
	[[ "${selected_ids[1]}" == "20260731T035931Z-93078" ]]
)

node "${REPO_ROOT}/bin/ci/contracts.mjs"

echo "wp-env tooling contracts passed."
