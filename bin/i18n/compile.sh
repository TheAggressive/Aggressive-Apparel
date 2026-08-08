#!/usr/bin/env bash
# Compile every locale .po into .mo + Jed JSON for classic scripts.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

aa_i18n_ensure_languages_dir

po_files="$(aa_i18n_list_po_files || true)"
if [[ -z "${po_files}" ]]; then
	aa_i18n_info "No locale .po files to compile."
	exit 0
fi

while IFS= read -r po; do
	[[ -n "${po}" ]] || continue
	locale="$(aa_i18n_locale_from_po "${po}")"
	aa_i18n_info "Compiling ${locale}"

	# Binary catalog for PHP gettext.
	aa_i18n_wp i18n make-mo "${po}" "${AA_LANGUAGES_DIR}"

	# make-mo names its output after the .po, giving aggressive-apparel-de_DE.mo.
	# That is the wp-content/languages/themes/ convention, and it is the wrong
	# one for a catalog the theme ships itself.
	#
	# _load_textdomain_just_in_time() picks the filename from where the
	# registered path points:
	#
	#   if ( str_starts_with( $path, $template_directory ) || … ) {
	#       $mofile = "{$path}{$locale}.mo";            // de_DE.mo
	#   } else {
	#       $mofile = "{$path}{$domain}-{$locale}.mo";  // aggressive-apparel-de_DE.mo
	#   }
	#
	# There is no fallback — it returns load_textdomain() on that one path.
	# Theme_Support registers get_template_directory() . '/languages', so only
	# the first branch is ever taken and a prefixed file is never opened.
	#
	# Nothing surfaces this. Since WordPress 6.7 load_theme_textdomain() only
	# records the path and returns true unconditionally, so the call looks like
	# it worked while every string falls through to English. All four locales
	# were compiled, shipped and dead for exactly this reason.
	mv "${AA_LANGUAGES_DIR}/${AA_TEXT_DOMAIN}-${locale}.mo" "${AA_LANGUAGES_DIR}/${locale}.mo"

	# The JSON catalogs keep the domain prefix: _load_script_textdomain_from_src()
	# builds "{$domain}-{$locale}-{$md5}.json" with no equivalent path branch,
	# which is why script translations were unaffected.
	aa_i18n_wp i18n make-json "${po}" "${AA_LANGUAGES_DIR}" --pretty-print --no-purge
done <<< "${po_files}"

aa_i18n_info "Done."
