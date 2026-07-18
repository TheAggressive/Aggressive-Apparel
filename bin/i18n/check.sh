#!/usr/bin/env bash
# CI gate: POT drift, PO validity, translator comments, compile dry-run.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

aa_i18n_ensure_languages_dir
[[ -f "${AA_POT_FILE}" ]] || aa_i18n_die "Committed POT missing at ${AA_POT_FILE}"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT

tmp_pot="${tmp_dir}/aggressive-apparel.pot"
aa_i18n_info "Regenerating POT for drift check…"

aa_i18n_wp i18n make-pot \
	. \
	"${tmp_pot}" \
	--domain="${AA_TEXT_DOMAIN}" \
	--exclude="${AA_I18N_EXCLUDE}"

norm_committed="${tmp_dir}/committed.pot"
norm_generated="${tmp_dir}/generated.pot"
aa_i18n_normalize_pot "${AA_POT_FILE}" "${norm_committed}"
aa_i18n_normalize_pot "${tmp_pot}" "${norm_generated}"

if ! diff -u "${norm_committed}" "${norm_generated}" > "${tmp_dir}/pot.diff"; then
	aa_i18n_info "POT drift detected. First 80 lines of diff:"
	head -n 80 "${tmp_dir}/pot.diff" || true
	aa_i18n_die "languages/${AA_TEXT_DOMAIN}.pot is out of date. Run: pnpm i18n:pot && commit the result."
fi

aa_i18n_info "POT is up to date."

# Validate locale catalogs when present.
po_files="$(aa_i18n_list_po_files || true)"
if [[ -n "${po_files}" ]]; then
	while IFS= read -r po; do
		[[ -n "${po}" ]] || continue
		aa_i18n_info "Validating $(basename "${po}")"
		if command -v msgfmt >/dev/null 2>&1; then
			msgfmt -c -o /dev/null "${po}"
		else
			# WP-CLI compile to a temp dir as a validity check.
			aa_i18n_wp i18n make-mo "${po}" "${tmp_dir}"
		fi
	done <<< "${po_files}"
else
	aa_i18n_info "No locale .po files — skipping PO validation."
fi

# Translator-comment lint (placeholders).
bash "${AA_I18N_DIR}/lint-translators.sh"

aa_i18n_info "i18n check passed."
