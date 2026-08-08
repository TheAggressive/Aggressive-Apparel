#!/usr/bin/env bash
# Validate every locale catalog: msgfmt -c, then placeholder parity.
#
# This exists as its own script because it is the one part of the i18n gate
# that cannot run inside the wp-env container. POT drift needs the pinned
# WP-CLI, which lives in the container; catalog validation needs msgfmt, which
# the Alpine cli image does not ship. Running both in the container is how the
# gate ended up validating nothing at all:
#
#   `wp i18n make-mo` accepts an unterminated msgid and a msgid/msgstr
#   placeholder mismatch alike, prints "Success: Created 1 file", and exits 0.
#
# So the CI lane runs POT drift in the container and this script on the host.
# msgfmt -c is the only check here that rejects a broken catalog, which is why
# a missing msgfmt is a hard failure rather than a skip: a catalog whose
# placeholders no longer match its msgid is a production crash, and machine
# translation is exactly the path that produces one.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

if ! command -v msgfmt >/dev/null 2>&1; then
	aa_i18n_die "msgfmt (gettext) is required to validate locale catalogs. Install gettext, or set AA_I18N_PO_VALIDATOR=skip to run the rest of the gate without catalog validation."
fi

# Placeholder parity is the second half of catalog validation and lives on the
# host for the same reason msgfmt does: the wp-env cli container ships neither
# gettext nor node. Same fail-closed rule — a missing tool is an error, never a
# quiet downgrade.
if ! command -v node >/dev/null 2>&1; then
	aa_i18n_die "node is required to lint catalog placeholders. Install Node, or set AA_I18N_PO_VALIDATOR=skip to run the rest of the gate without catalog validation."
fi

po_files="$(aa_i18n_list_po_files || true)"

if [[ -z "${po_files}" ]]; then
	aa_i18n_info "No locale .po files — nothing to validate."
	exit 0
fi

failures=0

while IFS= read -r po; do
	[[ -n "${po}" ]] || continue
	aa_i18n_info "Validating $(basename "${po}")"

	# -c turns on the checks that matter: msgfmt without it happily compiles a
	# catalog whose format specifiers disagree with the msgid.
	if ! msgfmt -c -o /dev/null "${po}"; then
		failures=$(( failures + 1 ))
	fi
done <<< "${po_files}"

if (( failures > 0 )); then
	aa_i18n_die "${failures} locale catalog(s) failed validation."
fi

# msgfmt -c only compares format specifiers on entries gettext flagged as a
# format string, and it has no concept of the `{percent}` tokens this theme
# substitutes itself. Both gaps are how a translated token reaches a page.
# shellcheck disable=SC2086 # Word splitting is the intent: one argument per catalog.
node "${AA_I18N_DIR}/lint-placeholders.mjs" ${po_files}

aa_i18n_info "Locale catalogs valid."
