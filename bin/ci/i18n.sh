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

# Catalog validation needs msgfmt on the host (see below). The lane already
# bootstraps its own WP-CLI, so it bootstraps gettext the same way rather than
# depending on whatever the runner image happens to ship — the sibling
# i18n-translate workflow installs gettext explicitly for exactly that reason.
#
# Guarded three ways so it is a no-op for developers: it only runs when msgfmt
# is genuinely missing, only under apt-get, and only with non-interactive sudo.
# If it cannot install, validate-po.sh still fails closed with instructions
# rather than silently skipping.
if ! command -v msgfmt > /dev/null 2>&1; then
	if command -v apt-get > /dev/null 2>&1 && sudo -n true > /dev/null 2>&1; then
		echo "msgfmt not found — installing gettext for catalog validation."
		sudo -n apt-get update -qq && sudo -n apt-get install -y -qq gettext
	else
		echo "Warning: msgfmt is missing and cannot be installed automatically." >&2
	fi
fi

cleanup() {
	if ! bash "${SCRIPT_DIR}/stop-wp-env.sh"; then
		echo "Warning: CI parity containers could not be stopped." >&2
	fi
}
trap cleanup EXIT

bash "${SCRIPT_DIR}/reset-wp-env.sh"

# The gate is split across the container boundary on purpose.
#
# POT drift needs the pinned WP-CLI, which lives in the wp-env cli container.
# Catalog validation needs msgfmt, which that container (Alpine) does not ship.
# Running both inside it is how catalog validation became a no-op: the lane
# forced the `wp-cli` validator, and `wp i18n make-mo` accepts a broken catalog
# and exits 0. So POT drift runs in the container and catalogs run on the host,
# where msgfmt is a hard requirement rather than a silent downgrade.
bash "${SCRIPT_DIR}/wp-env.sh" run cli \
	--env-cwd="wp-content/themes/aggressive-apparel" \
	-- bash -c 'PATH="$PWD/bin/ci:$PATH" I18N_CI=1 AA_I18N_PO_VALIDATOR=skip bash bin/i18n/check.sh'

bash "${REPO_ROOT}/bin/i18n/validate-po.sh"
