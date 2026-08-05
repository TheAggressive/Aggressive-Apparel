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

# Validate locale catalogs.
#
# Only two modes, because the third was a lie. The old `wp-cli` validator ran
# `wp i18n make-mo`, which reports "Success" on an unterminated msgid and on a
# msgid/msgstr placeholder mismatch alike — and bin/ci/i18n.sh forced that mode
# for every CI run, so this gate printed "Validating <catalog>" four times and
# passed unconditionally. Skipping is now explicit and loud; it is never
# something a missing tool selects on your behalf.
case "${AA_I18N_PO_VALIDATOR:-auto}" in
	auto)
		bash "${AA_I18N_DIR}/validate-po.sh"
		;;
	skip)
		aa_i18n_info "PO validation SKIPPED (AA_I18N_PO_VALIDATOR=skip) — catalogs are NOT checked here."
		;;
	*)
		aa_i18n_die "AA_I18N_PO_VALIDATOR must be 'auto' or 'skip' (got '${AA_I18N_PO_VALIDATOR}')."
		;;
esac

# Translator-comment lint (placeholders).
bash "${AA_I18N_DIR}/lint-translators.sh"

aa_i18n_info "i18n check passed."
