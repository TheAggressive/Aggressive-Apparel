#!/usr/bin/env bash
#
# Fast pre-push gate: one wp-env bring-up, ~3 minutes warm.
#
# The full rehearsal (bin/ci/verify.sh, `pnpm qa:ci`) takes ~15 minutes, mostly
# starting wp-env three times. That is right before a release and wrong before
# every push — a hook slow enough to annoy gets bypassed with --no-verify, and a
# bypassed gate is worse than a fast one.
#
# Excluded on purpose: ci:e2e (slowest by far; browser regressions come from
# block markup changes, and CI still runs it before anything can be released)
# and ci:package (only meaningful at release time, where it runs BEFORE
# publishing, so a packaging failure blocks the release rather than shipping).
#
# ci:i18n is excluded for cost, not because it rarely fires — it is one of the
# easiest lanes to trip, since the POT records source line numbers and ANY
# line-count change to a file holding a translatable string puts the committed
# catalog out of date. It is excluded because bin/ci/i18n.sh resets and stops
# its own wp-env, so adding it here buys a second full container cycle and
# roughly doubles this gate. If a POT drift round-trip ever costs more than
# that, move it in and reconsider the ~3 minute target rather than adding it
# silently. Fix a drift failure with: pnpm i18n:pot
#
# bin/ci/contracts.mjs asserts every lane below is one Actions also runs.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

pnpm ci:doctor
pnpm install --frozen-lockfile
pnpm ci:frontend
pnpm ci:build
pnpm ci:php

echo ""
echo "Fast pre-push checks passed."
echo "Before a release, run the full rehearsal: pnpm qa:ci"
