#!/usr/bin/env bash
# Extract theme strings into languages/aggressive-apparel.pot.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

aa_i18n_ensure_languages_dir
aa_i18n_info "Generating ${AA_POT_FILE}"

aa_i18n_wp i18n make-pot \
	. \
	"${AA_POT_FILE}" \
	--domain="${AA_TEXT_DOMAIN}" \
	--exclude="${AA_I18N_EXCLUDE}"

if [[ "${I18N_CI:-0}" == "1" ]]; then
	tmp="$(mktemp)"
	aa_i18n_normalize_pot "${AA_POT_FILE}" "${tmp}"
	mv "${tmp}" "${AA_POT_FILE}"
	aa_i18n_info "Normalized volatile POT headers (I18N_CI=1)."
fi

aa_i18n_info "Done."
