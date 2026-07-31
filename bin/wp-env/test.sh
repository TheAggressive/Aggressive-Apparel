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

node -e '
	const fs = require( "fs" );
	const pkg = JSON.parse( fs.readFileSync( "package.json", "utf8" ) );
	const override = JSON.parse(
		fs.readFileSync( "bin/wp-env/ci.override.json", "utf8" )
	);
	const wpEnv = JSON.parse( fs.readFileSync( ".wp-env.json", "utf8" ) );
	const stablePluginPattern =
		/^https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.\d+\.\d+\.\d+\.zip$/;
	const stableCorePattern =
		/^https:\/\/wordpress\.org\/wordpress-\d+\.\d+\.\d+\.zip$/;

	for ( const unsafe of [ "env:clean", "env:destroy" ] ) {
		if ( Object.hasOwn( pkg.scripts, unsafe ) ) {
			throw new Error( `${ unsafe } bypasses the guarded lifecycle.` );
		}
	}

	const developmentPlugins = override.env?.development?.plugins;
	const testPlugins = override.env?.tests?.plugins;
	if (
		developmentPlugins?.length !== 1 ||
		! stablePluginPattern.test( developmentPlugins[ 0 ] ) ||
		JSON.stringify( developmentPlugins ) !== JSON.stringify( testPlugins )
	) {
		throw new Error( "CI must use one identical, version-pinned WooCommerce archive." );
	}

	if ( ! stableCorePattern.test( wpEnv.core ) ) {
		throw new Error( "The required CI baseline must pin a stable WordPress archive." );
	}
'

if rg --pcre2 \
	'^\s*-\s*uses:\s*[^@\s]+@(?![0-9a-f]{40}(?:\s|#|$))' \
	"${REPO_ROOT}/.github/workflows"; then
	echo "Every GitHub Action must be pinned to a complete commit SHA." >&2
	exit 1
fi

if [[ "$(rg -c 'WP_ENV_SKIP_BETA_UPDATE' "${REPO_ROOT}/.github/workflows/release.yml")" != "2" ]] ||
	[[ "$(rg -c 'ci\.override\.json' "${REPO_ROOT}/.github/workflows/release.yml")" != "2" ]]; then
	echo "Required release jobs must use the deterministic wp-env lane." >&2
	exit 1
fi

echo "wp-env tooling contracts passed."
