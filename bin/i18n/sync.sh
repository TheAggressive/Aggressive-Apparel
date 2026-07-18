#!/usr/bin/env bash
# Merge the current POT into every locale PO (fuzzy-safe).
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

[[ -f "${AA_POT_FILE}" ]] || aa_i18n_die "Missing POT. Run: pnpm i18n:pot"

po_files="$(aa_i18n_list_po_files || true)"
if [[ -z "${po_files}" ]]; then
	aa_i18n_info "No locale .po files yet. Scaffold one with: pnpm i18n:locale -- <locale>"
	exit 0
fi

while IFS= read -r po; do
	[[ -n "${po}" ]] || continue
	locale="$(aa_i18n_locale_from_po "${po}")"
	aa_i18n_info "Syncing ${locale} ← pot"

	if command -v msgmerge >/dev/null 2>&1; then
		msgmerge --update --backup=none --previous "${po}" "${AA_POT_FILE}"
	else
		aa_i18n_wp i18n update-po "${AA_POT_FILE}" "${po}"
	fi
done <<< "${po_files}"

aa_i18n_info "Done."
