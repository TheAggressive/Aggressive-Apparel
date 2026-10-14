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
		# Never fatal on its own. Under `set -e` a flaky apt mirror would kill
		# the lane with a raw apt error; falling through instead lets
		# validate-po.sh report the actionable "install gettext" message.
		if ! { sudo -n apt-get update -qq && sudo -n apt-get install -y -qq gettext; }; then
			echo "Warning: installing gettext failed." >&2
		fi
	else
		echo "Warning: msgfmt is missing and cannot be installed automatically." >&2
	fi
fi

if ! command -v php > /dev/null 2>&1; then
	echo "PHP is required to run the pinned WP-CLI (Actions provisions it with setup-php)." >&2
	exit 1
fi

# POT drift runs the pinned, checksum-verified WP-CLI PHAR on host PHP. It used
# to boot the whole wp-env parity stack (two WordPress sites, WooCommerce,
# MySQL) only to borrow the cli container's PHP binary, which cost more than a
# minute per run. Nothing here ships: the regenerated POT is only compared with
# the committed one after normalization.
#
# Catalog validation stays a separate host step that hard-requires msgfmt.
# `wp i18n make-mo` accepts a broken catalog and exits 0, which is how forcing
# the `wp-cli` validator once made catalog validation a silent no-op.
AA_I18N_WP_BIN="${SCRIPT_DIR}/wp" I18N_CI=1 AA_I18N_PO_VALIDATOR=skip \
	bash "${REPO_ROOT}/bin/i18n/check.sh"

bash "${REPO_ROOT}/bin/i18n/validate-po.sh"
