#!/usr/bin/env bash

# Run the i18n gate with the same pinned WP-CLI release locally and in Actions.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
TOOL_DIR="${REPO_ROOT}/.cache/ci"
WP_CLI_PATH="${TOOL_DIR}/wp"

mkdir -p "${TOOL_DIR}"
WP_CLI_INSTALL_PATH="${WP_CLI_PATH}" \
	WP_CLI_SKIP_INFO=1 \
	bash "${SCRIPT_DIR}/install-wp-cli.sh"

cleanup() {
	if ! bash "${SCRIPT_DIR}/stop-wp-env.sh"; then
		echo "Warning: CI parity containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

bash "${SCRIPT_DIR}/reset-wp-env.sh"
bash "${SCRIPT_DIR}/wp-env.sh" run cli \
	--env-cwd="wp-content/themes/aggressive-apparel" \
	-- bash -c 'PATH="$PWD/bin/ci:$PATH" I18N_CI=1 AA_I18N_PO_VALIDATOR=wp-cli bash bin/i18n/check.sh'
