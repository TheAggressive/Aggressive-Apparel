#!/usr/bin/env bash
# No god files. Two-tier line budget for non-test source files:
#   > HARD_MAX (1000)  → FAIL the build.
#   > WARN_AT  (800)   → warn only (visibility; the danger zone).
#
# Large files concentrate responsibility and hide subtle bugs (see the
# interactivity store-merge regression). Split by responsibility instead:
# extract pure logic to sibling modules; for Interactivity stores, keep getters
# in the one state literal and move actions into extra `store()` calls (they
# merge by namespace); for PHP, extract collaborators/traits (PSR-4, one class
# per file). Tests are exempt (fixtures + table-driven specs legitimately run
# long). No allowlist — the cap holds for every non-test source file.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

HARD_MAX="${MAX_FILE_LINES:-1000}"
WARN_AT="${WARN_FILE_LINES:-800}"
EXIT=0
WARNED=0

echo "Checking source file lengths (warn > ${WARN_AT}, fail > ${HARD_MAX})..."

while IFS= read -r -d '' file; do
	lines=$(wc -l <"$file")
	if [[ "$lines" -gt "$HARD_MAX" ]]; then
		echo "  FAIL: $file has $lines lines (> $HARD_MAX)"
		EXIT=1
	elif [[ "$lines" -gt "$WARN_AT" ]]; then
		echo "  warn: $file has $lines lines (> $WARN_AT)"
		WARNED=1
	fi
done < <(
	find src \( -name '*.ts' -o -name '*.tsx' \) \
		-not -path '*/__tests__/*' -not -name '*.test.ts' -not -name '*.test.tsx' -print0
	find includes -name '*.php' -print0
	# The build tooling is held to the same budget. bin/ci/contracts.mjs reached
	# 1007 lines before anyone noticed, because this gate only looked at
	# shipping code — and an unreadable drift guard is the one file where
	# nobody wanting to read it is the actual risk.
	find bin -name '*.mjs' -not -name '*.test.mjs' -print0
	# Stylesheets too. A 1600-line stylesheet is as hard to navigate as a
	# 1600-line class, and badge-studio.css reached that size precisely because
	# this gate did not look at CSS. `_`-prefixed files are @import partials of
	# a sibling entry and are measured on their own, which is the point: the
	# entry stays small and each section is independently readable.
	find src \( -name '*.css' \) -print0
)

if [[ "$EXIT" -ne 0 ]]; then
	echo ""
	echo "Split oversized files by responsibility — do not raise the cap."
elif [[ "$WARNED" -eq 0 ]]; then
	echo "File-length check passed (all source files ≤ ${WARN_AT} lines)."
else
	echo "File-length check passed (no file over the ${HARD_MAX}-line hard cap)."
fi

exit "$EXIT"
