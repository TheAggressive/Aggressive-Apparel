#!/usr/bin/env bash
# Scaffold languages/aggressive-apparel-<locale>.po from the POT.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

# pnpm forwards a literal "--" before script args; skip it.
while [[ "${1:-}" == "--" ]]; do
	shift
done

locale="${1:-}"
[[ -n "${locale}" && "${locale}" != "--" ]] || aa_i18n_die "Usage: pnpm i18n:locale -- <locale>   (e.g. fr_FR)"

[[ -f "${AA_POT_FILE}" ]] || aa_i18n_die "Missing POT. Run: pnpm i18n:pot"

po_file="${AA_LANGUAGES_DIR}/${AA_TEXT_DOMAIN}-${locale}.po"
if [[ -f "${po_file}" ]]; then
	aa_i18n_info "Locale already exists: ${po_file}"
	exit 0
fi

aa_i18n_ensure_languages_dir
aa_i18n_info "Creating ${po_file}"

if command -v msginit >/dev/null 2>&1; then
	msginit --no-translator --locale="${locale}" --input="${AA_POT_FILE}" --output-file="${po_file}"
else
	# Fallback when gettext tools are unavailable: clone the POT header/body.
	cp "${AA_POT_FILE}" "${po_file}"
	if grep -q '^"Language:' "${po_file}"; then
		sed -i.bak -E "s/^\"Language: .*\\\\n\"$/\"Language: ${locale}\\\\n\"/" "${po_file}"
	else
		sed -i.bak -E "s/^(\"Content-Transfer-Encoding: .*\\\\n\")$/\\1\n\"Language: ${locale}\\\\n\"/" "${po_file}"
	fi
	rm -f "${po_file}.bak"
fi

# Ensure Language header matches the locale code.
if grep -q '^"Language:' "${po_file}"; then
	sed -i.bak -E "s/^\"Language: .*\\\\n\"$/\"Language: ${locale}\\\\n\"/" "${po_file}"
	rm -f "${po_file}.bak"
fi

aa_i18n_info "Done. Translate ${po_file}, then: pnpm i18n:sync && pnpm i18n:compile"
