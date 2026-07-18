#!/usr/bin/env bash
# Fail when a gettext string with placeholders lacks a translators comment.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

cd "${AA_THEME_ROOT}"

# Scan PHP for __() / esc_*__() / _x() / _n() calls containing % placeholders
# without a /* translators: */ comment in the preceding 5 non-empty lines.
#
# This is a pragmatic guardrail — not a full PHP parser.
mapfile -t files < <(
	find includes patterns src templates \
		\( -name '*.php' -o -name '*.tsx' -o -name '*.ts' -o -name '*.jsx' -o -name '*.js' \) \
		-type f 2>/dev/null | sort
)

failures=0

for file in "${files[@]}"; do
	[[ -f "${file}" ]] || continue

	# Only PHP uses /* translators: */ convention reliably.
	[[ "${file}" == *.php ]] || continue

	# Line numbers of gettext calls that look like they have placeholders.
	mapfile -t hits < <(
		grep -nE "(__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_n|_nx|_x)\s*\(\s*['\"][^'\"]*%[0-9$]*[sd]" "${file}" \
			| cut -d: -f1 || true
	)

	for line_no in "${hits[@]:-}"; do
		[[ -n "${line_no}" ]] || continue
		start=$(( line_no > 5 ? line_no - 5 : 1 ))
		window="$(sed -n "${start},${line_no}p" "${file}")"
		if ! grep -q 'translators:' <<< "${window}"; then
			printf 'i18n: missing translators comment: %s:%s\n' "${file}" "${line_no}"
			sed -n "${line_no}p" "${file}" | sed 's/^/  /'
			failures=$(( failures + 1 ))
		fi
	done
done

if (( failures > 0 )); then
	aa_i18n_die "${failures} gettext string(s) with placeholders lack /* translators: */ comments."
fi

aa_i18n_info "Translator-comment lint OK."
