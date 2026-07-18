#!/usr/bin/env bash
# Print per-locale translation coverage.
set -euo pipefail

# shellcheck source=lib.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

fail_under=""
while [[ $# -gt 0 ]]; do
	case "$1" in
		--)
			shift
			;;
		--fail-under=*)
			fail_under="${1#--fail-under=}"
			shift
			;;
		--fail-under)
			fail_under="${2:-}"
			shift 2
			;;
		*)
			aa_i18n_die "Unknown argument: $1"
			;;
	esac
done

po_files="$(aa_i18n_list_po_files || true)"
if [[ -z "${po_files}" ]]; then
	aa_i18n_info "No locale .po files. Scaffold with: pnpm i18n:locale -- <locale>"
	exit 0
fi

printf '%-12s %8s %8s %8s %8s\n' "LOCALE" "TOTAL" "DONE" "FUZZY" "EMPTY"
printf '%-12s %8s %8s %8s %8s\n' "------" "-----" "----" "-----" "-----"

lowest=100
exit_code=0

while IFS= read -r po; do
	[[ -n "${po}" ]] || continue
	locale="$(aa_i18n_locale_from_po "${po}")"

	# Count msgid entries (exclude header empty msgid).
	total="$(grep -c '^msgid ' "${po}" || true)"
	total=$(( total > 0 ? total - 1 : 0 ))

	# Fuzzy entries.
	fuzzy="$(grep -c '^#,.*fuzzy' "${po}" || true)"

	# Untranslated: msgstr "" followed by blank or comment (simple heuristic).
	empty="$(awk '
		BEGIN { n=0; in_msgstr=0; empty=0; skip_header=1 }
		/^msgid ""$/ && skip_header { skip_header=0; next }
		/^msgid / { if (in_msgstr && empty) n++; in_msgstr=0; empty=0 }
		/^msgstr ""$/ { in_msgstr=1; empty=1 }
		/^msgstr ".+"/ { in_msgstr=1; empty=0 }
		/^msgstr\[[0-9]+\] ""$/ { empty=1 }
		/^msgstr\[[0-9]+\] ".+"/ { empty=0 }
		END { if (in_msgstr && empty) n++; print n }
	' "${po}")"

	done=$(( total - empty ))
	if (( done < 0 )); then
		done=0
	fi

	pct=100
	if (( total > 0 )); then
		pct=$(( (done * 100) / total ))
	fi
	if (( pct < lowest )); then
		lowest=$pct
	fi

	printf '%-12s %8d %8d %8d %8d  (%d%%)\n' "${locale}" "${total}" "${done}" "${fuzzy}" "${empty}" "${pct}"
done <<< "${po_files}"

if [[ -n "${fail_under}" ]]; then
	if (( lowest < fail_under )); then
		aa_i18n_info "Coverage ${lowest}% is below --fail-under=${fail_under}."
		exit_code=1
	fi
fi

exit "${exit_code}"
