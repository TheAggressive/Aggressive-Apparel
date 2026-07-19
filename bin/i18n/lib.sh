#!/usr/bin/env bash
# Shared helpers for theme i18n tooling.
# shellcheck shell=bash

set -euo pipefail

AA_I18N_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AA_THEME_ROOT="$(cd "${AA_I18N_DIR}/../.." && pwd)"
AA_TEXT_DOMAIN="${AA_TEXT_DOMAIN:-aggressive-apparel}"
AA_LANGUAGES_DIR="${AA_THEME_ROOT}/languages"
AA_POT_FILE="${AA_LANGUAGES_DIR}/${AA_TEXT_DOMAIN}.pot"
AA_I18N_EXCLUDE="${AA_I18N_EXCLUDE:-node_modules,vendor,build,coverage,tests,.git,bin}"

# Container-relative theme path when using wp-env.
AA_WP_ENV_THEME_CWD="${AA_WP_ENV_THEME_CWD:-wp-content/themes/aggressive-apparel}"

aa_i18n_info() {
	printf 'i18n: %s\n' "$*"
}

aa_i18n_die() {
	printf 'i18n: ERROR: %s\n' "$*" >&2
	exit 1
}

aa_i18n_ensure_languages_dir() {
	mkdir -p "${AA_LANGUAGES_DIR}"
}

# Load gitignored local secrets (`.env.local`, then `.env`) without overriding
# variables already set in the shell / CI.
aa_i18n_load_dotenv() {
	local env_file
	for env_file in "${AA_THEME_ROOT}/.env.local" "${AA_THEME_ROOT}/.env"; do
		[[ -f "${env_file}" ]] || continue
		# Export KEY=value lines; skip comments and blanks.
		set -a
		# shellcheck disable=SC1090
		source "${env_file}"
		set +a
	done
}

# Run `wp …` either via host WP-CLI or wp-env cli container.
aa_i18n_wp() {
	if [[ "${AA_I18N_FORCE_WP_ENV:-0}" == "1" ]]; then
		command -v wp-env >/dev/null 2>&1 || aa_i18n_die "wp-env is required (AA_I18N_FORCE_WP_ENV=1)."
		CI=true wp-env run cli --env-cwd="${AA_WP_ENV_THEME_CWD}" wp "$@"
		return
	fi

	if command -v wp >/dev/null 2>&1 && wp help i18n >/dev/null 2>&1; then
		( cd "${AA_THEME_ROOT}" && wp --allow-root "$@" )
		return
	fi

	if command -v wp-env >/dev/null 2>&1; then
		CI=true wp-env run cli --env-cwd="${AA_WP_ENV_THEME_CWD}" wp "$@"
		return
	fi

	aa_i18n_die "Need WP-CLI (\`wp\`) with the i18n package, or wp-env."
}

# Strip volatile POT headers for stable CI diffs.
# Report-Msgid-Bugs-To follows the on-disk theme folder name (aggressive-apparel
# vs Aggressive-Apparel), which differs between local checkouts and CI clones.
aa_i18n_normalize_pot() {
	local src="$1"
	local dest="$2"

	sed -E \
		-e 's/^"POT-Creation-Date: .+\\n"$/"POT-Creation-Date: YEAR-MO-DA HO:MI+ZONE\\n"/' \
		-e 's/^"PO-Revision-Date: .+\\n"$/"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"/' \
		-e 's/^"X-Generator: .+\\n"$/"X-Generator: WP-CLI\\n"/' \
		-e 's|^"Report-Msgid-Bugs-To: .+\\n"$|"Report-Msgid-Bugs-To: https://wordpress.org/support/theme/aggressive-apparel\\n"|' \
		"${src}" > "${dest}"
}

aa_i18n_list_po_files() {
	find "${AA_LANGUAGES_DIR}" -maxdepth 1 -type f -name "${AA_TEXT_DOMAIN}-*.po" | sort
}

aa_i18n_locale_from_po() {
	local po_file="$1"
	local base
	base="$(basename "${po_file}" .po)"
	printf '%s\n' "${base#"${AA_TEXT_DOMAIN}-"}"
}
