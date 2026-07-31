#!/usr/bin/env bash

# Full local rehearsal of the required GitHub Actions build/test contract.
# GitHub runs these same lanes in parallel; this script runs them serially and
# resets the isolated wp-env databases between stateful lanes like fresh runners.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

pnpm ci:doctor
pnpm install --frozen-lockfile
pnpm ci:frontend
pnpm ci:i18n
pnpm ci:build
pnpm ci:php

pnpm ci:browser:install
pnpm ci:e2e

echo "Full local CI parity verification passed."
