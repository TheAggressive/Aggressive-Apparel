#!/usr/bin/env bash
# Docker-free pre-push gate.

set -euo pipefail

cd "$(dirname "$0")/../.."

pnpm ci:doctor
pnpm install --frozen-lockfile
pnpm ci:frontend
pnpm ci:build
pnpm run composer:verify
pnpm run phpstan
pnpm run test:unit

echo "Fast local checks passed without Docker."
