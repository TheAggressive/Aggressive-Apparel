#!/usr/bin/env bash
#
# Fast pre-push gate: the checks worth waiting for on every push.
#
# The full rehearsal (bin/ci/verify.sh, `pnpm qa`) runs every lane Actions runs
# and takes ~15 minutes, most of it spent bringing wp-env up three separate
# times — once for PHP, once for the browser suite, once for packaging. That is
# the right thing to run before a release and the wrong thing to run before
# every push: a hook slow enough to be annoying is a hook that gets bypassed
# with --no-verify, and a bypassed gate is worse than a fast one.
#
# This keeps one wp-env bring-up and the checks that catch the mistakes people
# actually push. Measured at ~3 minutes warm, versus ~15 for the full run:
#
#   ci:frontend  lint, types, 549 JS tests, the drift contract, audit
#   ci:build     the assets actually compile
#   ci:php       PHPCS, PHPStan level 8, every PHPUnit suite
#
# Deliberately NOT here, and why it is safe:
#
#   ci:e2e       the slowest lane by far; browser regressions come from block
#                markup changes, not routine edits, and CI still runs it on
#                every push before anything can be released.
#   ci:package   only meaningful when a release is cut, and it runs BEFORE
#                publishing — a packaging failure blocks the release rather
#                than shipping a broken one.
#
# bin/ci/contracts.mjs asserts every lane below is one Actions also runs, so
# this can never drift into testing something CI does not.

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
echo "Before a release, run the full rehearsal: pnpm qa"
